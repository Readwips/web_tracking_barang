<?php

namespace Tests\Feature\DelayAlerts;

use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class DelayAlertTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-08 12:00:00', 'Asia/Jakarta'));
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    protected function customerShipment(): Shipment
    {
        return Shipment::query()
            ->where('booking_number', 'BOOK-2026-000124')
            ->firstOrFail();
    }

    protected function makeOnlyCustomerShipmentDelayed(int $daysLate = 1): Shipment
    {
        Shipment::query()->update([
            'actual_arrival' => today()->toDateString(),
            'status' => 'Selesai',
        ]);

        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'actual_arrival' => null,
            'estimated_arrival' => today()->subDays($daysLate)->toDateString(),
            'status' => 'Dalam perjalanan',
        ])->save();

        return $shipment->fresh();
    }
}
