<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-900">AI Assistant</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">Informasi pelanggan</h3>
                <form method="POST" action="{{ route('ai.customer-notice') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="context" class="block text-sm font-semibold text-slate-700">Catatan petugas</label>
                        <textarea id="context" name="context" rows="7" class="mt-2 w-full rounded-md border-slate-300 shadow-sm focus:border-cyan-600 focus:ring-cyan-600">{{ old('context', $context) }}</textarea>
                        @error('context')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Buat Pesan</button>
                </form>

                @if($notice)
                    <div class="mt-6 rounded-lg bg-slate-50 p-5">
                        <p class="text-sm font-semibold text-slate-700">Draft pesan</p>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $notice }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-900">Ringkasan operasional</h3>
                    <form method="POST" action="{{ route('ai.operational-summary') }}">
                        @csrf
                        <button class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Refresh</button>
                    </form>
                </div>
                <div class="mt-5 rounded-lg bg-cyan-50 p-5">
                    <p class="whitespace-pre-line text-sm leading-6 text-cyan-950">{{ $summary }}</p>
                </div>

                <div class="mt-6 space-y-3 text-sm text-slate-600">
                    <div class="rounded-md border border-slate-200 p-4">
                        <span class="font-semibold text-slate-900">Provider:</span>
                        OpenAI API via `OPENAI_API_KEY`, dengan fallback lokal untuk demo.
                    </div>
                    <div class="rounded-md border border-slate-200 p-4">
                        <span class="font-semibold text-slate-900">Model:</span>
                        {{ config('services.openai.model') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
