<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tracking_can_fetch_a_shipment_without_exposing_customer_data(): void
    {
        $this->seed();

        $response = $this->getJson('/api/shipments/TANTO-CT-000124')
            ->assertOk()
            ->assertJsonPath('booking_number', 'BOOK-2026-000124')
            ->assertJsonPath('container.container_number', 'TANTO-CT-000124')
            ->assertJsonPath('status', 'Dalam perjalanan')
            ->assertJsonMissingPath('customer')
            ->assertJsonMissingPath('customer_id');

        $responseBody = $response->getContent();

        $this->assertStringNotContainsString('PT Nusantara Retail', $responseBody);
        $this->assertStringNotContainsString('pelanggan@logitrack.test', $responseBody);
        $this->assertStringNotContainsString('0812-9900-1122', $responseBody);
        $this->assertStringNotContainsString('Jl. Veteran No. 10, Surabaya', $responseBody);
    }

    public function test_guest_cannot_access_protected_shipment_api_endpoints(): void
    {
        $this->seed();
        $shipment = Shipment::where('booking_number', 'BOOK-2026-000124')->firstOrFail();

        $this->getJson('/api/shipments')->assertUnauthorized();
        $this->postJson('/api/shipments')->assertUnauthorized();
        $this->putJson("/api/shipments/{$shipment->id}/status", [
            'status' => 'Tiba di pelabuhan tujuan',
            'location' => 'Makassar',
            'expected_version' => $shipment->operational_version,
        ])->assertUnauthorized();
    }

    public function test_customer_cannot_access_protected_shipment_api_endpoints(): void
    {
        $this->seed();
        $shipment = Shipment::where('booking_number', 'BOOK-2026-000124')->firstOrFail();

        $this->withBasicAuth('pelanggan@logitrack.test', 'password')
            ->getJson('/api/shipments')
            ->assertForbidden();

        $this->withBasicAuth('pelanggan@logitrack.test', 'password')
            ->postJson('/api/shipments')
            ->assertForbidden();

        $this->withBasicAuth('pelanggan@logitrack.test', 'password')
            ->putJson("/api/shipments/{$shipment->id}/status", [
                'status' => 'Tiba di pelabuhan tujuan',
                'location' => 'Makassar',
                'expected_version' => $shipment->operational_version,
            ])
            ->assertForbidden();
    }

    public function test_admin_and_operator_can_update_status_with_the_current_version(): void
    {
        $this->seed();
        $shipment = Shipment::where('booking_number', 'BOOK-2026-000124')->firstOrFail();
        $originalVersion = $shipment->operational_version;
        $originalHistoryCount = $shipment->histories()->count();

        $this->withBasicAuth('admin@logitrack.test', 'password')
            ->putJson("/api/shipments/{$shipment->id}/status", [
                'status' => 'Menunggu keberangkatan',
                'location' => 'Surabaya',
                'description' => 'Status diperbarui oleh admin melalui API.',
                'expected_version' => $originalVersion,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Menunggu keberangkatan')
            ->assertJsonPath('operational_version', $originalVersion + 1);

        $shipment->refresh();
        $this->assertSame($originalVersion + 1, $shipment->operational_version);

        $this->withBasicAuth('operator@logitrack.test', 'password')
            ->putJson("/api/shipments/{$shipment->id}/status", [
                'status' => 'Dalam perjalanan',
                'location' => 'Laut Jawa',
                'description' => 'Status diperbarui oleh operator melalui API.',
                'expected_version' => $shipment->operational_version,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Dalam perjalanan')
            ->assertJsonPath('operational_version', $originalVersion + 2);

        $shipment->refresh();

        $this->assertSame('Dalam perjalanan', $shipment->status);
        $this->assertSame($originalVersion + 2, $shipment->operational_version);
        $this->assertSame($originalHistoryCount + 2, $shipment->histories()->count());
        $this->assertDatabaseHas('shipment_histories', [
            'shipment_id' => $shipment->id,
            'status' => 'Menunggu keberangkatan',
            'location' => 'Surabaya',
        ]);
        $this->assertDatabaseHas('shipment_histories', [
            'shipment_id' => $shipment->id,
            'status' => 'Dalam perjalanan',
            'location' => 'Laut Jawa',
        ]);
    }

    public function test_stale_expected_version_is_rejected_without_mutating_shipment_or_history(): void
    {
        $this->seed();
        $shipment = Shipment::where('booking_number', 'BOOK-2026-000124')->firstOrFail();
        $staleVersion = $shipment->operational_version;

        $this->withBasicAuth('admin@logitrack.test', 'password')
            ->putJson("/api/shipments/{$shipment->id}/status", [
                'status' => 'Menunggu keberangkatan',
                'location' => 'Surabaya',
                'description' => 'Pembaruan pertama yang valid.',
                'expected_version' => $staleVersion,
            ])
            ->assertOk();

        $shipment->refresh();
        $historyCountAfterValidUpdate = $shipment->histories()->count();
        $unchangedShipment = $shipment->only([
            'status',
            'latest_status_at',
            'delay_reported_at',
            'actual_arrival',
            'operational_version',
        ]);

        $this->withBasicAuth('operator@logitrack.test', 'password')
            ->putJson("/api/shipments/{$shipment->id}/status", [
                'status' => 'Tiba di pelabuhan tujuan',
                'location' => 'Lokasi dari permintaan basi',
                'description' => 'Histori ini tidak boleh tersimpan.',
                'expected_version' => $staleVersion,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_version');

        $shipment->refresh();

        $this->assertEquals($unchangedShipment, $shipment->only(array_keys($unchangedShipment)));
        $this->assertSame($historyCountAfterValidUpdate, $shipment->histories()->count());
        $this->assertDatabaseMissing('shipment_histories', [
            'shipment_id' => $shipment->id,
            'location' => 'Lokasi dari permintaan basi',
            'description' => 'Histori ini tidak boleh tersimpan.',
        ]);
    }
}
