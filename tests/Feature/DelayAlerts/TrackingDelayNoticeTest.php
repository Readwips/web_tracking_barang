<?php

namespace Tests\Feature\DelayAlerts;

use App\Models\Shipment;

class TrackingDelayNoticeTest extends DelayAlertTestCase
{
    public function test_public_tracking_shows_a_factual_delay_notice_for_an_overdue_shipment(): void
    {
        $shipment = Shipment::query()
            ->with('container')
            ->where('booking_number', 'BOOK-2026-000125')
            ->firstOrFail();

        $this->assertTrue($shipment->isDelayed());

        $response = $this->get(route('tracking.show', $shipment->container->container_number));

        $response
            ->assertOk()
            ->assertSee('data-delay-alert', false)
            ->assertSee('Pemberitahuan keterlambatan')
            ->assertSee('Pengiriman mengalami keterlambatan')
            ->assertSee($shipment->estimated_arrival->translatedFormat('d F Y'))
            ->assertSee($shipment->daysLate().' hari')
            ->assertSee('Status terakhir:')
            ->assertSee($shipment->status);
    }

    public function test_public_tracking_does_not_show_a_delay_notice_before_eta_has_passed(): void
    {
        $shipment = Shipment::query()
            ->with('container')
            ->where('booking_number', 'BOOK-2026-000124')
            ->firstOrFail();

        $shipment->forceFill([
            'actual_arrival' => null,
            'estimated_arrival' => today()->toDateString(),
            'status' => 'Dalam perjalanan',
        ])->save();

        $response = $this->get(route('tracking.show', $shipment->container->container_number));

        $response
            ->assertOk()
            ->assertDontSee('data-delay-alert', false)
            ->assertDontSee('Pengiriman mengalami keterlambatan');

        $shipment = $shipment->fresh();

        $this->assertFalse($shipment->isDelayed());
        $this->assertSame(0, $shipment->daysLate());
    }
}
