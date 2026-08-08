<?php

namespace Tests\Feature\DelayAlerts;

use App\Jobs\SendDelayAlert;
use App\Models\DelayAlertDelivery;
use App\Services\AiAssistantService;
use Illuminate\Support\Facades\Queue;
use Mockery;

class NotifyDelayedShipmentsCommandTest extends DelayAlertTestCase
{
    public function test_command_creates_customer_operations_and_webhook_deliveries_idempotently(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $webhookUrl = 'https://hooks.example.test/logitrack/delays';

        config([
            'delay-alerts.enabled' => true,
            'delay-alerts.notify_customer' => true,
            'delay-alerts.operations_emails' => [
                ' OPS@example.test ',
                'ops@example.test',
                'not-an-email',
                'pelanggan@logitrack.test',
            ],
            'delay-alerts.webhook.url' => $webhookUrl,
        ]);

        Queue::fake();

        $assistant = Mockery::mock(AiAssistantService::class);
        $assistant->shouldReceive('delayedShipmentNotice')
            ->once()
            ->withNoArgs()
            ->andReturn('Pesan keterlambatan yang telah disiapkan.');
        $this->app->instance(AiAssistantService::class, $assistant);

        $this->artisan('shipments:notify-delays')->assertSuccessful();
        $this->artisan('shipments:notify-delays')->assertSuccessful();

        $this->assertSame(3, DelayAlertDelivery::query()->whereBelongsTo($shipment)->count());

        $this->assertDatabaseHas('delay_alert_deliveries', [
            'shipment_id' => $shipment->id,
            'channel' => DelayAlertDelivery::CHANNEL_MAIL,
            'audience' => 'customer',
            'destination' => 'pelanggan@logitrack.test',
            'destination_hash' => DelayAlertDelivery::destinationHash(
                DelayAlertDelivery::CHANNEL_MAIL,
                'pelanggan@logitrack.test',
            ),
            'message' => 'Pesan keterlambatan yang telah disiapkan.',
            'status' => DelayAlertDelivery::STATUS_PENDING,
        ]);
        $customerDelivery = DelayAlertDelivery::query()
            ->whereBelongsTo($shipment)
            ->where('audience', 'customer')
            ->firstOrFail();
        $this->assertTrue($customerDelivery->expected_arrival->isSameDay(today()->subDay()));
        $this->assertDatabaseHas('delay_alert_deliveries', [
            'shipment_id' => $shipment->id,
            'channel' => DelayAlertDelivery::CHANNEL_MAIL,
            'audience' => 'operations',
            'destination' => 'ops@example.test',
            'destination_hash' => DelayAlertDelivery::destinationHash(
                DelayAlertDelivery::CHANNEL_MAIL,
                'ops@example.test',
            ),
        ]);
        $this->assertDatabaseHas('delay_alert_deliveries', [
            'shipment_id' => $shipment->id,
            'channel' => DelayAlertDelivery::CHANNEL_WEBHOOK,
            'audience' => 'system',
            'destination' => 'webhook://hooks.example.test',
            'destination_hash' => DelayAlertDelivery::destinationHash(
                DelayAlertDelivery::CHANNEL_WEBHOOK,
                $webhookUrl,
            ),
        ]);
        $this->assertDatabaseMissing('delay_alert_deliveries', [
            'destination' => 'not-an-email',
        ]);

        Queue::assertPushed(SendDelayAlert::class, 3);
        foreach (DelayAlertDelivery::query()->whereBelongsTo($shipment)->get() as $delivery) {
            Queue::assertPushed(
                SendDelayAlert::class,
                fn (SendDelayAlert $job) => $job->deliveryId === $delivery->id,
            );
        }
    }

    public function test_eta_change_creates_a_new_delivery_for_the_new_expected_arrival(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();

        config([
            'delay-alerts.enabled' => true,
            'delay-alerts.notify_customer' => true,
            'delay-alerts.operations_emails' => [],
            'delay-alerts.webhook.url' => null,
        ]);

        Queue::fake();

        $assistant = Mockery::mock(AiAssistantService::class);
        $assistant->shouldReceive('delayedShipmentNotice')
            ->twice()
            ->andReturn('Pesan untuk ETA aktif.');
        $this->app->instance(AiAssistantService::class, $assistant);

        $this->artisan('shipments:notify-delays')->assertSuccessful();

        $shipment->forceFill([
            'estimated_arrival' => today()->subDays(3)->toDateString(),
        ])->save();

        $this->artisan('shipments:notify-delays')->assertSuccessful();

        $deliveries = DelayAlertDelivery::query()
            ->whereBelongsTo($shipment)
            ->orderBy('expected_arrival')
            ->get();

        $this->assertCount(2, $deliveries);
        $this->assertSame(
            [today()->subDays(3)->toDateString(), today()->subDay()->toDateString()],
            $deliveries->pluck('expected_arrival')->map->toDateString()->all(),
        );
        $this->assertSame(
            [DelayAlertDelivery::STATUS_PENDING, DelayAlertDelivery::STATUS_PENDING],
            $deliveries->pluck('status')->all(),
        );
        Queue::assertPushed(SendDelayAlert::class, 2);
    }

    public function test_ai_notice_is_generated_once_and_reused_for_multiple_delayed_shipments(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'actual_arrival' => null,
            'estimated_arrival' => today()->subDay()->toDateString(),
            'status' => 'Dalam perjalanan',
        ])->save();

        config([
            'delay-alerts.enabled' => true,
            'delay-alerts.notify_customer' => false,
            'delay-alerts.operations_emails' => ['ops@example.test'],
            'delay-alerts.webhook.url' => null,
        ]);

        Queue::fake();
        $assistant = Mockery::mock(AiAssistantService::class);
        $assistant->shouldReceive('delayedShipmentNotice')
            ->once()
            ->withNoArgs()
            ->andReturn('Satu pesan netral untuk satu batch pemeriksaan.');
        $this->app->instance(AiAssistantService::class, $assistant);

        $this->artisan('shipments:notify-delays')->assertSuccessful();

        $this->assertSame(2, DelayAlertDelivery::query()->count());
        $this->assertSame(
            ['Satu pesan netral untuk satu batch pemeriksaan.'],
            DelayAlertDelivery::query()->distinct()->pluck('message')->all(),
        );
        Queue::assertPushed(SendDelayAlert::class, 2);
    }

    public function test_command_does_not_redispatch_delivery_after_total_attempt_limit(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $email = 'pelanggan@logitrack.test';

        config([
            'delay-alerts.enabled' => true,
            'delay-alerts.notify_customer' => true,
            'delay-alerts.operations_emails' => [],
            'delay-alerts.webhook.url' => null,
            'delay-alerts.max_attempts' => 5,
        ]);

        DelayAlertDelivery::query()->create([
            'shipment_id' => $shipment->id,
            'expected_arrival' => $shipment->estimated_arrival,
            'event' => DelayAlertDelivery::EVENT,
            'channel' => DelayAlertDelivery::CHANNEL_MAIL,
            'audience' => 'customer',
            'destination' => $email,
            'destination_hash' => DelayAlertDelivery::destinationHash(DelayAlertDelivery::CHANNEL_MAIL, $email),
            'message' => 'Pesan yang gagal dikirim.',
            'status' => DelayAlertDelivery::STATUS_FAILED,
            'attempts' => 5,
            'last_attempt_at' => now()->subHours(2),
        ]);

        Queue::fake();
        $assistant = Mockery::mock(AiAssistantService::class);
        $assistant->shouldNotReceive('delayedShipmentNotice');
        $this->app->instance(AiAssistantService::class, $assistant);

        $this->artisan('shipments:notify-delays')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(1, DelayAlertDelivery::query()->whereBelongsTo($shipment)->count());

        $this->artisan('shipments:notify-delays', ['--retry-failed' => true])->assertSuccessful();

        Queue::assertPushed(SendDelayAlert::class, 1);
        $delivery = DelayAlertDelivery::query()->whereBelongsTo($shipment)->firstOrFail();
        $this->assertSame(DelayAlertDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertNull($delivery->last_attempt_at);
        $this->assertNull($delivery->last_error);
    }

    public function test_command_does_not_invalidate_an_active_claim_at_attempt_limit(): void
    {
        $shipment = $this->makeOnlyCustomerShipmentDelayed();
        $email = 'pelanggan@logitrack.test';
        $token = '11111111-1111-1111-1111-111111111111';

        config([
            'delay-alerts.enabled' => true,
            'delay-alerts.notify_customer' => true,
            'delay-alerts.operations_emails' => [],
            'delay-alerts.webhook.url' => null,
            'delay-alerts.max_attempts' => 5,
            'delay-alerts.processing_timeout_minutes' => 5,
        ]);

        $delivery = DelayAlertDelivery::query()->create([
            'shipment_id' => $shipment->id,
            'expected_arrival' => $shipment->estimated_arrival,
            'event' => DelayAlertDelivery::EVENT,
            'channel' => DelayAlertDelivery::CHANNEL_MAIL,
            'audience' => 'customer',
            'destination' => $email,
            'destination_hash' => DelayAlertDelivery::destinationHash(DelayAlertDelivery::CHANNEL_MAIL, $email),
            'message' => 'Pesan sedang diproses.',
            'status' => DelayAlertDelivery::STATUS_PROCESSING,
            'processing_token' => $token,
            'attempts' => 5,
            'last_attempt_at' => now(),
        ]);

        Queue::fake();
        $assistant = Mockery::mock(AiAssistantService::class);
        $assistant->shouldNotReceive('delayedShipmentNotice');
        $this->app->instance(AiAssistantService::class, $assistant);

        $this->artisan('shipments:notify-delays')->assertSuccessful();

        Queue::assertNothingPushed();
        $delivery->refresh();
        $this->assertSame(DelayAlertDelivery::STATUS_PROCESSING, $delivery->status);
        $this->assertSame($token, $delivery->processing_token);
        $this->assertSame(5, $delivery->attempts);
    }
}
