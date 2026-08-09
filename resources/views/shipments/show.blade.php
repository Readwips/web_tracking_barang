<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">{{ $shipment->booking_number }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $shipment->container->container_number }} - {{ $shipment->customer->name }}</p>
            </div>
            @if(Auth::user()->hasRole('admin', 'operator'))
                <div class="flex gap-2">
                    <a href="{{ route('shipments.edit', $shipment) }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                    <form method="POST" action="{{ route('shipments.destroy', $shipment) }}" onsubmit="return confirm('Hapus pengiriman ini?')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="expected_version" value="{{ $shipment->operational_version }}">
                        <button class="rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                    </form>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div class="space-y-6 lg:col-span-2">
                @if($errors->any())
                    <div role="alert" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        <p class="font-semibold">Tindakan belum dapat dilakukan:</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('status'))
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif

                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    <h3 class="text-base font-semibold text-slate-900">Detail pengiriman</h3>
                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase text-slate-500">Pelanggan</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $shipment->customer->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-slate-500">Kapal</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $shipment->vessel->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-slate-500">Rute</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $shipment->originPort->city }} ke {{ $shipment->destinationPort->city }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-slate-500">Jenis barang</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $shipment->cargoType?->name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-slate-500">Berangkat</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $shipment->departure_date->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-slate-500">Estimasi tiba</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $shipment->estimated_arrival->format('d M Y') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    <h3 class="text-base font-semibold text-slate-900">Timeline tracking</h3>
                    <div class="mt-6 space-y-6">
                        @forelse($shipment->timeline as $history)
                            <div class="relative border-l-2 border-cyan-200 pl-6">
                                <span class="absolute -left-[9px] top-1 h-4 w-4 rounded-full border-2 border-white bg-cyan-700"></span>
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="font-semibold text-slate-900">{{ $history->location }} - {{ $history->status }}</p>
                                    <span class="text-xs text-slate-500">{{ $history->created_at->format('d M Y H:i') }}</span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $history->description }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada histori tracking.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    <p class="text-sm font-medium text-slate-500">Status saat ini</p>
                    @if($shipment->isDelayed())
                        <div class="mt-3 rounded-md bg-red-700 px-4 py-3 text-center text-white">
                            <p class="text-sm font-bold">{{ $shipment->daysLate() > 0 ? 'Terlambat '.$shipment->daysLate().' hari' : 'Keterlambatan dilaporkan' }}</p>
                            <p class="mt-1 text-xs text-red-100">{{ $shipment->status }}</p>
                        </div>
                    @else
                        <p class="mt-3 rounded-md bg-slate-900 px-4 py-3 text-center text-sm font-semibold text-white">{{ $shipment->status }}</p>
                    @endif
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    <h3 class="text-base font-semibold text-slate-900">API tracking</h3>
                    <code class="mt-3 block break-all rounded-md bg-slate-50 p-3 text-xs text-slate-700">GET /api/shipments/{{ $shipment->container->container_number }}</code>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
