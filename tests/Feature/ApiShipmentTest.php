<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_shipment_by_container_number(): void
    {
        $this->seed();

        $this->getJson('/api/shipments/TANTO-CT-000124')
            ->assertOk()
            ->assertJsonPath('container.container_number', 'TANTO-CT-000124')
            ->assertJsonPath('status', 'Dalam perjalanan');
    }

    public function test_can_update_shipment_status(): void
    {
        $this->seed();
        $shipment = Shipment::where('booking_number', 'BOOK-2026-000124')->firstOrFail();

        $this->putJson("/api/shipments/{$shipment->id}/status", [
            'status' => 'Tiba di pelabuhan tujuan',
            'location' => 'Makassar',
            'description' => 'Kontainer tiba di Makassar New Port.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'Tiba di pelabuhan tujuan');

        $this->assertDatabaseHas('shipment_histories', [
            'shipment_id' => $shipment->id,
            'status' => 'Tiba di pelabuhan tujuan',
            'location' => 'Makassar',
        ]);
    }
}
