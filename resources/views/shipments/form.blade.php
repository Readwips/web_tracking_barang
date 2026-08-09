@php
    $isEditing = $shipment !== null;
    $isDelayed = $shipment?->isDelayed() ?? false;
    $daysLate = $shipment?->daysLate() ?? 0;
    $hasArrived = $shipment && (in_array($shipment->status, \App\Models\Shipment::ARRIVED_STATUSES, true) || $shipment->actual_arrival);
    $canReportDelay = $shipment && ! $hasArrived && ! $shipment->delay_reported_at;
    $canMarkArrived = $shipment && $shipment->status !== 'Selesai' && ! $shipment->actual_arrival;
    $defaultQuickAction = $canReportDelay ? 'report_delay' : 'update';
    $selectedQuickAction = old('action', $defaultQuickAction);
    if (($selectedQuickAction === 'report_delay' && ! $canReportDelay)
        || ($selectedQuickAction === 'arrived' && ! $canMarkArrived)
        || ($selectedQuickAction === 'clear_delay' && ! $shipment?->delay_reported_at)) {
        $selectedQuickAction = 'update';
    }
    $scheduleDateMismatch = $shipment?->schedule && (
        ! $shipment->departure_date->isSameDay($shipment->schedule->departure_date)
        || ! $shipment->estimated_arrival->isSameDay($shipment->schedule->estimated_arrival)
    );
    $delayAlertLabels = collect($delayAlertDestinations ?? [])->pluck('label')->all();
    $hasEmailDestination = collect($delayAlertDestinations ?? [])->contains('channel', 'mail');
    $mailTransport = (string) config('mail.default');
    $emailUsesLocalTransport = $hasEmailDestination && in_array($mailTransport, ['array', 'log'], true);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold text-slate-950">{{ $isEditing ? 'Perbarui Pengiriman' : 'Buat Booking Pengiriman' }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $isEditing ? 'Gunakan aksi cepat untuk pembaruan operasional sehari-hari.' : 'Lengkapi data awal pengiriman dan jadwalnya.' }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">
                    {{ session('status') }}
                </div>
            @endif

            @if($isEditing)
                <section aria-labelledby="shipment-overview-heading" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-700">Pengiriman saat ini</p>
                            <h3 id="shipment-overview-heading" class="mt-2 break-words text-2xl font-black text-slate-950 [overflow-wrap:anywhere]">
                                {{ $shipment->booking_number }}
                            </h3>
                            <p class="mt-1 break-words text-sm font-semibold text-slate-600 [overflow-wrap:anywhere]">{{ $shipment->container->container_number }}</p>

                            <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-600">
                                <span class="font-semibold text-slate-900">{{ $shipment->originPort->city }}</span>
                                <span aria-hidden="true">→</span>
                                <span class="font-semibold text-slate-900">{{ $shipment->destinationPort->city }}</span>
                                <span class="text-slate-300" aria-hidden="true">•</span>
                                <span>{{ $shipment->status }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 lg:items-end">
                            <div @class([
                                'w-full rounded-xl border px-4 py-3 text-left lg:min-w-72',
                                'border-red-200 bg-red-50 text-red-950' => $isDelayed,
                                'border-cyan-200 bg-cyan-50 text-cyan-950' => ! $isDelayed,
                            ])>
                                <p class="text-xs font-bold uppercase tracking-[0.14em]">Kondisi</p>
                                <p class="mt-1 text-lg font-black">
                                    @if($isDelayed && $daysLate > 0)
                                        Terlambat {{ $daysLate }} hari
                                    @elseif($isDelayed)
                                        Keterlambatan dilaporkan
                                    @else
                                        Belum terlambat
                                    @endif
                                </p>
                                <p class="mt-1 text-xs opacity-80">ETA {{ $shipment->estimated_arrival->translatedFormat('d F Y') }}</p>
                            </div>

                            <a href="{{ route('tracking.show', $shipment->container->container_number) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 lg:w-auto">
                                Lihat tracking pelanggan
                            </a>
                        </div>
                    </div>

                    @if($scheduleDateMismatch)
                        <div role="alert" class="border-t border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-950 sm:px-6">
                            <span class="font-bold">Periksa tanggal:</span> jadwal terhubung mencatat keberangkatan
                            {{ $shipment->schedule->departure_date->translatedFormat('d F Y') }} dan ETA
                            {{ $shipment->schedule->estimated_arrival->translatedFormat('d F Y') }}, sedangkan tanggal pengiriman saat ini berbeda.
                        </div>
                    @endif
                </section>

                <section aria-labelledby="quick-action-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-700">Pembaruan operasional</p>
                        <h3 id="quick-action-heading" class="mt-1 text-xl font-black text-slate-950">Aksi cepat</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Pilih satu tindakan. Data booking, pelanggan, kapal, rute, dan ETA tidak akan berubah.</p>
                    </div>

                    @if($errors->quickAction->any())
                        <div id="quick-action-errors" role="alert" tabindex="-1" class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                            <p class="font-bold">Pembaruan belum dapat disimpan:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach($errors->quickAction->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('shipments.quick-action', $shipment) }}"
                        class="mt-6"
                        x-data="{ action: @js($selectedQuickAction) }"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="expected_version" value="{{ $shipment->operational_version }}">

                        <fieldset @if($errors->quickAction->any()) aria-describedby="quick-action-errors" @endif>
                            <legend class="sr-only">Pilih aksi cepat</legend>
                            <div class="grid gap-3 md:grid-cols-3">
                                <label @class([
                                    'relative flex min-h-28 cursor-pointer flex-col rounded-xl border p-4 transition',
                                    'cursor-not-allowed border-slate-200 bg-slate-50 opacity-60' => ! $canReportDelay,
                                ]) :class="action === 'report_delay' ? 'border-red-500 bg-red-50 ring-2 ring-red-100' : 'border-slate-200 hover:border-slate-300'">
                                    <input class="sr-only" type="radio" name="action" value="report_delay" x-model="action" @disabled(! $canReportDelay)>
                                    <span class="text-sm font-black text-slate-950">Laporkan keterlambatan</span>
                                    <span class="mt-2 text-xs leading-5 text-slate-600">Tampilkan peringatan tanpa mengubah ETA atau status perjalanan.</span>
                                    @if($shipment->delay_reported_at)
                                        <span class="mt-auto pt-2 text-xs font-bold text-red-700">Sudah dilaporkan</span>
                                    @endif
                                </label>

                                <label @class([
                                    'relative flex min-h-28 cursor-pointer flex-col rounded-xl border p-4 transition',
                                    'cursor-not-allowed border-slate-200 bg-slate-50 opacity-60' => ! $canMarkArrived,
                                ]) :class="action === 'arrived' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300'">
                                    <input class="sr-only" type="radio" name="action" value="arrived" x-model="action" @disabled(! $canMarkArrived)>
                                    <span class="text-sm font-black text-slate-950">Tandai tiba</span>
                                    <span class="mt-2 text-xs leading-5 text-slate-600">Catat tanggal tiba dan tutup kondisi keterlambatan.</span>
                                </label>

                                <label class="relative flex min-h-28 cursor-pointer flex-col rounded-xl border p-4 transition" :class="action === 'update' ? 'border-cyan-500 bg-cyan-50 ring-2 ring-cyan-100' : 'border-slate-200 hover:border-slate-300'">
                                    <input class="sr-only" type="radio" name="action" value="update" x-model="action">
                                    <span class="text-sm font-black text-slate-950">Tambah pembaruan</span>
                                    <span class="mt-2 text-xs leading-5 text-slate-600">Tambahkan lokasi atau catatan tanpa mengganti status.</span>
                                </label>
                            </div>

                            @if($shipment->delay_reported_at)
                                <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition" :class="action === 'clear_delay' ? 'border-slate-500 bg-slate-50 ring-2 ring-slate-100' : 'hover:border-slate-300'">
                                    <input class="mt-0.5 rounded border-slate-300 text-slate-700 focus:ring-slate-500" type="radio" name="action" value="clear_delay" x-model="action">
                                    <span>
                                        <span class="block text-sm font-black text-slate-950">Tutup laporan keterlambatan</span>
                                        <span class="mt-1 block text-xs leading-5 text-slate-600">Peringatan tetap aktif apabila ETA memang sudah terlewati.</span>
                                    </span>
                                </label>
                            @endif
                        </fieldset>

                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div x-show="action === 'report_delay'" class="rounded-xl border border-red-100 bg-red-50 p-4 text-sm leading-6 text-red-900 md:col-span-2">
                                <p>Sistem akan menggunakan catatan netral: <span class="font-semibold">“Pengiriman ditandai mengalami keterlambatan. Tim operasional sedang melakukan pemantauan.”</span></p>
                                @if(! config('delay-alerts.enabled'))
                                    <p class="mt-2 font-semibold">Notifikasi email/webhook sedang dinonaktifkan. Peringatan tracking tetap langsung tampil.</p>
                                @elseif($delayAlertLabels !== [])
                                    <p class="mt-2"><span class="font-semibold">Penerima terjadwal:</span> {{ implode(', ', $delayAlertLabels) }}</p>
                                @else
                                    <p class="mt-2 font-semibold">Belum ada email atau webhook penerima. Peringatan tracking tetap langsung tampil.</p>
                                @endif
                                @if($emailUsesLocalTransport)
                                    <p class="mt-2 font-semibold">Mode email saat ini: {{ $mailTransport }}. Pesan belum dikirim ke inbox; atur SMTP untuk pengiriman nyata.</p>
                                @endif
                            </div>

                            <div x-show="action === 'clear_delay'" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700 md:col-span-2">
                                Laporan manual akan ditutup. Bila ETA sudah terlewati, status keterlambatan otomatis tetap aktif.
                            </div>

                            <div x-show="action === 'arrived'">
                                <label for="quick_actual_arrival" class="block text-sm font-bold text-slate-700">Tanggal tiba aktual</label>
                                <input
                                    id="quick_actual_arrival"
                                    name="actual_arrival"
                                    type="date"
                                    min="{{ $shipment->departure_date->toDateString() }}"
                                    max="{{ today()->toDateString() }}"
                                    value="{{ old('actual_arrival', today()->toDateString()) }}"
                                    :required="action === 'arrived'"
                                    aria-describedby="quick-arrival-help"
                                    @if($errors->quickAction->has('actual_arrival')) aria-invalid="true" @endif
                                    class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600"
                                >
                                <p id="quick-arrival-help" class="mt-2 text-xs leading-5 text-slate-500">Pilih tanggal sebenarnya. Tidak boleh sebelum keberangkatan atau setelah hari ini.</p>
                            </div>

                            <div x-show="action === 'update'">
                                <label for="quick_location" class="block text-sm font-bold text-slate-700">Lokasi <span class="font-normal text-slate-400">(opsional)</span></label>
                                <input id="quick_location" name="location" value="{{ old('location') }}" placeholder="Contoh: Dalam perjalanan" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            </div>

                            <div x-show="action === 'update'" class="md:col-span-2">
                                <label for="quick_description" class="block text-sm font-bold text-slate-700">Catatan pelanggan</label>
                                <textarea id="quick_description" name="description" rows="3" maxlength="1000" placeholder="Tuliskan pembaruan yang sudah terverifikasi." class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">{{ old('description') }}</textarea>
                                <p class="mt-2 text-xs leading-5 text-slate-500">Catatan ini akan muncul pada timeline tracking publik. Gunakan informasi yang sudah terverifikasi.</p>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p aria-live="polite" class="text-xs leading-5 text-slate-500">
                                Tracking diperbarui langsung. Email atau webhook diproses terpisah oleh scheduler jika penerima tersedia.
                            </p>
                            <button type="submit" class="inline-flex min-h-12 w-full shrink-0 items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-black text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:ring-offset-2 sm:w-auto">
                                <span x-text="action === 'report_delay' ? 'Laporkan sekarang' : (action === 'arrived' ? 'Catat kedatangan' : (action === 'clear_delay' ? 'Tutup laporan' : 'Simpan pembaruan'))">Simpan pembaruan</span>
                            </button>
                        </div>
                    </form>
                </section>
            @endif

            <details @if(! $isEditing || $errors->any()) open @endif class="group rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-black text-slate-950 sm:px-6">
                    <span>{{ $isEditing ? 'Detail lanjutan' : 'Data booking' }}</span>
                    <span class="text-slate-400 transition group-open:rotate-180" aria-hidden="true">⌄</span>
                </summary>

                <form method="POST" action="{{ $isEditing ? route('shipments.update', $shipment) : route('shipments.store') }}" class="space-y-7 border-t border-slate-200 p-5 sm:p-6">
                    @csrf
                    @if($isEditing)
                        @method('PUT')
                        <input type="hidden" name="expected_version" value="{{ $shipment->operational_version }}">
                    @endif

                    @if($errors->any())
                        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                            <p class="font-bold">Periksa kembali detail berikut:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <section aria-labelledby="identity-fields-heading">
                        <h3 id="identity-fields-heading" class="text-base font-black text-slate-950">Identitas pengiriman</h3>
                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="booking_number">Nomor booking</label>
                                <input id="booking_number" name="booking_number" required value="{{ old('booking_number', $shipment?->booking_number) }}" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                @error('booking_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="container_id">Nomor kontainer</label>
                                <select id="container_id" name="container_id" required class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    @foreach($containers as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('container_id', $shipment?->container_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('container_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="customer_id">Nama pelanggan</label>
                                <select id="customer_id" name="customer_id" required class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    @foreach($customers as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('customer_id', $shipment?->customer_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="cargo_type_id">Jenis barang</label>
                                <select id="cargo_type_id" name="cargo_type_id" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    <option value="">Tidak ditentukan</option>
                                    @foreach($cargoTypes as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('cargo_type_id', $shipment?->cargo_type_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('cargo_type_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="route-fields-heading" class="border-t border-slate-100 pt-6">
                        <h3 id="route-fields-heading" class="text-base font-black text-slate-950">Rute dan transportasi</h3>
                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="origin_port_id">Pelabuhan asal</label>
                                <select id="origin_port_id" name="origin_port_id" required class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    @foreach($ports as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('origin_port_id', $shipment?->origin_port_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('origin_port_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="destination_port_id">Pelabuhan tujuan</label>
                                <select id="destination_port_id" name="destination_port_id" required class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    @foreach($ports as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('destination_port_id', $shipment?->destination_port_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('destination_port_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="vessel_id">Nama kapal</label>
                                <select id="vessel_id" name="vessel_id" required class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    @foreach($vessels as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('vessel_id', $shipment?->vessel_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('vessel_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="schedule_id">Jadwal keberangkatan</label>
                                <select id="schedule_id" name="schedule_id" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    <option value="">Tidak terhubung jadwal</option>
                                    @foreach($schedules as $id => $label)
                                        <option value="{{ $id }}" @selected((string) old('schedule_id', $shipment?->schedule_id) === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('schedule_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="date-fields-heading" class="border-t border-slate-100 pt-6">
                        <h3 id="date-fields-heading" class="text-base font-black text-slate-950">Tanggal dan status perjalanan</h3>
                        <p class="mt-1 text-sm text-slate-500">Keterlambatan dilaporkan melalui Aksi cepat; status di bawah hanya menunjukkan tahap perjalanan.</p>
                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="departure_date">Tanggal keberangkatan</label>
                                <input id="departure_date" name="departure_date" type="date" required value="{{ old('departure_date', $shipment?->departure_date?->format('Y-m-d')) }}" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                @if($shipment)<p class="mt-2 text-xs text-slate-500">Terbaca sebagai: {{ $shipment->departure_date->translatedFormat('d F Y') }}</p>@endif
                                @error('departure_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="estimated_arrival">Estimasi tiba</label>
                                <input id="estimated_arrival" name="estimated_arrival" type="date" required value="{{ old('estimated_arrival', $shipment?->estimated_arrival?->format('Y-m-d')) }}" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                @if($shipment)<p class="mt-2 text-xs text-slate-500">Terbaca sebagai: {{ $shipment->estimated_arrival->translatedFormat('d F Y') }}</p>@endif
                                @error('estimated_arrival')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="actual_arrival">Tanggal tiba aktual</label>
                                <input id="actual_arrival" name="actual_arrival" type="date" value="{{ old('actual_arrival', $shipment?->actual_arrival?->format('Y-m-d')) }}" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                @error('actual_arrival')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="status">Status perjalanan</label>
                                <select id="status" name="status" required class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                    @foreach($statuses as $status)
                                        <option value="{{ $status }}" @selected(old('status', $shipment?->status ?? 'Booking dibuat') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                @error('status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="advanced-history-heading" class="border-t border-slate-100 pt-6">
                        <h3 id="advanced-history-heading" class="text-base font-black text-slate-950">Catatan bersama perubahan detail <span class="font-normal text-slate-400">(opsional)</span></h3>
                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="history_location">Lokasi update</label>
                                <input id="history_location" name="history_location" value="{{ old('history_location') }}" placeholder="Surabaya / Dalam perjalanan / Makassar" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                @error('history_location')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="history_description">Deskripsi update</label>
                                <textarea id="history_description" name="history_description" rows="3" placeholder="Tuliskan pembaruan yang sudah terverifikasi." class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">{{ old('history_description') }}</textarea>
                                @error('history_description')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ $isEditing ? route('shipments.show', $shipment) : route('shipments.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Batal</a>
                        <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-800">
                            {{ $isEditing ? 'Simpan detail' : 'Buat booking' }}
                        </button>
                    </div>
                </form>
            </details>
        </div>
    </div>
</x-app-layout>
