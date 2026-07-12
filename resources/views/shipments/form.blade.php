<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-900">{{ $shipment ? 'Edit Pengiriman' : 'Buat Booking Pengiriman' }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $shipment ? route('shipments.update', $shipment) : route('shipments.store') }}" class="space-y-6 rounded-lg border border-slate-200 bg-white p-6">
                @csrf
                @if($shipment)
                    @method('PUT')
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="booking_number">Nomor booking</label>
                        <input id="booking_number" name="booking_number" value="{{ old('booking_number', $shipment?->booking_number) }}" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                        @error('booking_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="container_id">Nomor kontainer</label>
                        <select id="container_id" name="container_id" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @foreach($containers as $id => $label)
                                <option value="{{ $id }}" @selected((string) old('container_id', $shipment?->container_id) === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('container_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="customer_id">Nama pelanggan</label>
                        <select id="customer_id" name="customer_id" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @foreach($customers as $id => $label)
                                <option value="{{ $id }}" @selected((string) old('customer_id', $shipment?->customer_id) === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('customer_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="cargo_type_id">Jenis barang</label>
                        <select id="cargo_type_id" name="cargo_type_id" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            <option value="">Tidak ditentukan</option>
                            @foreach($cargoTypes as $id => $label)
                                <option value="{{ $id }}" @selected((string) old('cargo_type_id', $shipment?->cargo_type_id) === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('cargo_type_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="origin_port_id">Pelabuhan asal</label>
                        <select id="origin_port_id" name="origin_port_id" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @foreach($ports as $id => $label)
                                <option value="{{ $id }}" @selected((string) old('origin_port_id', $shipment?->origin_port_id) === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('origin_port_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="destination_port_id">Pelabuhan tujuan</label>
                        <select id="destination_port_id" name="destination_port_id" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @foreach($ports as $id => $label)
                                <option value="{{ $id }}" @selected((string) old('destination_port_id', $shipment?->destination_port_id) === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('destination_port_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="vessel_id">Nama kapal</label>
                        <select id="vessel_id" name="vessel_id" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @foreach($vessels as $id => $label)
                                <option value="{{ $id }}" @selected((string) old('vessel_id', $shipment?->vessel_id) === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('vessel_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="schedule_id">Jadwal keberangkatan</label>
                        <select id="schedule_id" name="schedule_id" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            <option value="">Tidak terhubung jadwal</option>
                            @foreach($schedules as $id => $label)
                                <option value="{{ $id }}" @selected((string) old('schedule_id', $shipment?->schedule_id) === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('schedule_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="departure_date">Tanggal keberangkatan</label>
                        <input id="departure_date" name="departure_date" type="date" value="{{ old('departure_date', $shipment?->departure_date?->format('Y-m-d')) }}" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                        @error('departure_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="estimated_arrival">Estimasi tiba</label>
                        <input id="estimated_arrival" name="estimated_arrival" type="date" value="{{ old('estimated_arrival', $shipment?->estimated_arrival?->format('Y-m-d')) }}" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                        @error('estimated_arrival')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="actual_arrival">Tanggal tiba aktual</label>
                        <input id="actual_arrival" name="actual_arrival" type="date" value="{{ old('actual_arrival', $shipment?->actual_arrival?->format('Y-m-d')) }}" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                        @error('actual_arrival')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700" for="status">Status pengiriman</label>
                        <select id="status" name="status" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $shipment?->status ?? 'Booking dibuat') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="rounded-lg bg-slate-50 p-5">
                    <h3 class="text-sm font-semibold text-slate-900">Histori tracking</h3>
                    <div class="mt-4 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700" for="history_location">Lokasi update</label>
                            <input id="history_location" name="history_location" value="{{ old('history_location') }}" placeholder="Surabaya / Dalam perjalanan / Makassar" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @error('history_location')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700" for="history_description">Deskripsi update</label>
                            <input id="history_description" name="history_description" value="{{ old('history_description') }}" placeholder="Kontainer dimuat ke kapal" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @error('history_description')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <a href="{{ route('shipments.index') }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
                    <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
