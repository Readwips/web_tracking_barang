<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function index(AiAssistantService $assistant)
    {
        return view('ai.index', [
            'context' => "Kontainer TANTO-CT-000124 terlambat karena cuaca buruk.\nPerkiraan tiba mundur dua hari.",
            'notice' => null,
            'summary' => $assistant->operationalSummary($this->stats()),
        ]);
    }

    public function customerNotice(Request $request, AiAssistantService $assistant)
    {
        $validated = $request->validate([
            'context' => ['required', 'string', 'min:10'],
        ]);

        return view('ai.index', [
            'context' => $validated['context'],
            'notice' => $assistant->customerNotice($validated['context']),
            'summary' => $assistant->operationalSummary($this->stats()),
        ]);
    }

    public function operationalSummary(AiAssistantService $assistant)
    {
        return view('ai.index', [
            'context' => "Kontainer TANTO-CT-000124 terlambat karena cuaca buruk.\nPerkiraan tiba mundur dua hari.",
            'notice' => null,
            'summary' => $assistant->operationalSummary($this->stats()),
        ]);
    }

    private function stats(): array
    {
        $shipments = Shipment::with(['originPort', 'destinationPort'])->get();
        $routeCounts = $shipments
            ->groupBy(fn (Shipment $shipment) => $shipment->originPort->city.' - '.$shipment->destinationPort->city)
            ->map->count()
            ->sortDesc();

        return [
            'active' => $shipments->where('status', '!=', 'Selesai')->count(),
            'completed' => $shipments->where('status', 'Selesai')->count(),
            'delayed' => $shipments
                ->where('status', '!=', 'Selesai')
                ->filter(fn (Shipment $shipment) => $shipment->estimated_arrival->isPast())
                ->count(),
            'stale' => $shipments
                ->where('status', '!=', 'Selesai')
                ->filter(fn (Shipment $shipment) => $shipment->latest_status_at?->lt(now()->subDay()))
                ->count(),
            'top_route' => $routeCounts->keys()->first(),
        ];
    }
}
