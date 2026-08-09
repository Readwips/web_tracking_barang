<?php

namespace App\Console\Commands;

use App\Jobs\SendDelayAlert;
use App\Models\DelayAlertDelivery;
use App\Models\Shipment;
use App\Services\AiAssistantService;
use App\Services\DelayAlertDestinationResolver;
use Illuminate\Console\Command;
use Throwable;

class NotifyDelayedShipments extends Command
{
    protected $signature = 'shipments:notify-delays
        {--sync : Kirim notifikasi langsung tanpa menunggu queue worker}
        {--dry-run : Tampilkan kandidat tanpa membuat delivery atau mengirim notifikasi}
        {--retry-failed : Reset dan proses kembali delivery yang sudah mencapai batas percobaan}';

    protected $description = 'Detect delayed shipments and dispatch configured email and webhook alerts';

    public function handle(AiAssistantService $assistant, DelayAlertDestinationResolver $destinationResolver): int
    {
        if (! config('delay-alerts.enabled')) {
            $this->components->info('Notifikasi keterlambatan dinonaktifkan.');

            return self::SUCCESS;
        }

        $summary = [
            'shipments' => 0,
            'destinations' => 0,
            'created' => 0,
            'dispatched' => 0,
            'already_sent' => 0,
            'cancelled' => 0,
            'without_destination' => 0,
            'deferred' => 0,
            'exhausted' => 0,
            'reset' => 0,
            'failed' => 0,
        ];
        $notificationMessage = null;

        Shipment::query()
            ->delayed()
            ->with(['customer.user', 'container', 'originPort', 'destinationPort'])
            ->chunkById(100, function ($shipments) use ($assistant, $destinationResolver, &$notificationMessage, &$summary): void {
                foreach ($shipments as $shipment) {
                    $summary['shipments']++;
                    $destinations = $destinationResolver->forShipment($shipment);
                    $summary['destinations'] += count($destinations);

                    if ($destinations === []) {
                        $summary['without_destination']++;
                        $this->components->warn(
                            $shipment->booking_number.': tidak ada email pelanggan, email operasional, atau webhook yang valid.'
                        );

                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line($shipment->booking_number.' -> '.implode(', ', array_column($destinations, 'label')));

                        continue;
                    }

                    $expectedArrival = $shipment->estimated_arrival->copy()->startOfDay();

                    foreach ($destinations as $destinationHash => $destination) {
                        $attributes = [
                            'shipment_id' => $shipment->id,
                            'expected_arrival' => $expectedArrival,
                            'delay_report_sequence' => $shipment->delay_report_sequence,
                            'event' => DelayAlertDelivery::EVENT,
                            'channel' => $destination['channel'],
                            'destination_hash' => $destinationHash,
                        ];
                        $delivery = DelayAlertDelivery::query()->where($attributes)->first();

                        if (! $delivery) {
                            $notificationMessage ??= $assistant->delayedShipmentNotice();
                            $delivery = DelayAlertDelivery::firstOrCreate(
                                $attributes,
                                [
                                    'audience' => $destination['audience'],
                                    'destination' => $destination['stored_destination'],
                                    'message' => $notificationMessage,
                                    'status' => DelayAlertDelivery::STATUS_PENDING,
                                ]
                            );
                        }

                        if ($delivery->wasRecentlyCreated) {
                            $summary['created']++;
                        }

                        if ($delivery->status === DelayAlertDelivery::STATUS_SENT) {
                            $summary['already_sent']++;

                            continue;
                        }

                        if ($delivery->status === DelayAlertDelivery::STATUS_CANCELLED) {
                            $summary['cancelled']++;

                            continue;
                        }

                        $processingTimeout = max(1, (int) config('delay-alerts.processing_timeout_minutes', 5));
                        $isProcessing = $delivery->status === DelayAlertDelivery::STATUS_PROCESSING
                            && $delivery->last_attempt_at?->gt(now()->subMinutes($processingTimeout));

                        if ($isProcessing) {
                            $summary['deferred']++;

                            continue;
                        }

                        $maxAttempts = max(1, (int) config('delay-alerts.max_attempts', 5));

                        if ($delivery->attempts >= $maxAttempts) {
                            if ($delivery->status === DelayAlertDelivery::STATUS_PROCESSING) {
                                $delivery->forceFill([
                                    'status' => DelayAlertDelivery::STATUS_FAILED,
                                    'processing_token' => null,
                                    'last_error' => 'Batas maksimum percobaan pengiriman telah tercapai.',
                                ])->save();
                            }

                            if (! $this->option('retry-failed')) {
                                $summary['exhausted']++;

                                continue;
                            }

                            $delivery->forceFill([
                                'status' => DelayAlertDelivery::STATUS_PENDING,
                                'processing_token' => null,
                                'attempts' => 0,
                                'last_attempt_at' => null,
                                'last_error' => null,
                            ])->save();
                            $summary['reset']++;
                        }

                        $retryAfter = max(1, (int) config('delay-alerts.retry_after_minutes', 60));
                        $isCoolingDown = $delivery->status === DelayAlertDelivery::STATUS_FAILED
                            && $delivery->last_attempt_at?->gt(now()->subMinutes($retryAfter));

                        if ($isCoolingDown) {
                            $summary['deferred']++;

                            continue;
                        }

                        try {
                            if ($this->option('sync')) {
                                SendDelayAlert::dispatchSync($delivery->id);
                            } else {
                                SendDelayAlert::dispatch($delivery->id);
                            }

                            $summary['dispatched']++;
                        } catch (Throwable $exception) {
                            report($exception);
                            $summary['failed']++;
                            $this->components->error(
                                $shipment->booking_number.' ke '.$destination['label'].' gagal dikirim.'
                            );
                        }
                    }
                }
            });

        $this->newLine();
        $this->table(
            ['Pengiriman terlambat', 'Tujuan', 'Delivery baru', 'Permintaan proses', 'Sudah terkirim', 'Dibatalkan', 'Ditunda', 'Direset', 'Batas retry', 'Tanpa tujuan', 'Gagal'],
            [[
                $summary['shipments'],
                $summary['destinations'],
                $summary['created'],
                $summary['dispatched'],
                $summary['already_sent'],
                $summary['cancelled'],
                $summary['deferred'],
                $summary['reset'],
                $summary['exhausted'],
                $summary['without_destination'],
                $summary['failed'],
            ]]
        );

        if ($this->option('dry-run')) {
            $this->components->info('Dry run selesai; tidak ada data atau notifikasi yang dibuat.');
        } elseif ($summary['failed'] === 0) {
            $this->components->info('Pemeriksaan keterlambatan selesai.');
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
