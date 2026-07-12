<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-900">{{ isset($item) ? 'Edit' : 'Tambah' }} {{ $definition['label'] }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ isset($item) ? route('master.update', [$resource, $item->id]) : route('master.store', $resource) }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
                @csrf
                @isset($item)
                    @method('PUT')
                @endisset

                @foreach($definition['fields'] as $name => $field)
                    @php
                        $current = old($name, isset($item) ? data_get($item, $name) : '');
                        if ($current instanceof \Illuminate\Support\Carbon) {
                            $current = $current->format('Y-m-d');
                        }
                    @endphp
                    <div>
                        <label for="{{ $name }}" class="block text-sm font-semibold text-slate-700">{{ $field['label'] }}</label>
                        @if($field['type'] === 'textarea')
                            <textarea id="{{ $name }}" name="{{ $name }}" rows="4" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">{{ $current }}</textarea>
                        @elseif($field['type'] === 'select')
                            <select id="{{ $name }}" name="{{ $name }}" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                                <option value="">Pilih {{ strtolower($field['label']) }}</option>
                                @foreach($options[$field['options']] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] }}" value="{{ $current }}" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                        @endif
                        @error($name)
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                    <a href="{{ route('master.index', $resource) }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
                    <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
