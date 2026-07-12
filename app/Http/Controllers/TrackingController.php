<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function index()
    {
        return view('tracking.index', ['shipment' => null, 'containerNumber' => null]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'container_number' => ['required', 'string', 'max:50'],
        ]);

        return redirect()->route('tracking.show', strtoupper($validated['container_number']));
    }

    public function show(string $containerNumber)
    {
        $shipment = Shipment::query()
            ->with(['customer', 'container', 'vessel', 'originPort', 'destinationPort', 'timeline'])
            ->whereHas('container', fn ($query) => $query->where('container_number', $containerNumber))
            ->latest()
            ->first();

        return view('tracking.index', compact('shipment', 'containerNumber'));
    }
}
