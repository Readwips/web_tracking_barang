<?php

namespace App\Http\Controllers;

use App\Models\CargoType;
use App\Models\Container as ShippingContainer;
use App\Models\Customer;
use App\Models\Port;
use App\Models\Schedule;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\Vessel;
use App\Services\DelayAlertDestinationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $shipments = Shipment::query()
            ->visibleTo($request->user())
            ->with(['customer', 'container', 'vessel', 'originPort', 'destinationPort'])
            ->latest()
            ->paginate(10);

        return view('shipments.index', compact('shipments'));
    }

    public function create()
    {
        return view('shipments.form', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $history = $this->historyPayload($validated);
        $shipment = Shipment::create($this->shipmentPayload($validated) + ['latest_status_at' => now()]);

        ShipmentHistory::create([
            'shipment_id' => $shipment->id,
            'status' => $shipment->status,
            'location' => $history['location'] ?: $shipment->originPort->city,
            'description' => $history['description'] ?: 'Booking pengiriman dibuat.',
        ]);

        $shipment->container->update(['status' => $this->containerStatusFor($shipment->status)]);

        return redirect()->route('shipments.show', $shipment)->with('status', 'Pengiriman berhasil dibuat.');
    }

    public function show(Request $request, Shipment $shipment)
    {
        $this->ensureVisible($request, $shipment);

        $shipment->load(['customer', 'container', 'cargoType', 'vessel', 'originPort', 'destinationPort', 'timeline']);

        return view('shipments.show', compact('shipment'));
    }

    public function edit(
        Request $request,
        Shipment $shipment,
        DelayAlertDestinationResolver $destinationResolver,
    ) {
        $this->ensureVisible($request, $shipment);
        $shipment->load(['customer.user', 'container', 'vessel', 'originPort', 'destinationPort', 'schedule']);

        return view('shipments.form', $this->formData($shipment) + [
            'delayAlertDestinations' => array_values($destinationResolver->forShipment($shipment)),
        ]);
    }

    public function update(Request $request, Shipment $shipment)
    {
        $this->ensureVisible($request, $shipment);

        $validated = $request->validate($this->rules($shipment->id));
        $history = $this->historyPayload($validated);
        $payload = $this->shipmentPayload($validated);
        $expectedVersion = (int) $validated['expected_version'];

        if (($payload['actual_arrival'] ?? null) || in_array($payload['status'], Shipment::ARRIVED_STATUSES, true)) {
            $payload['delay_reported_at'] = null;
        }

        DB::transaction(function () use ($shipment, $payload, $history, $expectedVersion): void {
            $currentShipment = Shipment::query()
                ->with(['originPort', 'destinationPort', 'container'])
                ->findOrFail($shipment->id);

            if ($currentShipment->operational_version !== $expectedVersion) {
                $this->failStaleUpdate();
            }

            $createsHistory = $currentShipment->status !== $payload['status']
                || $history['location']
                || $history['description'];

            $attributes = $payload;
            $projectedShipment = clone $currentShipment;
            $projectedShipment->forceFill($payload);

            if ($currentShipment->isDelayed() !== $projectedShipment->isDelayed()) {
                $attributes['delay_report_sequence'] = DB::raw('delay_report_sequence + 1');
            }

            if ($createsHistory) {
                $attributes['latest_status_at'] = now();
            }

            $updated = Shipment::query()
                ->whereKey($currentShipment->id)
                ->where('operational_version', $expectedVersion)
                ->update(array_merge($attributes, [
                    'operational_version' => DB::raw('operational_version + 1'),
                    'updated_at' => now(),
                ]));

            if ($updated !== 1) {
                $this->failStaleUpdate();
            }

            $currentShipment->refresh();
            $currentShipment->loadMissing(['originPort', 'destinationPort', 'container']);

            if ($createsHistory) {
                ShipmentHistory::create([
                    'shipment_id' => $currentShipment->id,
                    'status' => $currentShipment->status,
                    'location' => $history['location'] ?: $this->defaultHistoryLocation($currentShipment),
                    'description' => $history['description'] ?: 'Status pengiriman diperbarui menjadi '.$currentShipment->status.'.',
                ]);
            }

            $currentShipment->container->update(['status' => $this->containerStatusFor($currentShipment->status)]);
        });

        return redirect()->route('shipments.show', $shipment)->with('status', 'Pengiriman berhasil diperbarui.');
    }

    public function destroy(Request $request, Shipment $shipment)
    {
        $this->ensureVisible($request, $shipment);
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);

        $deleted = Shipment::query()
            ->whereKey($shipment->id)
            ->where('operational_version', (int) $validated['expected_version'])
            ->delete();

        if ($deleted !== 1) {
            throw ValidationException::withMessages([
                'expected_version' => 'Pengiriman telah diperbarui oleh petugas lain. Muat ulang halaman sebelum menghapus.',
            ]);
        }

        return redirect()->route('shipments.index')->with('status', 'Pengiriman berhasil dihapus.');
    }

    private function formData(?Shipment $shipment = null): array
    {
        return [
            'shipment' => $shipment,
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'containers' => ShippingContainer::orderBy('container_number')->pluck('container_number', 'id'),
            'vessels' => Vessel::orderBy('name')->pluck('name', 'id'),
            'ports' => Port::orderBy('city')->get()->mapWithKeys(fn (Port $port) => [$port->id => $port->city.' - '.$port->name]),
            'cargoTypes' => CargoType::orderBy('name')->pluck('name', 'id'),
            'schedules' => Schedule::with(['vessel', 'originPort', 'destinationPort'])
                ->orderBy('departure_date')
                ->get()
                ->mapWithKeys(fn (Schedule $schedule) => [
                    $schedule->id => $schedule->departure_date->format('d M Y').' - '.$schedule->vessel->name.' ('.$schedule->originPort->city.' ke '.$schedule->destinationPort->city.')',
                ]),
            'statuses' => Shipment::STATUSES,
        ];
    }

    private function rules(?int $id = null): array
    {
        return [
            'booking_number' => ['required', 'string', 'max:50', Rule::unique('shipments', 'booking_number')->ignore($id)],
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'container_id' => ['required', Rule::exists('containers', 'id')],
            'cargo_type_id' => ['nullable', Rule::exists('cargo_types', 'id')],
            'vessel_id' => ['required', Rule::exists('vessels', 'id')],
            'origin_port_id' => ['required', Rule::exists('ports', 'id'), 'different:destination_port_id'],
            'destination_port_id' => ['required', Rule::exists('ports', 'id')],
            'schedule_id' => ['nullable', Rule::exists('schedules', 'id')],
            'departure_date' => ['required', 'date'],
            'estimated_arrival' => ['required', 'date', 'after_or_equal:departure_date'],
            'actual_arrival' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'status' => ['required', Rule::in(Shipment::STATUSES)],
            'history_location' => ['nullable', 'string', 'max:255'],
            'history_description' => ['nullable', 'string'],
            'expected_version' => $id === null
                ? ['exclude']
                : ['required', 'integer', 'min:0'],
        ];
    }

    private function shipmentPayload(array $validated): array
    {
        unset($validated['history_location'], $validated['history_description'], $validated['expected_version']);

        return $validated;
    }

    private function historyPayload(array $validated): array
    {
        return [
            'location' => $validated['history_location'] ?? null,
            'description' => $validated['history_description'] ?? null,
        ];
    }

    private function ensureVisible(Request $request, Shipment $shipment): void
    {
        abort_unless(
            Shipment::query()->visibleTo($request->user())->whereKey($shipment->id)->exists(),
            403
        );
    }

    private function defaultHistoryLocation(Shipment $shipment): string
    {
        return match ($shipment->status) {
            'Tiba di pelabuhan tujuan', 'Selesai' => $shipment->destinationPort->city,
            'Dalam perjalanan' => 'Dalam perjalanan',
            default => $shipment->originPort->city,
        };
    }

    private function containerStatusFor(string $shipmentStatus): string
    {
        return match ($shipmentStatus) {
            'Selesai' => 'available',
            'Booking dibuat' => 'booked',
            default => 'in_use',
        };
    }

    private function failStaleUpdate(): never
    {
        throw ValidationException::withMessages([
            'expected_version' => 'Pengiriman telah diperbarui oleh petugas lain. Muat ulang halaman sebelum menyimpan Detail lanjutan.',
        ]);
    }
}
