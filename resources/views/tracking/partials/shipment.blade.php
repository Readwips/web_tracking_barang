@php
    $isDelayed = $shipment->isDelayed();
    $etaHasPassed = $shipment->hasPassedEstimatedArrival();
    $statusSaysArrived = in_array($shipment->status, \App\Models\Shipment::ARRIVED_STATUSES, true);
    $hasActualArrival = $shipment->actual_arrival !== null;
    $arrivalState = match (true) {
        $isDelayed => 'delayed',
        $statusSaysArrived && $hasActualArrival => 'arrived',
        $hasActualArrival => 'actual-status-pending',
        $statusSaysArrived => 'arrived-date-missing',
        default => 'estimated',
    };
    $arrivalDate = $hasActualArrival ? $shipment->actual_arrival : ($statusSaysArrived ? null : $shipment->estimated_arrival);
    $arrivalLabel = match ($arrivalState) {
        'delayed' => $etaHasPassed ? 'ETA terlewati' : 'Keterlambatan dilaporkan',
        'arrived' => 'Tiba pada',
        'actual-status-pending' => 'Kedatangan tercatat',
        'arrived-date-missing' => 'Status kedatangan tercatat',
        default => 'Estimasi tiba',
    };
@endphp

<article data-shipment-summary class="space-y-6">
    <section aria-labelledby="shipment-summary-heading" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_24px_70px_-36px_rgba(15,23,42,0.35)]">
        <div class="border-b border-slate-100 px-5 py-4 sm:px-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <span class="h-2 w-2 rounded-full bg-cyan-500" aria-hidden="true"></span>
                    <span>Hasil pelacakan</span>
                </div>
                <p class="text-xs text-slate-500 sm:text-sm">
                    @if($shipment->latest_status_at)
                        Diperbarui
                        <time datetime="{{ $shipment->latest_status_at->toIso8601String() }}" class="font-medium text-slate-700">
                            {{ $shipment->latest_status_at->translatedFormat('d M Y, H:i') }} WIB
                        </time>
                    @else
                        Waktu pembaruan belum tersedia
                    @endif
                </p>
            </div>
        </div>

        <div class="grid gap-8 px-5 py-7 sm:px-8 sm:py-9 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.38fr)] lg:items-center">
            <div class="min-w-0">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Nomor kontainer</p>
                        <h2 id="shipment-summary-heading" class="mt-2 break-words text-3xl font-black tracking-tight text-slate-950 [overflow-wrap:anywhere] sm:text-4xl">
                            {{ $shipment->container->container_number }}
                        </h2>
                        <p class="mt-2 break-words text-sm font-medium text-slate-500 [overflow-wrap:anywhere]">
                            Booking {{ $shipment->booking_number }}
                        </p>
                    </div>

                    <span @class([
                        'inline-flex w-fit shrink-0 items-center gap-2 rounded-full px-3.5 py-2 text-sm font-bold ring-1 ring-inset',
                        'bg-red-50 text-red-700 ring-red-600/20' => $isDelayed,
                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => ! $isDelayed && $statusSaysArrived,
                        'bg-cyan-50 text-cyan-800 ring-cyan-600/20' => ! $isDelayed && ! $statusSaysArrived,
                    ])>
                        <span @class([
                            'h-2 w-2 rounded-full',
                            'bg-red-600' => $isDelayed,
                            'bg-emerald-600' => ! $isDelayed && $statusSaysArrived,
                            'bg-cyan-600' => ! $isDelayed && ! $statusSaysArrived,
                        ]) aria-hidden="true"></span>
                        {{ $shipment->status }}
                    </span>
                </div>

                <div aria-label="Rute pengiriman" class="mt-8 grid grid-cols-[minmax(0,1fr)_4rem_minmax(0,1fr)] items-center gap-3 sm:grid-cols-[minmax(0,1fr)_8rem_minmax(0,1fr)]">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Asal</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900 sm:text-lg">{{ $shipment->originPort->city }}</p>
                        <p class="mt-1 text-xs text-slate-500">Berangkat {{ $shipment->departure_date->translatedFormat('d M Y') }}</p>
                    </div>

                    <div class="flex items-center" aria-hidden="true">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="mx-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-950 text-white shadow-sm">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 17h18M5 17l1.5-7h11L19 17M9 10V6h6v4M4 20h16" />
                            </svg>
                        </span>
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>

                    <div class="min-w-0 text-right">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Tujuan</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900 sm:text-lg">{{ $shipment->destinationPort->city }}</p>
                        <p class="mt-1 text-xs text-slate-500">ETA {{ $shipment->estimated_arrival->translatedFormat('d M Y') }}</p>
                    </div>
                </div>

                <div class="mt-7 flex min-w-0 items-start gap-2 text-sm text-slate-600">
                    <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 18h16M6 18V8l6-3 6 3v10M9 11h6M9 14h6" />
                    </svg>
                    <p class="min-w-0">Kapal <span class="break-words font-semibold text-slate-900 [overflow-wrap:anywhere]">{{ $shipment->vessel->name }}</span></p>
                </div>
            </div>

            <div data-arrival-state="{{ $arrivalState }}" @class([
                'rounded-2xl border p-5 sm:p-6',
                'border-red-200 bg-red-50' => $arrivalState === 'delayed',
                'border-emerald-200 bg-emerald-50' => in_array($arrivalState, ['arrived', 'arrived-date-missing'], true),
                'border-amber-200 bg-amber-50' => $arrivalState === 'actual-status-pending',
                'border-cyan-100 bg-cyan-50/70' => $arrivalState === 'estimated',
            ])>
                <p @class([
                    'text-xs font-bold uppercase tracking-[0.18em]',
                    'text-red-700' => $arrivalState === 'delayed',
                    'text-emerald-700' => in_array($arrivalState, ['arrived', 'arrived-date-missing'], true),
                    'text-amber-800' => $arrivalState === 'actual-status-pending',
                    'text-cyan-800' => $arrivalState === 'estimated',
                ])>{{ $arrivalLabel }}</p>
                @if($arrivalDate)
                    <p class="mt-3 text-2xl font-black tracking-tight text-slate-950">{{ $arrivalDate->translatedFormat('d F Y') }}</p>
                @else
                    <p class="mt-3 text-lg font-black tracking-tight text-slate-950">Tanggal aktual belum tersedia</p>
                @endif

                @if($arrivalState === 'delayed')
                    @if($etaHasPassed)
                        <p class="mt-3 text-sm font-semibold text-red-800">{{ $shipment->daysLate() }} hari melewati estimasi</p>
                    @else
                        <p class="mt-3 text-sm font-semibold text-red-800">Dilaporkan sebelum ETA terlewati</p>
                    @endif
                @elseif($arrivalState === 'arrived')
                    <p class="mt-3 text-sm font-semibold text-emerald-800">Kedatangan telah tercatat</p>
                @elseif($arrivalState === 'actual-status-pending')
                    <p class="mt-3 text-sm leading-6 text-amber-900">Tanggal kedatangan telah dicatat, tetapi status pengiriman masih <span class="font-bold">{{ $shipment->status }}</span>.</p>
                @elseif($arrivalState === 'arrived-date-missing')
                    <p class="mt-3 text-sm leading-6 text-emerald-900">Status menunjukkan pengiriman telah tiba, tetapi tanggal kedatangan aktual belum dicatat.</p>
                @else
                    <p class="mt-3 text-sm leading-6 text-slate-600">Perkiraan berdasarkan jadwal pengiriman saat ini.</p>
                @endif
            </div>
        </div>
    </section>

    @if($isDelayed)
        <section data-delay-alert role="alert" aria-labelledby="delay-alert-heading" class="rounded-3xl border border-red-200 bg-red-50 p-5 shadow-sm sm:p-6">
            <div class="flex items-start gap-4">
                <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-700 text-lg font-black text-white">!</span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-700">Pemberitahuan keterlambatan</p>
                    <h3 id="delay-alert-heading" class="mt-1 text-lg font-black text-red-950 sm:text-xl">Pengiriman mengalami keterlambatan</h3>
                    @if($etaHasPassed)
                        <p class="mt-2 text-sm leading-6 text-red-900">
                            Estimasi tiba {{ $shipment->estimated_arrival->translatedFormat('d F Y') }} telah terlewati selama
                            <span class="font-bold">{{ $shipment->daysLate() }} hari</span>. Status terakhir:
                            <span class="font-bold">{{ $shipment->status }}</span>.
                        </p>
                    @else
                        <p class="mt-2 text-sm leading-6 text-red-900">
                            Tim operasional telah melaporkan keterlambatan. ETA yang tercatat adalah
                            <span class="font-bold">{{ $shipment->estimated_arrival->translatedFormat('d F Y') }}</span>
                            dan status terakhir <span class="font-bold">{{ $shipment->status }}</span>.
                        </p>
                    @endif
                    <p class="mt-2 text-sm leading-6 text-red-800">Pantau pembaruan di halaman ini atau sampaikan nomor booking kepada petugas operasional.</p>
                </div>
            </div>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(17rem,0.65fr)] lg:items-start">
        @include('tracking.partials.timeline')
        @include('tracking.partials.details')
    </div>
</article>
