<?php

namespace Tests\Feature\DelayAlerts;

use App\Models\Shipment;
use PHPUnit\Framework\Attributes\DataProvider;

class ShipmentDelayDetectionTest extends DelayAlertTestCase
{
    #[DataProvider('delayBoundaryCases')]
    public function test_delayed_scope_and_model_predicate_observe_arrival_boundaries(
        int $etaOffset,
        string $status,
        ?int $actualArrivalOffset,
        bool $expectedDelayed,
    ): void {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'estimated_arrival' => today()->addDays($etaOffset)->toDateString(),
            'actual_arrival' => $actualArrivalOffset === null
                ? null
                : today()->addDays($actualArrivalOffset)->toDateString(),
            'status' => $status,
        ])->save();

        $shipment = $shipment->fresh();

        $this->assertSame($expectedDelayed, $shipment->isDelayed());
        $this->assertSame(
            $expectedDelayed,
            Shipment::query()->delayed()->whereKey($shipment->id)->exists(),
        );
    }

    public static function delayBoundaryCases(): array
    {
        return [
            'ETA yesterday is delayed' => [-1, 'Dalam perjalanan', null, true],
            'ETA today is not delayed yet' => [0, 'Dalam perjalanan', null, false],
            'arrived status is not delayed' => [-1, 'Tiba di pelabuhan tujuan', null, false],
            'completed status is not delayed' => [-1, 'Selesai', null, false],
            'actual arrival excludes the shipment' => [-1, 'Dalam perjalanan', 0, false],
        ];
    }
}
