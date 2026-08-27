<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Lacak status, estimasi tiba, dan riwayat perjalanan kontainer Anda dengan LogiTrack AI.">
        <title>Tracking Kontainer | LogiTrack AI</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <a href="#main-content" class="sr-only z-50 rounded-lg bg-white px-4 py-3 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            Lewati ke konten utama
        </a>

        <header class="relative z-20 border-b border-white/10 bg-slate-950 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('tracking.index') }}" class="group inline-flex items-center gap-3" aria-label="LogiTrack AI - halaman tracking">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-950/30 transition group-hover:bg-cyan-300" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 17h16M5.5 17 7 10h10l1.5 7M9 10V6h6v4M7 20h10" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-base font-black tracking-tight">LogiTrack</span>
                        <span class="block text-[0.625rem] font-bold uppercase tracking-[0.24em] text-cyan-300">Smart Tracking</span>
                    </span>
                </a>

                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full border border-white/20 px-4 py-2 text-sm font-bold text-white transition hover:border-white/40 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950">
                        Buka Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full border border-white/20 px-4 py-2 text-sm font-bold text-white transition hover:border-white/40 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950">
                        Login Petugas
                    </a>
                @endauth
            </div>
        </header>

        <main id="main-content">
            <section aria-labelledby="tracking-page-heading" class="relative isolate overflow-hidden bg-slate-950 pb-32 pt-14 text-white sm:pb-36 sm:pt-20">
                <div class="absolute inset-0 -z-10 opacity-90" aria-hidden="true">
                    <div class="absolute -left-32 top-10 h-80 w-80 rounded-full bg-cyan-500/20 blur-3xl"></div>
                    <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-blue-600/20 blur-3xl"></div>
                    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-cyan-300/40 to-transparent"></div>
                    <svg class="absolute inset-0 h-full w-full stroke-white/[0.035]" aria-hidden="true">
                        <defs>
                            <pattern id="tracking-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                                <path d="M48 0H0v48" fill="none" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#tracking-grid)" />
                    </svg>
                </div>

                <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <p class="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3.5 py-1.5 text-xs font-bold uppercase tracking-[0.18em] text-cyan-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-300" aria-hidden="true"></span>
                        Pelacakan kontainer
                    </p>
                    <h1 id="tracking-page-heading" class="mx-auto mt-6 max-w-3xl text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                        Lacak perjalanan pengiriman Anda
                    </h1>
                    <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                        Masukkan nomor kontainer untuk melihat status terakhir, estimasi tiba, dan riwayat perjalanan dalam satu tampilan.
                    </p>

                    <form method="POST" action="{{ route('tracking.search') }}" class="mx-auto mt-9 max-w-3xl text-left" novalidate>
                        @csrf
                        <label for="container_number" class="mb-2 block text-sm font-bold text-white">Nomor kontainer</label>
                        <div class="rounded-2xl bg-white p-2 shadow-[0_24px_70px_-20px_rgba(6,182,212,0.35)] sm:flex sm:items-center sm:gap-2">
                            <div class="flex min-w-0 flex-1 items-center">
                                <span class="ml-3 hidden text-slate-400 sm:block" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.4-4.4m2.4-5.1a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                                    </svg>
                                </span>
                                <input
                                    id="container_number"
                                    name="container_number"
                                    type="text"
                                    value="{{ old('container_number', $containerNumber ?? '') }}"
                                    placeholder="Contoh: TANTO-CT-000124"
                                    autocomplete="off"
                                    autocapitalize="characters"
                                    spellcheck="false"
                                    required
                                    aria-invalid="{{ $errors->has('container_number') ? 'true' : 'false' }}"
                                    @if($errors->has('container_number')) aria-describedby="container-number-error" @endif
                                    class="w-full min-w-0 border-0 bg-transparent px-3 py-3 text-base font-semibold uppercase tracking-wide text-slate-950 placeholder:normal-case placeholder:font-normal placeholder:tracking-normal placeholder:text-slate-400 focus:ring-0 sm:py-2.5"
                                >
                            </div>
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-cyan-500 px-6 py-3.5 text-sm font-black text-slate-950 transition hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:ring-offset-2 sm:w-auto sm:shrink-0">
                                Lacak Sekarang
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                </svg>
                            </button>
                        </div>
                        @error('container_number')
                            <p id="container-number-error" class="mt-3 rounded-xl border border-red-300/30 bg-red-400/10 px-4 py-2 text-sm font-semibold text-red-100">{{ $message }}</p>
                        @enderror
                    </form>

                </div>
            </section>

            <section class="relative z-10 -mt-20 pb-16 sm:-mt-24 sm:pb-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    @if($shipment)
                        @include('tracking.partials.shipment')
                    @elseif($containerNumber)
                        <section aria-labelledby="not-found-heading" class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white px-5 py-12 text-center shadow-[0_24px_70px_-36px_rgba(15,23,42,0.35)] sm:px-10 sm:py-16">
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-500" aria-hidden="true">
                                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 15 5 5m-2-8a6 6 0 1 1-12 0 6 6 0 0 1 12 0ZM9.8 9.8l4.4 4.4m0-4.4-4.4 4.4" />
                                </svg>
                            </span>
                            <p class="mt-5 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Tidak ada hasil</p>
                            <h2 id="not-found-heading" class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Kontainer tidak ditemukan</h2>
                            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-slate-600">
                                Tidak ada data untuk nomor <span class="break-words font-bold text-slate-900 [overflow-wrap:anywhere]">{{ $containerNumber }}</span>. Periksa kembali penulisannya atau hubungi petugas operasional.
                            </p>
                            <a href="{{ route('tracking.index') }}#container_number" class="mt-7 inline-flex items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                                Coba nomor lain
                            </a>
                        </section>
                    @else
                        <div class="rounded-3xl border border-slate-200 bg-white shadow-[0_24px_70px_-36px_rgba(15,23,42,0.35)]">
                            <section aria-labelledby="tracking-benefits-heading" class="grid gap-px overflow-hidden rounded-3xl bg-slate-200 md:grid-cols-3">
                                <h2 id="tracking-benefits-heading" class="sr-only">Informasi yang tersedia</h2>

                                <div class="bg-white p-6 sm:p-8">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-700" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-12v4l2.5 1.5M9 3h6" />
                                        </svg>
                                    </span>
                                    <h3 class="mt-5 font-black text-slate-950">Status terbaru</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">Lihat pembaruan status dan waktu pencatatan terakhir pengiriman.</p>
                                </div>

                                <div class="bg-white p-6 sm:p-8">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-700" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 18h16M6 18V8l6-3 6 3v10M9 11h6M9 14h6" />
                                        </svg>
                                    </span>
                                    <h3 class="mt-5 font-black text-slate-950">Rute & estimasi</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">Periksa asal, tujuan, kapal, serta estimasi tanggal kedatangan.</p>
                                </div>

                                <div class="bg-white p-6 sm:p-8">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-red-50 text-red-700" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 3h.01M10.3 4.5 3.4 17a2 2 0 0 0 1.75 3h13.7a2 2 0 0 0 1.75-3L13.7 4.5a2 2 0 0 0-3.4 0Z" />
                                        </svg>
                                    </span>
                                    <h3 class="mt-5 font-black text-slate-950">Peringatan terlambat</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">Keterlambatan ditampilkan jelas ketika tanggal estimasi telah terlewati.</p>
                                </div>
                            </section>
                        </div>

                        <section aria-labelledby="how-to-track-heading" class="mx-auto mt-14 max-w-5xl sm:mt-20">
                            <div class="text-center">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">Sederhana & transparan</p>
                                <h2 id="how-to-track-heading" class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Cara melacak pengiriman</h2>
                            </div>

                            <ol class="mt-8 grid gap-4 md:grid-cols-3">
                                <li class="rounded-2xl border border-slate-200 bg-white p-6">
                                    <span class="text-sm font-black text-cyan-700">01</span>
                                    <h3 class="mt-3 font-black text-slate-950">Masukkan nomor</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">Gunakan nomor kontainer yang diberikan oleh petugas.</p>
                                </li>
                                <li class="rounded-2xl border border-slate-200 bg-white p-6">
                                    <span class="text-sm font-black text-cyan-700">02</span>
                                    <h3 class="mt-3 font-black text-slate-950">Buka hasil tracking</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">Sistem menampilkan data pengiriman yang cocok.</p>
                                </li>
                                <li class="rounded-2xl border border-slate-200 bg-white p-6">
                                    <span class="text-sm font-black text-cyan-700">03</span>
                                    <h3 class="mt-3 font-black text-slate-950">Pantau pembaruan</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">Kembali ke halaman ini untuk melihat status berikutnya.</p>
                                </li>
                            </ol>
                        </section>
                    @endif
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-7 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p class="font-semibold text-slate-700">LogiTrack AI</p>
                <p>Informasi tracking berdasarkan pembaruan operasional yang tercatat.</p>
            </div>
        </footer>
    </body>
</html>
