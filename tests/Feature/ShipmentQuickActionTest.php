<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\DelayAlerts\DelayAlertTestCase;

class ShipmentQuickActionTest extends DelayAlertTestCase
{
    #[DataProvider('operationalRoles')]
    public function test_admin_and_operator_can_report_a_delay_without_rewriting_shipment_facts(string $role): void
    {
        $shipment = $this->customerShipment()->fresh();
        $originalEta = $shipment->estimated_arrival->toDateString();
        $originalStatus = $shipment->status;
        $originalActualArrival = $shipment->actual_arrival;
        $originalVersion = $shipment->operational_version;
        $originalDelaySequence = $shipment->delay_report_sequence;
        $historyCount = $shipment->histories()->count();

        $response = $this
            ->actingAs($this->userWithRole($role))
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'report_delay',
                'location' => 'Lokasi yang tidak terverifikasi',
                'description' => 'Telat gara-gara pihak tertentu.',
                'estimated_arrival' => today()->subYear()->toDateString(),
                'actual_arrival' => today()->toDateString(),
                'status' => 'Selesai',
            ]));

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh();

        $this->assertNotNull($shipment->delay_reported_at);
        $this->assertSame($originalEta, $shipment->estimated_arrival->toDateString());
        $this->assertSame($originalStatus, $shipment->status);
        $this->assertSame($originalActualArrival, $shipment->actual_arrival);
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($originalDelaySequence + 1, $shipment->delay_report_sequence);
        $this->assertTrue($shipment->isDelayed());
        $this->assertTrue(Shipment::query()->delayed()->whereKey($shipment)->exists());
        $this->assertSame($historyCount + 1, $shipment->histories()->count());

        $history = $shipment->histories()->reorder()->latest('id')->firstOrFail();

        $this->assertSame($originalStatus, $history->status);
        $this->assertSame('Dalam perjalanan', $history->location);
        $this->assertMatchesRegularExpression('/terlambat|keterlambatan/i', (string) $history->description);
        $this->assertMatchesRegularExpression('/operasional|pemantauan|ditindaklanjuti/i', (string) $history->description);
        $this->assertDoesNotMatchRegularExpression('/gara(?:-gara)?|kesalahan|disebabkan oleh|karena\s+[A-Z]/i', (string) $history->description);
    }

    public function test_clearing_a_manual_delay_removes_the_flag_for_a_future_eta(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'delay_reported_at' => now()->subHour(),
            'estimated_arrival' => today()->addDays(2),
            'actual_arrival' => null,
            'status' => 'Dalam perjalanan',
        ])->save();
        $shipment->refresh();
        $originalVersion = $shipment->operational_version;
        $originalDelaySequence = $shipment->delay_report_sequence;

        $this->assertTrue($shipment->isDelayed());

        $this
            ->actingAs($this->userWithRole('admin'))
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'clear_delay',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh();

        $this->assertNull($shipment->delay_reported_at);
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($originalDelaySequence + 1, $shipment->delay_report_sequence);
        $this->assertFalse($shipment->isDelayed());
        $this->assertFalse(Shipment::query()->delayed()->whereKey($shipment)->exists());
    }

    public function test_public_tracking_distinguishes_a_manual_future_eta_delay_from_an_overdue_eta(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'delay_reported_at' => now(),
            'estimated_arrival' => today()->addDays(2),
            'actual_arrival' => null,
            'status' => 'Dalam perjalanan',
        ])->save();
        $shipment->refresh()->load('container');

        $this->assertTrue($shipment->isDelayed());
        $this->assertSame(0, $shipment->daysLate());

        $this
            ->get(route('tracking.show', $shipment->container->container_number))
            ->assertOk()
            ->assertSee('data-delay-alert', false)
            ->assertSee('Keterlambatan dilaporkan')
            ->assertSee('Dilaporkan sebelum ETA terlewati')
            ->assertSee($shipment->estimated_arrival->translatedFormat('d F Y'))
            ->assertDontSee('>ETA terlewati<', false)
            ->assertDontSee('hari melewati estimasi');
    }

    public function test_clearing_a_manual_delay_does_not_hide_an_overdue_eta(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'delay_reported_at' => now()->subHour(),
            'estimated_arrival' => today()->subDay(),
            'actual_arrival' => null,
            'status' => 'Dalam perjalanan',
        ])->save();
        $shipment->refresh();
        $originalVersion = $shipment->operational_version;
        $originalDelaySequence = $shipment->delay_report_sequence;

        $this
            ->actingAs($this->userWithRole('operator'))
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'clear_delay',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh();

        $this->assertNull($shipment->delay_reported_at);
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($originalDelaySequence, $shipment->delay_report_sequence);
        $this->assertTrue($shipment->isDelayed());
        $this->assertTrue(Shipment::query()->delayed()->whereKey($shipment)->exists());
    }

    public function test_arrived_action_records_the_actual_arrival_and_destination_consistently(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'delay_reported_at' => now()->subHour(),
            'actual_arrival' => null,
            'status' => 'Dalam perjalanan',
        ])->save();
        $shipment->refresh();

        $originalDeparture = $shipment->departure_date->toDateString();
        $originalEta = $shipment->estimated_arrival->toDateString();
        $originalVersion = $shipment->operational_version;
        $originalDelaySequence = $shipment->delay_report_sequence;
        $historyCount = $shipment->histories()->count();

        $this
            ->actingAs($this->userWithRole('operator'))
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'arrived',
                'actual_arrival' => today()->toDateString(),
                'location' => 'Pelabuhan yang salah',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh()->load(['container', 'destinationPort']);

        $this->assertSame('Tiba di pelabuhan tujuan', $shipment->status);
        $this->assertSame(today()->toDateString(), $shipment->actual_arrival?->toDateString());
        $this->assertNull($shipment->delay_reported_at);
        $this->assertSame($originalDeparture, $shipment->departure_date->toDateString());
        $this->assertSame($originalEta, $shipment->estimated_arrival->toDateString());
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($originalDelaySequence + 1, $shipment->delay_report_sequence);
        $this->assertSame('in_use', $shipment->container->status);
        $this->assertFalse($shipment->isDelayed());
        $this->assertSame($historyCount + 1, $shipment->histories()->count());

        $history = $shipment->histories()->reorder()->latest('id')->firstOrFail();

        $this->assertSame('Tiba di pelabuhan tujuan', $history->status);
        $this->assertSame($shipment->destinationPort->city, $history->location);
        $this->assertMatchesRegularExpression('/tiba|kedatangan/i', (string) $history->description);
    }

    public function test_arrived_action_cannot_overwrite_an_existing_actual_arrival_hidden_by_an_active_status(): void
    {
        $shipment = $this->customerShipment();
        $existingArrival = today()->subDay()->toDateString();
        $shipment->forceFill([
            'actual_arrival' => $existingArrival,
            'status' => 'Dalam perjalanan',
        ])->save();
        $shipment->refresh();

        $originalVersion = $shipment->operational_version;
        $historyCount = $shipment->histories()->count();

        $response = $this
            ->actingAs($this->userWithRole('admin'))
            ->from(route('shipments.edit', $shipment))
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'arrived',
                'actual_arrival' => today()->toDateString(),
            ]));

        $response
            ->assertRedirect(route('shipments.edit', $shipment))
            ->assertSessionHasErrors('action', null, 'quickAction');

        $shipment->refresh();

        $this->assertSame('Dalam perjalanan', $shipment->status);
        $this->assertSame($existingArrival, $shipment->actual_arrival?->toDateString());
        $this->assertSame($originalVersion, $shipment->operational_version);
        $this->assertSame($historyCount, $shipment->histories()->count());
    }

    public function test_update_action_adds_an_operational_note_without_changing_shipment_facts(): void
    {
        $shipment = $this->customerShipment()->fresh();
        $originalEta = $shipment->estimated_arrival->toDateString();
        $originalStatus = $shipment->status;
        $originalActualArrival = $shipment->actual_arrival;
        $originalDelayReportedAt = $shipment->delay_reported_at;
        $originalVersion = $shipment->operational_version;
        $historyCount = $shipment->histories()->count();

        $this
            ->actingAs($this->userWithRole('operator'))
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'update',
                'location' => 'Selat Makassar',
                'description' => 'Posisi kapal telah dikonfirmasi oleh tim operasional.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh();

        $this->assertSame($originalEta, $shipment->estimated_arrival->toDateString());
        $this->assertSame($originalStatus, $shipment->status);
        $this->assertSame($originalActualArrival, $shipment->actual_arrival);
        $this->assertSame($originalDelayReportedAt, $shipment->delay_reported_at);
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($historyCount + 1, $shipment->histories()->count());

        $history = $shipment->histories()->reorder()->latest('id')->firstOrFail();

        $this->assertSame($originalStatus, $history->status);
        $this->assertSame('Selat Makassar', $history->location);
        $this->assertSame('Posisi kapal telah dikonfirmasi oleh tim operasional.', $history->description);
    }

    #[DataProvider('invalidArrivalDates')]
    public function test_arrived_action_requires_a_date_between_departure_and_today(string $dateCase): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill([
            'delay_reported_at' => now()->subHour(),
            'actual_arrival' => null,
            'status' => 'Dalam perjalanan',
        ])->save();
        $shipment->refresh();

        $actualArrival = match ($dateCase) {
            'missing' => null,
            'before_departure' => $shipment->departure_date->subDay()->toDateString(),
            'after_today' => today()->addDay()->toDateString(),
        };
        $historyCount = $shipment->histories()->count();

        $response = $this
            ->actingAs($this->userWithRole('admin'))
            ->from(route('shipments.edit', $shipment))
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'arrived',
                'actual_arrival' => $actualArrival,
            ]));

        $response
            ->assertRedirect(route('shipments.edit', $shipment))
            ->assertSessionHasErrors('actual_arrival', null, 'quickAction');

        $shipment->refresh();

        $this->assertSame('Dalam perjalanan', $shipment->status);
        $this->assertNull($shipment->actual_arrival);
        $this->assertNotNull($shipment->delay_reported_at);
        $this->assertSame($historyCount, $shipment->histories()->count());
    }

    public function test_customer_cannot_run_a_shipment_quick_action(): void
    {
        $shipment = $this->customerShipment()->fresh();
        $customer = User::query()->where('role', 'customer')->firstOrFail();
        $historyCount = $shipment->histories()->count();

        $this
            ->actingAs($customer)
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'report_delay',
            ]))
            ->assertForbidden();

        $shipment->refresh();

        $this->assertNull($shipment->delay_reported_at);
        $this->assertSame($historyCount, $shipment->histories()->count());
    }

    public function test_guest_is_redirected_to_login_before_a_quick_action_runs(): void
    {
        $shipment = $this->customerShipment()->fresh();
        $historyCount = $shipment->histories()->count();

        $this
            ->patch(route('shipments.quick-action', $shipment), $this->quickActionPayload($shipment, [
                'action' => 'report_delay',
            ]))
            ->assertRedirect(route('login'));

        $shipment->refresh();

        $this->assertNull($shipment->delay_reported_at);
        $this->assertSame($historyCount, $shipment->histories()->count());
    }

    public function test_second_quick_action_with_the_same_version_is_rejected_even_when_time_is_frozen(): void
    {
        $shipment = $this->customerShipment()->fresh();
        $originalVersion = $shipment->operational_version;
        $originalUpdatedAt = $shipment->updated_at->toDateTimeString();
        $historyCount = $shipment->histories()->count();
        $payload = [
            'action' => 'update',
            'expected_version' => $originalVersion,
            'location' => 'Selat Makassar',
            'description' => 'Pembaruan tunggal pada waktu yang dibekukan.',
        ];

        $this
            ->actingAs($this->userWithRole('admin'))
            ->patch(route('shipments.quick-action', $shipment), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh();

        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($originalUpdatedAt, $shipment->updated_at->toDateTimeString());
        $this->assertSame($historyCount + 1, $shipment->histories()->count());

        $response = $this
            ->from(route('shipments.edit', $shipment))
            ->patch(route('shipments.quick-action', $shipment), $payload);

        $response
            ->assertRedirect(route('shipments.edit', $shipment))
            ->assertSessionHasErrors('action', null, 'quickAction');

        $shipment->refresh();

        $this->assertNull($shipment->delay_reported_at);
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($historyCount + 1, $shipment->histories()->count());
        $this->assertSame(
            1,
            $shipment->histories()->where('description', 'Pembaruan tunggal pada waktu yang dibekukan.')->count(),
        );
    }

    public function test_stale_full_update_cannot_overwrite_a_completed_quick_action(): void
    {
        $shipment = $this->customerShipment()->fresh();
        $originalVersion = $shipment->operational_version;
        $originalEta = $shipment->estimated_arrival->toDateString();
        $historyCount = $shipment->histories()->count();
        $staleFullUpdate = $this->fullUpdatePayload($shipment, [
            'expected_version' => $originalVersion,
            'estimated_arrival' => today()->addMonth()->toDateString(),
            'actual_arrival' => today()->toDateString(),
            'status' => 'Selesai',
            'history_location' => 'Data dari halaman lama',
            'history_description' => 'Perubahan basi seharusnya ditolak.',
        ]);

        $this
            ->actingAs($this->userWithRole('operator'))
            ->patch(route('shipments.quick-action', $shipment), [
                'action' => 'report_delay',
                'expected_version' => $originalVersion,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh();

        $this->assertNotNull($shipment->delay_reported_at);
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($historyCount + 1, $shipment->histories()->count());

        $response = $this
            ->from(route('shipments.edit', $shipment))
            ->put(route('shipments.update', $shipment), $staleFullUpdate);

        $response
            ->assertRedirect(route('shipments.edit', $shipment))
            ->assertSessionHasErrors('expected_version');

        $shipment->refresh();

        $this->assertSame('Dalam perjalanan', $shipment->status);
        $this->assertNull($shipment->actual_arrival);
        $this->assertNotNull($shipment->delay_reported_at);
        $this->assertSame($originalEta, $shipment->estimated_arrival->toDateString());
        $this->assertSame($originalVersion + 1, $shipment->operational_version);
        $this->assertSame($historyCount + 1, $shipment->histories()->count());
        $this->assertDatabaseMissing('shipment_histories', [
            'shipment_id' => $shipment->id,
            'description' => 'Perubahan basi seharusnya ditolak.',
        ]);
    }

    public function test_stale_delete_cannot_remove_a_shipment_updated_by_a_quick_action(): void
    {
        $shipment = $this->customerShipment()->fresh();
        $originalVersion = $shipment->operational_version;

        $this
            ->actingAs($this->userWithRole('admin'))
            ->patch(route('shipments.quick-action', $shipment), [
                'action' => 'update',
                'expected_version' => $originalVersion,
                'description' => 'Pembaruan terbaru sebelum upaya hapus basi.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this
            ->from(route('shipments.show', $shipment))
            ->delete(route('shipments.destroy', $shipment), [
                'expected_version' => $originalVersion,
            ])
            ->assertRedirect(route('shipments.show', $shipment))
            ->assertSessionHasErrors('expected_version');

        $this
            ->get(route('shipments.show', $shipment))
            ->assertOk()
            ->assertSee('Tindakan belum dapat dilakukan')
            ->assertSee('Pengiriman telah diperbarui oleh petugas lain. Muat ulang halaman sebelum menghapus.');

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
        $this->assertDatabaseHas('shipment_histories', [
            'shipment_id' => $shipment->id,
            'description' => 'Pembaruan terbaru sebelum upaya hapus basi.',
        ]);
    }

    public function test_edit_page_exposes_quick_actions_and_keeps_full_editing_under_advanced_details(): void
    {
        $shipment = $this->customerShipment();
        $shipment->forceFill(['delay_reported_at' => now()->subHour()])->save();
        $shipment->refresh();

        $response = $this
            ->actingAs($this->userWithRole('admin'))
            ->get(route('shipments.edit', $shipment));

        $response
            ->assertOk()
            ->assertSee('Aksi cepat')
            ->assertSee('Laporkan keterlambatan')
            ->assertSee('Tandai tiba')
            ->assertSee('Penerima terjadwal')
            ->assertSee('pelanggan@logitrack.test')
            ->assertSee('Pesan belum dikirim ke inbox; atur SMTP untuk pengiriman nyata.')
            ->assertSee('Detail lanjutan')
            ->assertSee('<details', false)
            ->assertSee('<summary', false)
            ->assertSee('action="'.route('shipments.quick-action', $shipment).'"', false)
            ->assertSee('name="expected_version"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('value="report_delay"', false)
            ->assertSee('value="clear_delay"', false)
            ->assertSee('value="arrived"', false)
            ->assertSee('value="update"', false)
            ->assertSee('name="actual_arrival"', false)
            ->assertSee(':disabled="action !== \'arrived\'"', false)
            ->assertSee(':disabled="action !== \'update\'"', false)
            ->assertSee('name="booking_number"', false)
            ->assertSee('name="estimated_arrival"', false);

        $this->assertSame(2, substr_count($response->getContent(), 'name="expected_version"'));
    }

    public static function operationalRoles(): array
    {
        return [
            'admin' => ['admin'],
            'operator' => ['operator'],
        ];
    }

    public static function invalidArrivalDates(): array
    {
        return [
            'actual date is required' => ['missing'],
            'date cannot precede departure' => ['before_departure'],
            'date cannot be in the future' => ['after_today'],
        ];
    }

    private function userWithRole(string $role): User
    {
        return User::query()->where('role', $role)->firstOrFail();
    }

    private function quickActionPayload(Shipment $shipment, array $overrides = []): array
    {
        return array_merge([
            'expected_version' => $shipment->operational_version,
        ], $overrides);
    }

    private function fullUpdatePayload(Shipment $shipment, array $overrides = []): array
    {
        return array_merge([
            'expected_version' => $shipment->operational_version,
            'booking_number' => $shipment->booking_number,
            'customer_id' => $shipment->customer_id,
            'container_id' => $shipment->container_id,
            'cargo_type_id' => $shipment->cargo_type_id,
            'vessel_id' => $shipment->vessel_id,
            'origin_port_id' => $shipment->origin_port_id,
            'destination_port_id' => $shipment->destination_port_id,
            'schedule_id' => $shipment->schedule_id,
            'departure_date' => $shipment->departure_date->toDateString(),
            'estimated_arrival' => $shipment->estimated_arrival->toDateString(),
            'actual_arrival' => $shipment->actual_arrival?->toDateString(),
            'status' => $shipment->status,
            'history_location' => null,
            'history_description' => null,
        ], $overrides);
    }
}
