<?php

namespace Database\Seeders;

use App\Models\CargoType;
use App\Models\Container as ShippingContainer;
use App\Models\Customer;
use App\Models\Port;
use App\Models\Schedule;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DatabaseSeeder hanya berisi akun dan data demo; jangan jalankan di production.');
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@logitrack.test'],
            ['name' => 'Admin LogiTrack', 'role' => 'admin', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $operator = User::updateOrCreate(
            ['email' => 'operator@logitrack.test'],
            ['name' => 'Petugas Operasional', 'role' => 'operator', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $customerUser = User::updateOrCreate(
            ['email' => 'pelanggan@logitrack.test'],
            ['name' => 'Budi Santoso', 'role' => 'customer', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );

        $surabaya = Port::updateOrCreate(['name' => 'Terminal Petikemas Surabaya'], ['city' => 'Surabaya']);
        $makassar = Port::updateOrCreate(['name' => 'Makassar New Port'], ['city' => 'Makassar']);
        $jakarta = Port::updateOrCreate(['name' => 'Tanjung Priok'], ['city' => 'Jakarta']);
        $balikpapan = Port::updateOrCreate(['name' => 'Pelabuhan Semayang'], ['city' => 'Balikpapan']);

        $vesselTanto = Vessel::updateOrCreate(['vessel_code' => 'TANTO-SEA-07'], ['name' => 'KM Tanto Sejahtera']);
        $vesselMeratus = Vessel::updateOrCreate(['vessel_code' => 'MRTS-22'], ['name' => 'MV Meratus Jayakarta']);

        $generalCargo = CargoType::updateOrCreate(['name' => 'General Cargo'], ['description' => 'Barang umum dalam kemasan kontainer.']);
        $coldChain = CargoType::updateOrCreate(['name' => 'Reefer Cargo'], ['description' => 'Barang temperatur terkendali.']);

        $container124 = ShippingContainer::updateOrCreate(
            ['container_number' => 'TANTO-CT-000124'],
            ['container_type' => '40 FT High Cube', 'status' => 'in_use']
        );
        $container125 = ShippingContainer::updateOrCreate(
            ['container_number' => 'TANTO-CT-000125'],
            ['container_type' => '20 FT Dry', 'status' => 'in_use']
        );
        $container126 = ShippingContainer::updateOrCreate(
            ['container_number' => 'TANTO-CT-000126'],
            ['container_type' => '40 FT Reefer', 'status' => 'available']
        );

        $customer = Customer::updateOrCreate(
            ['user_id' => $customerUser->id],
            ['name' => 'PT Nusantara Retail', 'phone' => '0812-9900-1122', 'address' => 'Jl. Veteran No. 10, Surabaya']
        );
        $customerTwo = Customer::updateOrCreate(
            ['name' => 'CV Makmur Timur'],
            ['phone' => '0411-555-010', 'address' => 'Jl. Pelabuhan Raya, Makassar']
        );

        $schedule = Schedule::updateOrCreate(
            [
                'vessel_id' => $vesselTanto->id,
                'origin_port_id' => $surabaya->id,
                'destination_port_id' => $makassar->id,
                'departure_date' => now()->subDays(2)->toDateString(),
            ],
            ['estimated_arrival' => now()->addDays(2)->toDateString(), 'status' => 'delayed']
        );

        $shipment = Shipment::updateOrCreate(
            ['booking_number' => 'BOOK-2026-000124'],
            [
                'customer_id' => $customer->id,
                'container_id' => $container124->id,
                'cargo_type_id' => $generalCargo->id,
                'vessel_id' => $vesselTanto->id,
                'origin_port_id' => $surabaya->id,
                'destination_port_id' => $makassar->id,
                'schedule_id' => $schedule->id,
                'departure_date' => now()->subDays(2)->toDateString(),
                'estimated_arrival' => now()->addDays(2)->toDateString(),
                'status' => 'Dalam perjalanan',
                'latest_status_at' => now()->subHours(3),
            ]
        );

        $timeline = [
            ['Kontainer diterima', 'Surabaya', 'Kontainer diterima di Terminal Petikemas Surabaya.', now()->subDays(3)],
            ['Dimuat ke kapal', 'Surabaya', 'Kontainer dimuat ke KM Tanto Sejahtera.', now()->subDays(2)],
            ['Dalam perjalanan', 'Dalam perjalanan', 'Menuju Makassar melalui rute laut reguler.', now()->subDay()],
            ['Tiba di pelabuhan', 'Makassar', 'Estimasi tiba di Makassar New Port.', now()->addDays(2)],
        ];

        foreach ($timeline as [$status, $location, $description, $time]) {
            $history = ShipmentHistory::firstOrNew([
                'shipment_id' => $shipment->id,
                'status' => $status,
                'location' => $location,
            ]);
            $history->description = $description;
            $history->created_at = $time;
            $history->updated_at = $time;
            $history->save();
        }

        Shipment::updateOrCreate(
            ['booking_number' => 'BOOK-2026-000125'],
            [
                'customer_id' => $customerTwo->id,
                'container_id' => $container125->id,
                'cargo_type_id' => $coldChain->id,
                'vessel_id' => $vesselMeratus->id,
                'origin_port_id' => $jakarta->id,
                'destination_port_id' => $balikpapan->id,
                'departure_date' => now()->subDays(5)->toDateString(),
                'estimated_arrival' => now()->subDay()->toDateString(),
                'status' => 'Dalam perjalanan',
                'latest_status_at' => now()->subHours(30),
            ]
        );

        Shipment::updateOrCreate(
            ['booking_number' => 'BOOK-2026-000126'],
            [
                'customer_id' => $customer->id,
                'container_id' => $container126->id,
                'cargo_type_id' => $generalCargo->id,
                'vessel_id' => $vesselTanto->id,
                'origin_port_id' => $surabaya->id,
                'destination_port_id' => $makassar->id,
                'departure_date' => now()->subDays(12)->toDateString(),
                'estimated_arrival' => now()->subDays(8)->toDateString(),
                'actual_arrival' => now()->subDays(8)->toDateString(),
                'status' => 'Selesai',
                'latest_status_at' => now()->subDays(8),
            ]
        );
    }
}
