@php
    $timelineCutoff = $shipment->latest_status_at ?? now();
    $currentHistoryId = $shipment->timeline
        ->filter(fn ($history) => $history->created_at->lte($timelineCutoff))
        ->sortByDesc('created_at')
        ->first()?->id;
@endphp

<section data-tracking-timeline aria-labelledby="tracking-history-heading" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
    <div class="flex flex-col gap-2 border-b border-slate-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">Perjalanan</p>
            <h3 id="tracking-history-heading" class="mt-1 text-xl font-black text-slate-950">Riwayat &amp; estimasi</h3>
        </div>
        <p class="text-sm text-slate-500">{{ $shipment->timeline->count() }} catatan</p>
    </div>

    @if($shipment->timeline->isNotEmpty())
        <ol class="mt-7">
            @foreach($shipment->timeline as $history)
                @php
                    $isCurrentHistory = $history->id === $currentHistoryId;
                    $isProjected = $history->created_at->gt($timelineCutoff);
                @endphp
                <li @if($isCurrentHistory) aria-current="step" @endif class="relative grid grid-cols-[1.5rem_minmax(0,1fr)] gap-4 pb-8 last:pb-0">
                    @unless($loop->last)
                        <span class="absolute bottom-0 left-[0.6875rem] top-6 w-px bg-slate-200" aria-hidden="true"></span>
                    @endunless

                    <span @class([
                        'relative mt-1.5 h-6 w-6 rounded-full border-4 border-white shadow-sm ring-1',
                        'bg-cyan-600 ring-cyan-200' => $isCurrentHistory,
                        'bg-slate-300 ring-slate-200' => ! $isCurrentHistory,
                    ]) aria-hidden="true"></span>

                    <div class="min-w-0 rounded-2xl bg-slate-50/80 p-4 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="break-words font-bold text-slate-950">{{ $history->status }}</h4>
                                    @if($isCurrentHistory)
                                        <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-[0.6875rem] font-bold uppercase tracking-wider text-cyan-800">Pembaruan terakhir</span>
                                    @elseif($isProjected)
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[0.6875rem] font-bold uppercase tracking-wider text-amber-800">Estimasi</span>
                                    @endif
                                </div>
                                <p class="mt-1 break-words text-sm font-medium text-slate-600">{{ $history->location }}</p>
                            </div>
                            <time datetime="{{ $history->created_at->toIso8601String() }}" class="shrink-0 text-xs font-medium text-slate-500 sm:text-right">
                                {{ $history->created_at->translatedFormat('d M Y') }}<br class="hidden sm:block">
                                {{ $history->created_at->format('H:i') }} WIB
                            </time>
                        </div>
                        <p class="mt-3 break-words text-sm leading-6 text-slate-600 [overflow-wrap:anywhere]">{{ $history->description }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <div class="mt-7 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 1.5M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                </svg>
            </span>
            <h4 class="mt-4 font-bold text-slate-900">Belum ada pembaruan perjalanan</h4>
            <p class="mt-2 text-sm leading-6 text-slate-500">Status dan lokasi terbaru akan muncul di sini setelah dicatat oleh petugas operasional.</p>
        </div>
    @endif
</section>
