<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->with(['customer', 'container', 'cargoType', 'vessel', 'originPort', 'destinationPort', 'timeline'])
            ->where('booking_number', $tracking_number)
            ->orWhereHas('container', fn ($query) => $query->where('container_number', $tracking_number))
            ->firstOrFail();

        return response()->json($shipment);
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
        ]);

        $shipment->update([
            'status' => $validated['status'],
            'latest_status_at' => now(),
        ]);
        ShipmentHistory::create([
            'shipment_id' => $shipment->id,
            'status' => $validated['status'],
            'location' => $validated['location'],
            'description' => $validated['description'] ?? null,
        ]);

        $shipment->container->update(['status' => $validated['status'] === 'Selesai' ? 'available' : 'in_use']);

        return response()->json($shipment->load(['customer', 'container', 'timeline']));
    }
}
