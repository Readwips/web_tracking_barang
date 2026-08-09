<?php

namespace Tests\Feature\DelayAlerts;

use App\Models\Shipment;

class TrackingPageTest extends DelayAlertTestCase
{
    public function test_tracking_index_renders_an_accessible_search_experience(): void
    {
        $this->get(route('tracking.index'))
            ->assertOk()
            ->assertSee('Lacak perjalanan pengiriman Anda')
            ->assertSee('action="'.route('tracking.search').'"', false)
            ->assertSee('required', false)
            ->assertSee('aria-invalid="false"', false)
            ->assertSee('TANTO-CT-000124')
            ->assertSee('TANTO-CT-000125')
            ->assertDontSee('data-shipment-summary', false);
    }

    public function test_tracking_validation_is_connected_to_the_search_input(): void
    {
        $this->from(route('tracking.index'))
            ->followingRedirects()
            ->post(route('tracking.search'), [])
            ->assertOk()
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="container-number-error"', false)
            ->assertSee('id="container-number-error"', false);
    }

    public function test_tracking_search_and_direct_url_normalize_whitespace_and_case(): void
    {
        $this->post(route('tracking.search'), [
            'container_number' => '  tanto-ct-000124  ',
        ])->assertRedirect(route('tracking.show', 'TANTO-CT-000124'));

        $this->get(route('tracking.show', 'tanto-ct-000124'))
            ->assertOk()
            ->assertSee('BOOK-2026-000124')
            ->assertSee('TANTO-CT-000124');
    }

    public function test_tracking_result_shows_summary_timeline_and_details_without_customer_contacts(): void
    {
        $shipment = Shipment::query()
            ->with(['customer.user', 'container', 'vessel', 'originPort', 'destinationPort'])
            ->where('booking_number', 'BOOK-2026-000124')
            ->firstOrFail();

        $this->get(route('tracking.show', $shipment->container->container_number))
            ->assertOk()
            ->assertSee('data-shipment-summary', false)
            ->assertSee('data-tracking-timeline', false)
            ->assertSee($shipment->booking_number)
            ->assertSee($shipment->container->container_number)
            ->assertSee($shipment->vessel->name)
            ->assertSee($shipment->originPort->city)
            ->assertSee($shipment->destinationPort->city)
            ->assertSee($shipment->estimated_arrival->translatedFormat('d F Y'))
            ->assertSee('datetime="', false)
            ->assertSeeInOrder([
                'Kontainer diterima',
                'Dimuat ke kapal',
                'Dalam perjalanan',
                'Tiba di pelabuhan',
            ])
            ->assertDontSee($shipment->customer->phone)
            ->assertDontSee($shipment->customer->address)
            ->assertDontSee($shipment->customer->user->email);
    }

    public function test_tracking_result_handles_an_empty_timeline(): void
    {
        $shipment = Shipment::query()
            ->with('container')
            ->where('booking_number', 'BOOK-2026-000125')
            ->firstOrFail();

        $this->get(route('tracking.show', $shipment->container->container_number))
            ->assertOk()
            ->assertSee('Belum ada pembaruan perjalanan')
            ->assertSee('data-delay-alert', false);
    }

    public function test_tracking_result_displays_a_safe_not_found_state(): void
    {
        $this->get(route('tracking.show', 'UNKNOWN-CONTAINER'))
            ->assertOk()
            ->assertSee('Kontainer tidak ditemukan')
            ->assertSee('UNKNOWN-CONTAINER')
            ->assertDontSee('data-shipment-summary', false);
    }

    public function test_completed_shipment_shows_actual_arrival_without_a_delay_alert(): void
    {
        $shipment = Shipment::query()
            ->with('container')
            ->where('booking_number', 'BOOK-2026-000126')
            ->firstOrFail();

        $this->get(route('tracking.show', $shipment->container->container_number))
            ->assertOk()
            ->assertSee('Selesai')
            ->assertSee('Kedatangan aktual')
            ->assertSee($shipment->actual_arrival->translatedFormat('d F Y'))
            ->assertDontSee('data-delay-alert', false);
    }

    public function test_arrived_status_without_actual_date_is_explained_without_reusing_the_eta(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'status' => 'Tiba di pelabuhan tujuan',
            'actual_arrival' => null,
        ])->save();

        $this->get(route('tracking.show', $shipment->container->container_number))
            ->assertOk()
            ->assertSee('data-arrival-state="arrived-date-missing"', false)
            ->assertSee('Status kedatangan tercatat')
            ->assertSee('Tanggal aktual belum tersedia')
            ->assertSee('tanggal kedatangan aktual belum dicatat')
            ->assertDontSee('Perkiraan berdasarkan jadwal pengiriman saat ini.')
            ->assertDontSee('data-delay-alert', false);
    }

    public function test_actual_arrival_with_an_active_status_explains_the_pending_status_update(): void
    {
        $shipment = Shipment::query()
            ->with('container')
            ->where('booking_number', 'BOOK-2026-000126')
            ->firstOrFail();

        $shipment->forceFill(['status' => 'Dalam perjalanan'])->save();

        $this->get(route('tracking.show', $shipment->container->container_number))
            ->assertOk()
            ->assertSee('data-arrival-state="actual-status-pending"', false)
            ->assertSee('Kedatangan tercatat')
            ->assertSee($shipment->actual_arrival->translatedFormat('d F Y'))
            ->assertSee('status pengiriman masih')
            ->assertSee('Dalam perjalanan')
            ->assertDontSee('data-delay-alert', false);
    }

    public function test_timeline_marks_only_one_actual_entry_as_current_and_labels_future_entries(): void
    {
        $shipment = $this->customerShipment();
        $shipment->histories()->create([
            'status' => 'Dalam perjalanan',
            'location' => 'Selat Makassar',
            'description' => 'Posisi kapal diperbarui.',
        ]);
        $shipment->forceFill(['latest_status_at' => now()])->save();

        $response = $this->get(route('tracking.show', $shipment->container->container_number));
        $content = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('Riwayat &amp; estimasi', false)
            ->assertSee('Pembaruan terakhir')
            ->assertSee('Estimasi');

        $this->assertSame(1, substr_count($content, 'aria-current="step"'));
        $this->assertSame(1, substr_count($content, 'Pembaruan terakhir'));
        $this->assertMatchesRegularExpression(
            '/<li(?![^>]*aria-current)[^>]*>.*?Tiba di pelabuhan.*?Estimasi.*?<\/li>/s',
            $content,
        );
    }

    public function test_long_public_tracking_values_are_rendered_with_overflow_protection(): void
    {
        $shipment = $this->customerShipment();
        $bookingNumber = str_repeat('B', 50);
        $vesselName = str_repeat('V', 255);
        $historyDescription = 'https://example.test/'.str_repeat('x', 600);

        $shipment->forceFill(['booking_number' => $bookingNumber])->save();
        $shipment->vessel->forceFill(['name' => $vesselName])->save();
        $shipment->timeline()->firstOrFail()->forceFill(['description' => $historyDescription])->save();

        $response = $this->get(route('tracking.show', $shipment->container->container_number));

        $response
            ->assertOk()
            ->assertSee($bookingNumber)
            ->assertSee($vesselName)
            ->assertSee($historyDescription)
            ->assertSee('[overflow-wrap:anywhere]', false);

        $this->assertGreaterThanOrEqual(3, substr_count($response->getContent(), '[overflow-wrap:anywhere]'));
    }
}
