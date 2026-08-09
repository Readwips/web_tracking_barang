<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipmentQuickActionRequest;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentQuickActionController extends Controller
{
    public function __invoke(ShipmentQuickActionRequest $request, Shipment $shipment): RedirectResponse
    {
        $validated = $request->validated();
        $expectedVersion = (int) $validated['expected_version'];

        $message = DB::transaction(function () use ($shipment, $validated, $expectedVersion): string {
            $currentShipment = Shipment::query()
                ->with(['container', 'originPort', 'destinationPort'])
                ->findOrFail($shipment->id);

            if ($currentShipment->operational_version !== $expectedVersion) {
                $this->fail('Pengiriman telah diperbarui oleh petugas lain. Muat ulang halaman sebelum melanjutkan.');
            }

            return match ($validated['action']) {
                'report_delay' => $this->reportDelay($currentShipment, $expectedVersion),
                'clear_delay' => $this->clearDelay($currentShipment, $expectedVersion),
                'arrived' => $this->markArrived($currentShipment, $validated, $expectedVersion),
                'update' => $this->addUpdate($currentShipment, $validated, $expectedVersion),
            };
        });

        return redirect()
            ->route('shipments.edit', $shipment)
            ->with('status', $message);
    }

    private function reportDelay(Shipment $shipment, int $expectedVersion): string
    {
        if ($shipment->actual_arrival || in_array($shipment->status, Shipment::ARRIVED_STATUSES, true)) {
            $this->fail('Pengiriman yang sudah tiba tidak dapat ditandai terlambat.');
        }

        if ($shipment->delay_reported_at) {
            $this->fail('Keterlambatan pengiriman ini sudah dilaporkan.');
        }

        $this->updateShipment($shipment, $expectedVersion, [
            'delay_reported_at' => now(),
            'delay_report_sequence' => DB::raw('delay_report_sequence + 1'),
            'latest_status_at' => now(),
        ]);

        $this->createHistory(
            $shipment,
            $this->defaultLocation($shipment),
            'Pengiriman ditandai mengalami keterlambatan. Tim operasional sedang melakukan pemantauan.',
        );

        return 'Keterlambatan berhasil dilaporkan. Tracking publik langsung diperbarui dan notifikasi akan diproses oleh scheduler.';
    }

    private function clearDelay(Shipment $shipment, int $expectedVersion): string
    {
        if (! $shipment->delay_reported_at) {
            return 'Tidak ada laporan keterlambatan manual yang perlu dihapus.';
        }

        $attributes = [
            'delay_reported_at' => null,
            'latest_status_at' => now(),
        ];

        if ($this->changesDelayState($shipment, $attributes)) {
            $attributes['delay_report_sequence'] = DB::raw('delay_report_sequence + 1');
        }

        $this->updateShipment($shipment, $expectedVersion, $attributes);

        $this->createHistory(
            $shipment,
            $this->defaultLocation($shipment),
            'Laporan keterlambatan manual telah ditutup setelah pembaruan operasional.',
        );

        return $shipment->fresh()->isDelayed()
            ? 'Laporan manual dihapus, tetapi pengiriman tetap terlambat karena ETA sudah terlewati.'
            : 'Laporan keterlambatan manual berhasil dihapus.';
    }

    private function markArrived(Shipment $shipment, array $validated, int $expectedVersion): string
    {
        if ($shipment->status === 'Selesai') {
            $this->fail('Pengiriman yang sudah selesai tidak dapat diubah melalui aksi cepat.');
        }

        if ($shipment->actual_arrival) {
            $this->fail('Kedatangan sudah tercatat. Gunakan Detail lanjutan untuk melakukan koreksi.');
        }

        $attributes = [
            'status' => 'Tiba di pelabuhan tujuan',
            'actual_arrival' => $validated['actual_arrival'],
            'delay_reported_at' => null,
            'latest_status_at' => now(),
        ];

        if ($this->changesDelayState($shipment, $attributes)) {
            $attributes['delay_report_sequence'] = DB::raw('delay_report_sequence + 1');
        }

        $this->updateShipment($shipment, $expectedVersion, $attributes);

        $this->createHistory(
            $shipment,
            $shipment->destinationPort->city,
            'Pengiriman telah tiba di pelabuhan tujuan.',
        );

        $shipment->container->update(['status' => 'in_use']);

        return 'Kedatangan berhasil dicatat dan kondisi keterlambatan telah ditutup.';
    }

    private function addUpdate(Shipment $shipment, array $validated, int $expectedVersion): string
    {
        $this->updateShipment($shipment, $expectedVersion, ['latest_status_at' => now()]);

        $this->createHistory(
            $shipment,
            ($validated['location'] ?? null) ?: $this->defaultLocation($shipment),
            ($validated['description'] ?? null) ?: 'Pembaruan operasional dicatat.',
        );

        return 'Pembaruan tracking berhasil ditambahkan.';
    }

    private function updateShipment(Shipment $shipment, int $expectedVersion, array $attributes): void
    {
        $updated = Shipment::query()
            ->whereKey($shipment->id)
            ->where('operational_version', $expectedVersion)
            ->update(array_merge($attributes, [
                'operational_version' => DB::raw('operational_version + 1'),
                'updated_at' => now(),
            ]));

        if ($updated !== 1) {
            $this->fail('Pengiriman telah diperbarui oleh petugas lain. Muat ulang halaman sebelum melanjutkan.');
        }

        $shipment->refresh();
        $shipment->loadMissing(['container', 'originPort', 'destinationPort']);
    }

    private function changesDelayState(Shipment $shipment, array $attributes): bool
    {
        $wasDelayed = $shipment->isDelayed();
        $projectedShipment = clone $shipment;
        $projectedShipment->forceFill($attributes);

        return $wasDelayed !== $projectedShipment->isDelayed();
    }

    private function createHistory(Shipment $shipment, string $location, string $description): void
    {
        ShipmentHistory::create([
            'shipment_id' => $shipment->id,
            'status' => $shipment->status,
            'location' => $location,
            'description' => $description,
        ]);
    }

    private function defaultLocation(Shipment $shipment): string
    {
        return match ($shipment->status) {
            'Dalam perjalanan' => 'Dalam perjalanan',
            'Tiba di pelabuhan tujuan', 'Selesai' => $shipment->destinationPort->city,
            default => $shipment->originPort->city,
        };
    }

    private function fail(string $message): never
    {
        $exception = ValidationException::withMessages(['action' => $message]);
        $exception->errorBag = 'quickAction';

        throw $exception;
    }
}
