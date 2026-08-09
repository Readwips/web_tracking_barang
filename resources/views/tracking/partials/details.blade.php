<aside class="space-y-6">
    <section aria-labelledby="shipment-details-heading" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">Informasi</p>
        <h3 id="shipment-details-heading" class="mt-1 text-xl font-black text-slate-950">Detail pengiriman</h3>

        <dl class="mt-6 divide-y divide-slate-100">
            <div class="py-3 first:pt-0">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Booking</dt>
                <dd class="mt-1 break-words text-sm font-bold text-slate-900 [overflow-wrap:anywhere]">{{ $shipment->booking_number }}</dd>
            </div>
            <div class="py-3">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Kontainer</dt>
                <dd class="mt-1 break-words text-sm font-bold text-slate-900 [overflow-wrap:anywhere]">{{ $shipment->container->container_number }}</dd>
                <dd class="mt-1 text-xs text-slate-500">{{ $shipment->container->container_type }}</dd>
            </div>
            <div class="py-3">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Kapal</dt>
                <dd class="mt-1 break-words text-sm font-bold text-slate-900">{{ $shipment->vessel->name }}</dd>
            </div>
            <div class="py-3">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tanggal berangkat</dt>
                <dd class="mt-1 text-sm font-bold text-slate-900">
                    <time datetime="{{ $shipment->departure_date->toDateString() }}">{{ $shipment->departure_date->translatedFormat('d F Y') }}</time>
                </dd>
            </div>
            <div class="py-3">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Estimasi tiba</dt>
                <dd class="mt-1 text-sm font-bold text-slate-900">
                    <time datetime="{{ $shipment->estimated_arrival->toDateString() }}">{{ $shipment->estimated_arrival->translatedFormat('d F Y') }}</time>
                </dd>
            </div>
            <div class="py-3 last:pb-0">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Kedatangan aktual</dt>
                <dd class="mt-1 text-sm font-bold text-slate-900">
                    @if($shipment->actual_arrival)
                        <time datetime="{{ $shipment->actual_arrival->toDateString() }}">{{ $shipment->actual_arrival->translatedFormat('d F Y') }}</time>
                    @else
                        <span class="font-medium text-slate-500">Belum tercatat</span>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="tracking-help-heading" class="rounded-3xl bg-slate-950 p-5 text-white shadow-sm sm:p-6">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-cyan-300" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01M9.6 9a2.5 2.5 0 1 1 3.7 2.2c-.8.45-1.3.9-1.3 1.8v.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </span>
        <h3 id="tracking-help-heading" class="mt-4 text-lg font-black">Butuh bantuan?</h3>
        <p class="mt-2 break-words text-sm leading-6 text-slate-300 [overflow-wrap:anywhere]">Sampaikan nomor booking <span class="font-bold text-white">{{ $shipment->booking_number }}</span> kepada petugas operasional agar pemeriksaan lebih cepat.</p>
    </section>
</aside>
