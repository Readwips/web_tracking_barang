<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function index()
    {
        return Shipment::query()
            ->with(['customer', 'container', 'vessel', 'originPort', 'destinationPort'])
            ->latest()
            ->paginate(15);
    }

    public function show(string $tracking_number)
    {
        $shipment = Shipment::query()
            ->with(['container', 'vessel', 'originPort', 'destinationPort', 'timeline'])
            ->where('booking_number', $tracking_number)
            ->orWhereHas('container', fn ($query) => $query->where('container_number', $tracking_number))
            ->firstOrFail();

        return response()->json([
            'booking_number' => $shipment->booking_number,
            'container' => [
                'container_number' => $shipment->container->container_number,
                'container_type' => $shipment->container->container_type,
            ],
            'vessel' => [
                'name' => $shipment->vessel->name,
            ],
            'origin_port' => [
                'city' => $shipment->originPort->city,
                'name' => $shipment->originPort->name,
            ],
            'destination_port' => [
                'city' => $shipment->destinationPort->city,
                'name' => $shipment->destinationPort->name,
            ],
            'departure_date' => $shipment->departure_date->toDateString(),
            'estimated_arrival' => $shipment->estimated_arrival->toDateString(),
            'actual_arrival' => $shipment->actual_arrival?->toDateString(),
            'status' => $shipment->status,
            'is_delayed' => $shipment->isDelayed(),
            'timeline' => $shipment->timeline->map(fn (ShipmentHistory $history): array => [
                'status' => $history->status,
                'location' => $history->location,
                'description' => $history->description,
                'created_at' => $history->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_number' => ['required', 'string', 'max:50', Rule::unique('shipments', 'booking_number')],
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
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $history = [
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
        unset($validated['location'], $validated['description']);

        $shipment = Shipment::create($validated + ['latest_status_at' => now()]);
        $shipment->load(['originPort', 'container']);
        ShipmentHistory::create([
            'shipment_id' => $shipment->id,
            'status' => $shipment->status,
            'location' => $history['location'] ?: $shipment->originPort->city,
            'description' => $history['description'] ?: 'Booking pengiriman dibuat melalui API.',
        ]);
        $shipment->container->update(['status' => $shipment->status === 'Booking dibuat' ? 'booked' : 'in_use']);

        return response()->json($shipment->load(['customer', 'container', 'timeline']), 201);
    }

    public function updateStatus(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Shipment::STATUSES)],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $expectedVersion = (int) $validated['expected_version'];

        return DB::transaction(function () use ($shipment, $validated, $expectedVersion) {
            $currentShipment = Shipment::query()
                ->with('container')
                ->findOrFail($shipment->id);

            if ($currentShipment->operational_version !== $expectedVersion) {
                $this->failStaleUpdate();
            }

            $payload = [
                'status' => $validated['status'],
                'latest_status_at' => now(),
            ];

            if (in_array($validated['status'], Shipment::ARRIVED_STATUSES, true)) {
                $payload['delay_reported_at'] = null;
            }

            $projectedShipment = clone $currentShipment;
            $projectedShipment->forceFill($payload);

            if ($currentShipment->isDelayed() !== $projectedShipment->isDelayed()) {
                $payload['delay_report_sequence'] = DB::raw('delay_report_sequence + 1');
            }

            $updated = Shipment::query()
                ->whereKey($currentShipment->id)
                ->where('operational_version', $expectedVersion)
                ->update(array_merge($payload, [
                    'operational_version' => DB::raw('operational_version + 1'),
                    'updated_at' => now(),
                ]));

            if ($updated !== 1) {
                $this->failStaleUpdate();
            }

            $currentShipment->refresh();

            ShipmentHistory::create([
                'shipment_id' => $currentShipment->id,
                'status' => $validated['status'],
                'location' => $validated['location'],
                'description' => $validated['description'] ?? null,
            ]);

            $currentShipment->container->update([
                'status' => $validated['status'] === 'Selesai' ? 'available' : 'in_use',
            ]);

            return response()->json($currentShipment->load(['customer', 'container', 'timeline']));
        });
    }

    private function failStaleUpdate(): never
    {
        throw ValidationException::withMessages([
            'expected_version' => 'Data pengiriman sudah berubah. Ambil versi terbaru sebelum memperbarui status.',
        ]);
    }
}
