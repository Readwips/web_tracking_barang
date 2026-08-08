<?php

namespace App\Jobs;

use App\Mail\ShipmentDelayedMail;
use App\Models\DelayAlertDelivery;
use App\Services\DelayAlertDestinationResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendDelayAlert implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    public int $uniqueFor = 1800;

    public function __construct(
        public int $deliveryId,
        public ?string $processingToken = null,
    ) {
        $this->processingToken ??= (string) Str::uuid();
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(?DelayAlertDestinationResolver $destinationResolver = null): void
    {
        $destinationResolver ??= app(DelayAlertDestinationResolver::class);
        $delivery = $this->findDelivery();

        if (! $delivery || in_array($delivery->status, [
            DelayAlertDelivery::STATUS_SENT,
            DelayAlertDelivery::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $processingTimeout = max(1, (int) config('delay-alerts.processing_timeout_minutes', 5));
        $isBeingProcessed = $delivery->status === DelayAlertDelivery::STATUS_PROCESSING
            && $delivery->last_attempt_at?->gt(now()->subMinutes($processingTimeout));

        if ($isBeingProcessed) {
            if ($delivery->processing_token === $this->processingToken && $this->job) {
                $leaseExpiresAt = $delivery->last_attempt_at->copy()->addMinutes($processingTimeout);
                $secondsUntilLeaseExpires = max(
                    1,
                    $leaseExpiresAt->getTimestamp() - now()->getTimestamp(),
                );

                $this->release($secondsUntilLeaseExpires);
            }

            return;
        }

        $currentDestination = $this->validateDelivery($delivery, $destinationResolver);

        if (! $currentDestination) {
            return;
        }

        $delivery = $this->claimDelivery();

        if (! $delivery) {
            return;
        }

        $currentDestination = $this->validateDelivery($delivery, $destinationResolver);

        if (! $currentDestination) {
            return;
        }

        $delivery->forceFill([
            'audience' => $currentDestination['audience'],
            'destination' => $currentDestination['stored_destination'],
        ])->save();

        try {
            match ($delivery->channel) {
                DelayAlertDelivery::CHANNEL_MAIL => $this->sendMail($delivery, $currentDestination['target']),
                DelayAlertDelivery::CHANNEL_WEBHOOK => $this->sendWebhook($delivery, $currentDestination['target']),
                default => throw new RuntimeException('Kanal notifikasi tidak didukung.'),
            };

            DelayAlertDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', DelayAlertDelivery::STATUS_PROCESSING)
                ->where('processing_token', $this->processingToken)
                ->update([
                    'status' => DelayAlertDelivery::STATUS_SENT,
                    'processing_token' => null,
                    'sent_at' => now(),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            DelayAlertDelivery::query()
                ->whereKey($delivery->id)
                ->where('processing_token', $this->processingToken)
                ->update([
                    'status' => DelayAlertDelivery::STATUS_FAILED,
                    'processing_token' => null,
                    'last_error' => $this->safeError($exception),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = DelayAlertDelivery::find($this->deliveryId);

        if (! $delivery || in_array($delivery->status, [
            DelayAlertDelivery::STATUS_SENT,
            DelayAlertDelivery::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        DelayAlertDelivery::query()
            ->whereKey($delivery->id)
            ->where(function ($query): void {
                $query->where('processing_token', $this->processingToken)
                    ->orWhere('status', DelayAlertDelivery::STATUS_FAILED);
            })
            ->update([
                'status' => DelayAlertDelivery::STATUS_FAILED,
                'processing_token' => null,
                'last_error' => $exception ? $this->safeError($exception) : $delivery->last_error,
                'updated_at' => now(),
            ]);
    }

    private function sendMail(DelayAlertDelivery $delivery, string $email): void
    {
        Mail::to($email)->send(new ShipmentDelayedMail($delivery->shipment, $delivery));
    }

    private function sendWebhook(DelayAlertDelivery $delivery, string $url): void
    {
        if (DelayAlertDelivery::destinationHash(DelayAlertDelivery::CHANNEL_WEBHOOK, $url) !== $delivery->destination_hash) {
            throw new RuntimeException('URL webhook tidak valid atau tidak aman.');
        }

        $shipment = $delivery->shipment;
        $timestamp = now()->toIso8601String();
        $payload = [
            'event' => DelayAlertDelivery::EVENT,
            'delivery_id' => $delivery->id,
            'occurred_at' => $timestamp,
            'shipment' => [
                'booking_number' => $shipment->booking_number,
                'container_number' => $shipment->container->container_number,
                'origin' => $shipment->originPort->city,
                'destination' => $shipment->destinationPort->city,
                'estimated_arrival' => $delivery->expected_arrival->toDateString(),
                'days_late' => max(1, (int) $delivery->expected_arrival->startOfDay()->diffInDays(today())),
                'status' => $shipment->status,
                'tracking_url' => route('tracking.show', $shipment->container->container_number),
            ],
            'message' => $delivery->message,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'X-LogiTrack-Event' => DelayAlertDelivery::EVENT,
            'X-LogiTrack-Delivery' => (string) $delivery->id,
            'X-LogiTrack-Timestamp' => $timestamp,
        ];
        $secret = (string) config('delay-alerts.webhook.secret');

        if ($secret !== '') {
            $headers['X-LogiTrack-Signature'] = 'sha256='.hash_hmac('sha256', $body, $secret);
        }

        $timeout = min(30, max(1, (int) config('delay-alerts.webhook.timeout', 10)));

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->withOptions(['allow_redirects' => false])
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (Throwable) {
            throw new RuntimeException('Webhook tidak dapat dihubungi.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Webhook gagal dengan HTTP status '.$response->status().'.');
        }

    }

    private function findDelivery(): ?DelayAlertDelivery
    {
        return DelayAlertDelivery::query()
            ->with([
                'shipment.customer.user',
                'shipment.container',
                'shipment.originPort',
                'shipment.destinationPort',
            ])
            ->find($this->deliveryId);
    }

    private function claimDelivery(): ?DelayAlertDelivery
    {
        $now = now();
        $maxAttempts = max(1, (int) config('delay-alerts.max_attempts', 5));
        $processingTimeout = max(1, (int) config('delay-alerts.processing_timeout_minutes', 5));
        $staleBefore = $now->copy()->subMinutes($processingTimeout);

        $claimed = DelayAlertDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('attempts', '<', $maxAttempts)
            ->where(function ($query) use ($staleBefore): void {
                $query->whereIn('status', [
                    DelayAlertDelivery::STATUS_PENDING,
                    DelayAlertDelivery::STATUS_FAILED,
                ])->orWhere(function ($query) use ($staleBefore): void {
                    $query->where('status', DelayAlertDelivery::STATUS_PROCESSING)
                        ->where(function ($query) use ($staleBefore): void {
                            $query->whereNull('last_attempt_at')
                                ->orWhere('last_attempt_at', '<=', $staleBefore);
                        });
                });
            })
            ->update([
                'status' => DelayAlertDelivery::STATUS_PROCESSING,
                'processing_token' => $this->processingToken,
                'attempts' => DB::raw('attempts + 1'),
                'last_attempt_at' => $now,
                'last_error' => null,
                'updated_at' => $now,
            ]);

        return $claimed === 1 ? $this->findDelivery() : null;
    }

    /**
     * @return array{channel:string, audience:string, target:string, stored_destination:string, label:string}|null
     */
    private function validateDelivery(
        DelayAlertDelivery $delivery,
        DelayAlertDestinationResolver $destinationResolver,
    ): ?array {
        if (! config('delay-alerts.enabled')) {
            $this->cancel($delivery, 'Notifikasi keterlambatan telah dinonaktifkan.');

            return null;
        }

        $shipment = $delivery->shipment;

        if (! $shipment->isDelayed()
            || ! $shipment->estimated_arrival->isSameDay($delivery->expected_arrival)) {
            $this->cancel($delivery, 'Pengiriman tidak lagi memenuhi kondisi keterlambatan untuk ETA ini.');

            return null;
        }

        $destination = $destinationResolver->currentFor($delivery, $shipment);

        if (! $destination) {
            $this->cancel($delivery, 'Tujuan notifikasi tidak lagi aktif atau telah berubah.');

            return null;
        }

        return $destination;
    }

    private function cancel(DelayAlertDelivery $delivery, string $reason): void
    {
        $processingTimeout = max(1, (int) config('delay-alerts.processing_timeout_minutes', 5));
        $staleBefore = now()->subMinutes($processingTimeout);

        DelayAlertDelivery::query()
            ->whereKey($delivery->id)
            ->whereNotIn('status', [
                DelayAlertDelivery::STATUS_SENT,
                DelayAlertDelivery::STATUS_CANCELLED,
            ])
            ->where(function ($query) use ($staleBefore): void {
                $query->where('status', '!=', DelayAlertDelivery::STATUS_PROCESSING)
                    ->orWhere('processing_token', $this->processingToken)
                    ->orWhere(function ($query) use ($staleBefore): void {
                        $query->where('status', DelayAlertDelivery::STATUS_PROCESSING)
                            ->where(function ($query) use ($staleBefore): void {
                                $query->whereNull('last_attempt_at')
                                    ->orWhere('last_attempt_at', '<=', $staleBefore);
                            });
                    });
            })
            ->update([
                'status' => DelayAlertDelivery::STATUS_CANCELLED,
                'processing_token' => null,
                'cancelled_at' => now(),
                'last_error' => $reason,
                'updated_at' => now(),
            ]);

        $delivery->refresh();
    }

    private function safeError(Throwable $exception): string
    {
        $message = preg_replace('~https?://\\S+~i', '[URL disembunyikan]', $exception->getMessage());

        return Str::limit(class_basename($exception).': '.($message ?: 'Pengiriman notifikasi gagal.'), 1000);
    }
}
