<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Master Data</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $definition['label'] }}</p>
            </div>
            <a href="{{ route('master.create', $resource) }}" class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                Tambah
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap gap-2">
                @foreach([
                    'customers' => 'Pelanggan',
                    'vessels' => 'Kapal',
                    'ports' => 'Pelabuhan',
                    'containers' => 'Kontainer',
                    'cargo-types' => 'Jenis barang',
                    'schedules' => 'Jadwal',
                ] as $key => $label)
                    <a href="{{ route('master.index', $key) }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ $resource === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="rounded-lg border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                @foreach($definition['fields'] as $field)
                                    <th class="px-6 py-3">{{ $field['label'] }}</th>
                                @endforeach
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($items as $item)
                                <tr>
                                    @foreach($definition['fields'] as $name => $field)
                                        @php
                                            $value = isset($field['display']) ? $field['display']($item) : data_get($item, $name);
                                            if ($value instanceof \Illuminate\Support\Carbon) {
                                                $value = $value->format('d M Y');
                                            }
                                        @endphp
                                        <td class="px-6 py-4 text-slate-700">{{ $value ?: '-' }}</td>
                                    @endforeach
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('master.edit', [$resource, $item->id]) }}" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                            <form method="POST" action="{{ route('master.destroy', [$resource, $item->id]) }}" onsubmit="return confirm('Hapus data ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($definition['fields']) + 1 }}" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-6 py-4">
                    {{ $items->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
