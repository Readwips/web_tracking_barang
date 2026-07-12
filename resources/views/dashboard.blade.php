<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-900">Dashboard Operasional</h2>
                <p class="mt-1 text-sm text-slate-500">Ringkasan pengiriman kontainer dan performa rute.</p>
            </div>
            <span class="text-sm font-medium text-slate-500">{{ now()->format('d M Y H:i') }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <p class="text-sm font-medium text-slate-500">Pengiriman aktif</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ $activeShipments }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <p class="text-sm font-medium text-slate-500">Pengiriman selesai</p>
                    <p class="mt-3 text-3xl font-bold text-emerald-700">{{ $completedShipments }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <p class="text-sm font-medium text-slate-500">Terlambat</p>
                    <p class="mt-3 text-3xl font-bold text-red-700">{{ $delayedShipments }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <p class="text-sm font-medium text-slate-500">Pelanggan</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ $customerCount }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-6 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-slate-900">Grafik pengiriman bulanan</h3>
                        <span class="text-xs font-semibold uppercase text-slate-400">Chart.js</span>
                    </div>
                    <div class="mt-6 h-72">
                        <canvas id="shipmentChart"></canvas>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    <h3 class="text-base font-semibold text-slate-900">Rute terbanyak</h3>
                    <div class="mt-5 space-y-4">
                        @forelse($routeCounts->take(5) as $route => $count)
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium text-slate-700">{{ $route }}</span>
                                    <span class="text-slate-500">{{ $count }}</span>
                                </div>
                                <div class="mt-2 h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-cyan-600" style="width: {{ max(10, ($count / max(1, $routeCounts->max())) * 100) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada data rute.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-6">
                <h3 class="text-base font-semibold text-cyan-950">Ringkasan AI operasional</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-cyan-950">{{ $operationalSummary }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-900">Pengiriman terbaru</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Booking</th>
                                <th class="px-6 py-3">Kontainer</th>
                                <th class="px-6 py-3">Pelanggan</th>
                                <th class="px-6 py-3">Rute</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentShipments as $shipment)
                                <tr>
                                    <td class="px-6 py-4">
                                        <a class="font-semibold text-cyan-700" href="{{ route('shipments.show', $shipment) }}">{{ $shipment->booking_number }}</a>
                                    </td>
                                    <td class="px-6 py-4">{{ $shipment->container->container_number }}</td>
                                    <td class="px-6 py-4">{{ $shipment->customer->name }}</td>
                                    <td class="px-6 py-4">{{ $shipment->originPort->city }} - {{ $shipment->destinationPort->city }}</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $shipment->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada pengiriman.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('shipmentChart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($monthlyLabels),
                datasets: [{
                    label: 'Pengiriman',
                    data: @json($monthlyValues),
                    backgroundColor: '#0891b2',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    </script>
</x-app-layout>
