<?php

namespace Tests\Feature\DelayAlerts;

use App\Jobs\SendDelayAlert;
use App\Mail\ShipmentDelayedMail;
use App\Models\DelayAlertDelivery;
use App\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendDelayAlertTest extends DelayAlertTestCase
{
    public function test_job_cancels_a_delivery_from_an_older_manual_delay_report_cycle(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'actual_arrival' => null,
            'estimated_arrival' => today()->addDays(5),
            'status' => 'Dalam perjalanan',
            'delay_reported_at' => now(),
            'delay_report_sequence' => 1,
        ])->save();
        $shipment->refresh();

        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'pelanggan@logitrack.test',
            'pelanggan@logitrack.test',
            'customer',
        );

        $shipment->forceFill([
            'delay_reported_at' => now()->addMinute(),
            'delay_report_sequence' => 2,
        ])->save();

        config([
            'delay-alerts.notify_customer' => true,
            'delay-alerts.operations_emails' => [],
        ]);
        Mail::fake();
        Http::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertNothingSent();
        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame(1, $delivery->delay_report_sequence);
        $this->assertSame(DelayAlertDelivery::STATUS_CANCELLED, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertNotNull($delivery->cancelled_at);
    }

    public function test_operator_reported_delay_mail_does_not_claim_the_future_eta_was_missed(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'actual_arrival' => null,
            'estimated_arrival' => today()->addDays(5),
            'status' => 'Dalam perjalanan',
            'delay_reported_at' => now(),
        ])->save();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'recipient@example.test',
            'recipient@example.test',
            'operations',
        );

        config(['delay-alerts.operations_emails' => ['recipient@example.test']]);
        Mail::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertSent(ShipmentDelayedMail::class, function (ShipmentDelayedMail $mail): bool {
            $mail->assertSeeInText('Kondisi: Dilaporkan terlambat');
            $mail->assertDontSeeInText('Keterlambatan: 1 hari');

            return true;
        });
    }

    public function test_operator_reported_delay_webhook_uses_zero_days_late_before_eta(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'actual_arrival' => null,
            'estimated_arrival' => today()->addDays(5),
            'status' => 'Dalam perjalanan',
            'delay_reported_at' => now(),
        ])->save();
        $webhookUrl = 'https://alerts.example.test/events/reported-delay';
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_WEBHOOK,
            $webhookUrl,
            'webhook://alerts.example.test',
            'system',
        );

        config(['delay-alerts.webhook.url' => $webhookUrl]);
        Http::fake(function (Request $request) {
            $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(0, $payload['shipment']['days_late']);

            return Http::response(null, 202);
        });

        (new SendDelayAlert($delivery->id))->handle();

        Http::assertSentCount(1);
        $this->assertSame(DelayAlertDelivery::STATUS_SENT, $delivery->refresh()->status);
    }

    public function test_queued_job_sends_mail_and_marks_delivery_as_sent(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'recipient@example.test',
            'recipient@example.test',
            'operations',
        );

        config([
            'delay-alerts.operations_emails' => ['recipient@example.test'],
        ]);

        Mail::fake();
        Http::preventStrayRequests();

        $job = new SendDelayAlert($delivery->id);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) $delivery->id, $job->uniqueId());

        $job->handle();

        Mail::assertSent(
            ShipmentDelayedMail::class,
            fn (ShipmentDelayedMail $mail) => $mail->hasTo('recipient@example.test')
                && $mail->shipment->is($shipment)
                && $mail->delivery->is($delivery)
                && $mail->envelope()->subject === 'Pengiriman BOOK-2026-000124 mengalami keterlambatan',
        );
        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->last_attempt_at);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->last_error);
    }

    public function test_plain_text_mail_keeps_quotes_ampersands_and_indonesian_characters_readable(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $shipment->customer->update(['name' => 'A&B "Logistik" Nusantara']);
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'pelanggan@logitrack.test',
            'pelanggan@logitrack.test',
            'customer',
        );
        $delivery->update(['message' => 'Pembaruan "aman" & jelas untuk pengiriman.']);

        config([
            'delay-alerts.notify_customer' => true,
            'delay-alerts.operations_emails' => [],
        ]);

        Mail::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertSent(ShipmentDelayedMail::class, function (ShipmentDelayedMail $mail): bool {
            $mail->assertSeeInText('A&B "Logistik" Nusantara');
            $mail->assertSeeInText('Pembaruan "aman" & jelas untuk pengiriman.');
            $mail->assertDontSeeInText('&amp;');
            $mail->assertDontSeeInText('&quot;');

            return true;
        });
    }

    public function test_webhook_job_sends_expected_payload_with_hmac_signature(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $webhookUrl = 'https://alerts.example.test/events/delayed';
        $secret = 'test-signing-secret';
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_WEBHOOK,
            $webhookUrl,
            'webhook://alerts.example.test',
            'system',
        );

        config([
            'delay-alerts.webhook.url' => $webhookUrl,
            'delay-alerts.webhook.secret' => $secret,
            'delay-alerts.webhook.timeout' => 7,
        ]);

        Http::fake(function (Request $request) use ($delivery, $secret, $shipment, $webhookUrl) {
            $body = $request->body();
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            $timestamp = now()->toIso8601String();

            $this->assertSame($webhookUrl, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame(DelayAlertDelivery::EVENT, $request->header('X-LogiTrack-Event')[0] ?? null);
            $this->assertSame((string) $delivery->id, $request->header('X-LogiTrack-Delivery')[0] ?? null);
            $this->assertSame($timestamp, $request->header('X-LogiTrack-Timestamp')[0] ?? null);
            $this->assertSame(
                'sha256='.hash_hmac('sha256', $body, $secret),
                $request->header('X-LogiTrack-Signature')[0] ?? null,
            );

            $this->assertSame(DelayAlertDelivery::EVENT, $payload['event']);
            $this->assertSame($delivery->id, $payload['delivery_id']);
            $this->assertSame($timestamp, $payload['occurred_at']);
            $this->assertSame([
                'booking_number' => $shipment->booking_number,
                'container_number' => $shipment->container->container_number,
                'origin' => $shipment->originPort->city,
                'destination' => $shipment->destinationPort->city,
                'estimated_arrival' => today()->subDay()->toDateString(),
                'days_late' => 1,
                'status' => 'Dalam perjalanan',
                'tracking_url' => route('tracking.show', $shipment->container->container_number),
            ], $payload['shipment']);
            $this->assertSame('Pesan keterlambatan teruji.', $payload['message']);

            return Http::response(null, 202);
        });

        (new SendDelayAlert($delivery->id))->handle();

        Http::assertSentCount(1);

        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_job_cancels_stale_delivery_when_eta_has_changed(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'recipient@example.test',
            'recipient@example.test',
            'customer',
        );

        $shipment->forceFill([
            'estimated_arrival' => today()->subDays(3)->toDateString(),
        ])->save();

        Mail::fake();
        Http::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertNothingSent();
        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_CANCELLED, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertNotNull($delivery->cancelled_at);
        $this->assertStringContainsString('ETA', $delivery->last_error);
    }

    public function test_job_cancels_delivery_when_shipment_has_already_arrived(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'recipient@example.test',
            'recipient@example.test',
            'customer',
        );

        $shipment->forceFill([
            'actual_arrival' => today()->toDateString(),
            'status' => 'Tiba di pelabuhan tujuan',
        ])->save();

        Mail::fake();
        Http::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertNothingSent();
        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_CANCELLED, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertNotNull($delivery->cancelled_at);
    }

    public function test_job_cancels_mail_when_recipient_is_no_longer_configured(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'pelanggan@logitrack.test',
            'pelanggan@logitrack.test',
            'customer',
        );

        config([
            'delay-alerts.notify_customer' => false,
            'delay-alerts.operations_emails' => [],
        ]);

        Mail::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame(DelayAlertDelivery::STATUS_CANCELLED, $delivery->refresh()->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertStringContainsString('Tujuan notifikasi', $delivery->last_error);
    }

    public function test_job_honors_global_disable_switch_before_sending(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'pelanggan@logitrack.test',
            'pelanggan@logitrack.test',
            'customer',
        );

        config(['delay-alerts.enabled' => false]);
        Mail::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame(DelayAlertDelivery::STATUS_CANCELLED, $delivery->refresh()->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertStringContainsString('dinonaktifkan', $delivery->last_error);
    }

    public function test_job_does_not_send_when_another_worker_has_an_active_claim(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'recipient@example.test',
            'recipient@example.test',
            'operations',
        );
        $delivery->forceFill([
            'status' => DelayAlertDelivery::STATUS_PROCESSING,
            'processing_token' => '11111111-1111-1111-1111-111111111111',
            'attempts' => 1,
            'last_attempt_at' => now(),
        ])->save();

        config([
            'delay-alerts.operations_emails' => ['recipient@example.test'],
        ]);
        Mail::fake();

        (new SendDelayAlert($delivery->id, '11111111-1111-1111-1111-111111111111'))->handle();

        Mail::assertNothingSent();
        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_PROCESSING, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $delivery->processing_token);
    }

    public function test_timed_out_worker_retry_is_released_until_its_processing_lease_expires(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'recipient@example.test',
            'recipient@example.test',
            'operations',
        );
        $processingToken = '11111111-1111-1111-1111-111111111111';
        $delivery->forceFill([
            'status' => DelayAlertDelivery::STATUS_PROCESSING,
            'processing_token' => $processingToken,
            'attempts' => 1,
            'last_attempt_at' => now()->subMinute(),
        ])->save();

        config([
            'delay-alerts.operations_emails' => ['recipient@example.test'],
            'delay-alerts.processing_timeout_minutes' => 5,
        ]);
        Mail::fake();

        $job = (new SendDelayAlert($delivery->id, $processingToken))
            ->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(240);
        Mail::assertNothingSent();
        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_PROCESSING, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame($processingToken, $delivery->processing_token);
    }

    public function test_job_can_cancel_an_invalid_delivery_after_processing_lease_expires(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_MAIL,
            'pelanggan@logitrack.test',
            'pelanggan@logitrack.test',
            'customer',
        );
        $delivery->forceFill([
            'status' => DelayAlertDelivery::STATUS_PROCESSING,
            'processing_token' => '11111111-1111-1111-1111-111111111111',
            'attempts' => 1,
            'last_attempt_at' => now()->subMinutes(6),
        ])->save();

        config([
            'delay-alerts.notify_customer' => false,
            'delay-alerts.operations_emails' => [],
            'delay-alerts.processing_timeout_minutes' => 5,
        ]);
        Mail::fake();

        (new SendDelayAlert($delivery->id))->handle();

        Mail::assertNothingSent();
        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_CANCELLED, $delivery->status);
        $this->assertNull($delivery->processing_token);
        $this->assertStringContainsString('Tujuan notifikasi', $delivery->last_error);
    }

    public function test_webhook_redirect_is_recorded_as_failure_instead_of_sent(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $webhookUrl = 'https://alerts.example.test/events/delayed';
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_WEBHOOK,
            $webhookUrl,
            'webhook://alerts.example.test',
            'system',
        );

        config(['delay-alerts.webhook.url' => $webhookUrl]);
        Http::fake([$webhookUrl => Http::response(null, 302, ['Location' => 'https://login.example.test'])]);

        try {
            (new SendDelayAlert($delivery->id))->handle();
            $this->fail('Webhook redirect seharusnya dianggap gagal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP status 302', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNull($delivery->sent_at);
        $this->assertStringContainsString('HTTP status 302', $delivery->last_error);
    }

    public function test_webhook_connection_failure_does_not_expose_secret_url(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $webhookUrl = 'https://alerts.example.test/events/delayed?token=super-secret';
        $delivery = $this->createDelivery(
            $shipment,
            DelayAlertDelivery::CHANNEL_WEBHOOK,
            $webhookUrl,
            'webhook://alerts.example.test',
            'system',
        );

        config(['delay-alerts.webhook.url' => $webhookUrl]);
        Http::fake(fn () => throw new ConnectionException('Connection failed for '.$webhookUrl));

        try {
            (new SendDelayAlert($delivery->id))->handle();
            $this->fail('Kegagalan koneksi webhook seharusnya melempar exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Webhook tidak dapat dihubungi.', $exception->getMessage());
            $this->assertStringNotContainsString('super-secret', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_FAILED, $delivery->status);
        $this->assertStringNotContainsString('super-secret', (string) $delivery->last_error);
        $this->assertStringNotContainsString($webhookUrl, (string) $delivery->last_error);
    }

    private function createDelivery(
        Shipment $shipment,
        string $channel,
        string $hashDestination,
        string $storedDestination,
        string $audience,
    ): DelayAlertDelivery {
        return DelayAlertDelivery::query()->create([
            'shipment_id' => $shipment->id,
            'expected_arrival' => $shipment->estimated_arrival->toDateString(),
            'delay_report_sequence' => $shipment->delay_report_sequence,
            'event' => DelayAlertDelivery::EVENT,
            'channel' => $channel,
            'audience' => $audience,
            'destination' => $storedDestination,
            'destination_hash' => DelayAlertDelivery::destinationHash($channel, $hashDestination),
            'message' => 'Pesan keterlambatan teruji.',
            'status' => DelayAlertDelivery::STATUS_PENDING,
        ]);
    }
}
