<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Shipment;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AiAssistantService $assistant)
    {
        $shipments = Shipment::query()
            ->visibleTo($request->user())
            ->with(['originPort', 'destinationPort'])
            ->get();

        $active = $shipments->where('status', '!=', 'Selesai')->count();
        $completed = $shipments->where('status', 'Selesai')->count();
        $delayed = $shipments
            ->filter(fn (Shipment $shipment) => $shipment->isDelayed())
            ->count();
        $stale = $shipments
            ->where('status', '!=', 'Selesai')
            ->filter(fn (Shipment $shipment) => $shipment->latest_status_at?->lt(now()->subDay()))
            ->count();

        $routeCounts = $shipments
            ->groupBy(fn (Shipment $shipment) => $shipment->originPort->city.' - '.$shipment->destinationPort->city)
            ->map->count()
            ->sortDesc();

        $monthlyShipments = $this->monthlyShipmentCounts($request);

        return view('dashboard', [
            'activeShipments' => $active,
            'completedShipments' => $completed,
            'delayedShipments' => $delayed,
            'customerCount' => $request->user()->hasRole('customer') ? 1 : Customer::count(),
            'routeCounts' => $routeCounts,
            'monthlyLabels' => $monthlyShipments->keys()->values(),
            'monthlyValues' => $monthlyShipments->values(),
            'recentShipments' => $shipments->sortByDesc('updated_at')->take(6),
            'operationalSummary' => $assistant->operationalSummary([
                'active' => $active,
                'completed' => $completed,
                'delayed' => $delayed,
                'stale' => $stale,
                'top_route' => $routeCounts->keys()->first(),
            ]),
        ]);
    }

    private function monthlyShipmentCounts(Request $request): Collection
    {
        $start = now()->startOfMonth()->subMonths(5);
        $labels = collect(range(0, 5))
            ->map(fn (int $offset) => $start->copy()->addMonths($offset))
            ->mapWithKeys(fn (Carbon $month) => [$month->format('M Y') => 0]);

        $data = Shipment::query()
            ->visibleTo($request->user())
            ->whereDate('departure_date', '>=', $start)
            ->get()
            ->groupBy(fn (Shipment $shipment) => $shipment->departure_date->format('M Y'))
            ->map->count();

        return $labels->merge($data);
    }
}
