<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Pengiriman</h2>
                <p class="mt-1 text-sm text-slate-500">Booking, status, dan rute kontainer.</p>
            </div>
            @if(Auth::user()->hasRole('admin', 'operator'))
                <a href="{{ route('shipments.create') }}" class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Buat Booking</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Booking</th>
                                <th class="px-6 py-3">Kontainer</th>
                                <th class="px-6 py-3">Pelanggan</th>
                                <th class="px-6 py-3">Rute</th>
                                <th class="px-6 py-3">Kapal</th>
                                <th class="px-6 py-3">ETA</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($shipments as $shipment)
                                <tr>
                                    <td class="px-6 py-4">
                                        <a class="font-semibold text-cyan-700" href="{{ route('shipments.show', $shipment) }}">{{ $shipment->booking_number }}</a>
                                    </td>
                                    <td class="px-6 py-4">{{ $shipment->container->container_number }}</td>
                                    <td class="px-6 py-4">{{ $shipment->customer->name }}</td>
                                    <td class="px-6 py-4">{{ $shipment->originPort->city }} - {{ $shipment->destinationPort->city }}</td>
                                    <td class="px-6 py-4">{{ $shipment->vessel->name }}</td>
                                    <td class="px-6 py-4">{{ $shipment->estimated_arrival->format('d M Y') }}</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $shipment->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">Belum ada pengiriman.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $shipments->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
