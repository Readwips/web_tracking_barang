<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LogiTrack AI') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900">
        <div class="relative min-h-screen flex flex-col items-center justify-center">
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3">
                    <div class="flex lg:justify-start">
                        <a href="{{ route('tracking.index') }}" class="group inline-flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-600 text-white shadow-lg transition group-hover:bg-cyan-500">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 17h16M5.5 17 7 10h10l1.5 7M9 10V6h6v4M7 20h10" />
                                </svg>
                            </span>
                            <span>
                                <span class="block text-base font-black tracking-tight text-slate-900">LogiTrack</span>
                                <span class="block text-[0.625rem] font-bold uppercase tracking-[0.24em] text-cyan-600">Smart Tracking</span>
                            </span>
                        </a>
                    </div>
                    @if (Route::has('login'))
                        <nav class="-mx-3 flex flex-1 justify-end lg:col-span-2">
                            @auth
                                <a
                                    href="{{ url('/dashboard') }}"
                                    class="rounded-md px-3 py-2 text-slate-700 ring-1 ring-transparent transition hover:text-slate-900 focus:outline-none focus-visible:ring-cyan-500 font-semibold"
                                >
                                    Dashboard
                                </a>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="rounded-md px-3 py-2 text-slate-700 ring-1 ring-transparent transition hover:text-slate-900 focus:outline-none focus-visible:ring-cyan-500 font-semibold"
                                >
                                    Login Petugas
                                </a>
                            @endauth
                        </nav>
                    @endif
                </header>

                <main class="mt-6 mb-16">
                    <div class="grid gap-12 lg:grid-cols-2 lg:gap-8 items-center h-full">
                        <div class="flex flex-col justify-center text-center lg:text-left">
                            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                                Pantau Perjalanan Kontainer Secara Real-time
                            </h1>
                            <p class="mt-6 text-lg leading-8 text-slate-600">
                                LogiTrack AI memberikan visibilitas penuh atas operasional logistik Anda. Dapatkan informasi terkini mengenai lokasi, estimasi kedatangan, dan peringatan keterlambatan.
                            </p>
                            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 sm:gap-6">
                                <a href="{{ route('tracking.index') }}" class="w-full sm:w-auto rounded-xl bg-cyan-600 px-8 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition text-center">
                                    Lacak Kontainer
                                </a>
                                @guest
                                    <a href="{{ route('login') }}" class="w-full sm:w-auto text-sm font-semibold leading-6 text-slate-700 transition hover:text-slate-900 text-center">
                                        Masuk sebagai Petugas <span aria-hidden="true">→</span>
                                    </a>
                                @endguest
                            </div>
                        </div>

                        <div class="relative lg:col-span-1 flex justify-center lg:justify-end">
                            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/5">
                                <div class="p-8">
                                    <div class="flex items-center justify-between mb-8 border-b border-slate-100 pb-4">
                                        <h3 class="text-lg font-bold text-slate-900">Demo Tracking</h3>
                                        <span class="inline-flex items-center rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700 ring-1 ring-inset ring-cyan-600/20">Live Preview</span>
                                    </div>
                                    <div class="space-y-6">
                                        <div class="flex gap-4">
                                            <div class="flex-none">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-900">Tiba di pelabuhan tujuan</h4>
                                                <p class="mt-1 text-sm text-slate-500">TANTO-CT-000124 telah tiba di Makassar.</p>
                                                <p class="mt-2 text-xs font-medium text-slate-400">Hari ini, 10:30 WIB</p>
                                            </div>
                                        </div>
                                        <div class="flex gap-4 opacity-60 relative before:absolute before:left-5 before:top-10 before:bottom-[-24px] before:w-0.5 before:-ml-px before:bg-slate-200">
                                            <div class="flex-none z-10">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-900">Dalam perjalanan</h4>
                                                <p class="mt-1 text-sm text-slate-500">Kapal sedang transit di Pelabuhan Tanjung Perak.</p>
                                                <p class="mt-2 text-xs font-medium text-slate-400">Kemarin, 14:15 WIB</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                                        <a href="{{ route('tracking.show', 'TANTO-CT-000124') }}" class="text-sm font-bold text-cyan-600 hover:text-cyan-500 transition inline-flex items-center gap-1">
                                            Lihat detail lengkap
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                <footer class="py-8 text-center text-sm font-medium text-slate-500 border-t border-slate-200 mt-auto">
                    &copy; {{ date('Y') }} LogiTrack AI. Hak cipta dilindungi.
                </footer>
            </div>
        </div>
    </body>
</html>