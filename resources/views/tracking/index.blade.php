<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>LogiTrack AI</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-100 font-sans text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('tracking.index') }}" class="text-lg font-bold">LogiTrack AI</a>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Login</a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <section class="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    <p class="text-sm font-semibold uppercase tracking-wide text-cyan-700">Tracking kontainer</p>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Cek posisi pengiriman kontainer</h1>
                    <form method="POST" action="{{ route('tracking.search') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label for="container_number" class="block text-sm font-semibold text-slate-700">Nomor kontainer</label>
                            <input id="container_number" name="container_number" value="{{ old('container_number', $containerNumber ?? 'TANTO-CT-000124') }}" class="mt-2 w-full rounded-md border-slate-300 text-base shadow-sm focus:border-cyan-600 focus:ring-cyan-600">
                            @error('container_number')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button class="w-full rounded-md bg-cyan-700 px-4 py-3 text-sm font-semibold text-white hover:bg-cyan-800">Lacak Kontainer</button>
                    </form>
                    <div class="mt-6 rounded-md bg-slate-50 p-4 text-sm text-slate-600">
                        <p class="font-semibold text-slate-900">Contoh nomor kontainer</p>
                        <div class="mt-2 flex flex-col gap-2">
                            <a href="{{ route('tracking.show', 'TANTO-CT-000124') }}" class="text-cyan-700 underline decoration-cyan-200 underline-offset-4 hover:text-cyan-900">
                                TANTO-CT-000124
                            </a>
                            <a href="{{ route('tracking.show', 'TANTO-CT-000125') }}" class="text-red-700 underline decoration-red-200 underline-offset-4 hover:text-red-900">
                                TANTO-CT-000125
                            </a>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    @if($shipment)
                        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm text-slate-500">{{ $shipment->booking_number }}</p>
                                <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $shipment->container->container_number }}</h2>
                                <p class="mt-2 text-sm text-slate-600">{{ $shipment->originPort->city }} ke {{ $shipment->destinationPort->city }} - {{ $shipment->vessel->name }}</p>
                                <p class="mt-1 text-sm text-slate-600">Estimasi tiba: <span class="font-semibold text-slate-900">{{ $shipment->estimated_arrival->translatedFormat('d F Y') }}</span></p>
                            </div>
                            <span @class([
                                'rounded-full px-4 py-2 text-sm font-semibold text-white',
                                'bg-red-700' => $shipment->isDelayed(),
                                'bg-slate-900' => ! $shipment->isDelayed(),
                            ])>{{ $shipment->status }}</span>
                        </div>

                        @if($shipment->isDelayed())
                            <div data-delay-alert role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-5 text-red-950 shadow-sm">
                                <div class="flex items-start gap-4">
                                    <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-700 text-lg font-bold text-white">!</span>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-red-700">Pemberitahuan keterlambatan</p>
                                        <h3 class="mt-1 text-lg font-bold">Pengiriman mengalami keterlambatan</h3>
                                        <p class="mt-2 text-sm leading-6">
                                            Estimasi tiba {{ $shipment->estimated_arrival->translatedFormat('d F Y') }} telah terlewati selama
                                            <span class="font-bold">{{ $shipment->daysLate() }} hari</span>.
                                        </p>
                                        <p class="mt-1 text-sm leading-6">
                                            Status terakhir: <span class="font-semibold">{{ $shipment->status }}</span>. Pantau timeline ini atau hubungi petugas operasional untuk informasi terbaru.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="mt-6 space-y-6">
                            @foreach($shipment->timeline as $history)
                                <div class="relative border-l-2 border-cyan-200 pl-6">
                                    <span class="absolute -left-[9px] top-1 h-4 w-4 rounded-full border-2 border-white bg-cyan-700"></span>
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="font-semibold text-slate-900">{{ $history->location }} - {{ $history->status }}</p>
                                        <span class="text-xs text-slate-500">{{ $history->created_at->format('d M Y H:i') }}</span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $history->description }}</p>
                                </div>
                            @endforeach
                        </div>
                    @elseif($containerNumber)
                        <div class="flex min-h-80 items-center justify-center text-center">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Kontainer tidak ditemukan</h2>
                                <p class="mt-2 text-sm text-slate-500">Periksa nomor kontainer atau hubungi petugas operasional.</p>
                            </div>
                        </div>
                    @else
                        <div class="flex min-h-80 items-center justify-center text-center">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Timeline akan tampil di sini</h2>
                                <p class="mt-2 text-sm text-slate-500">Masukkan nomor kontainer untuk melihat histori pengiriman.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </main>
    </body>
</html>
