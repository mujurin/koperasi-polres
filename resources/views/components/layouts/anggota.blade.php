<!DOCTYPE html>
<html lang="id" class="">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>PRIMKOPPOL LOTARA — Simpanan Saya</title>

    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-950 antialiased">

    {{-- ═══════════════════════════════════════════════════════
    PWA TOP BAR
    ════════════════════════════════════════════════════════════ --}}
    <div class="sticky top-0 z-50 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 max-w-lg mx-auto">
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800 shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <img src="{{ asset('logo.png') }}" class="h-5 w-auto object-contain" alt="Logo" />
                </div>
                <div>
                    <p class="text-sm font-bold text-zinc-900 dark:text-white leading-none">PRIMKOPPOL LOTARA</p>
                    <p class="text-[10px] text-zinc-500 dark:text-zinc-400 leading-tight mt-0.5">"Rencanakanlah
                        Kebutuhan Anda dengan Seksama"</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="text-right mr-1">
                    <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 leading-none">
                        {{ auth()->user()->name }}
                    </p>
                    <p class="text-[10px] text-zinc-400 font-mono leading-none mt-0.5">{{ auth()->user()->nrp }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-500 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/50 transition-colors">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- PAGE CONTENT --}}
    <main class="max-w-lg mx-auto pb-24">
        {{ $slot }}
    </main>

    {{-- ═══════════════════════════════════════════════════════
    PWA BOTTOM NAVIGATION
    ════════════════════════════════════════════════════════════ --}}
    <nav
        class="fixed bottom-0 left-0 right-0 z-50 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 pb-safe">
        <div class="flex justify-around max-w-lg mx-auto">

            <a href="{{ route('anggota.dashboard') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-1 py-2.5 transition-colors
                      {{ request()->routeIs('anggota.dashboard') ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-700' }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1" />
                    <rect x="14" y="3" width="7" height="7" rx="1" />
                    <rect x="3" y="14" width="7" height="7" rx="1" />
                    <rect x="14" y="14" width="7" height="7" rx="1" />
                </svg>
                <span class="text-[10px] font-medium">Beranda</span>
                @if(request()->routeIs('anggota.dashboard'))
                    <span
                        class="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                @endif
            </a>

            <a href="{{ route('anggota.simpanan') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-1 py-2.5 transition-colors
                      {{ request()->routeIs('anggota.simpanan') ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-700' }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z" />
                </svg>
                <span class="text-[10px] font-medium">Simpanan</span>
                @if(request()->routeIs('anggota.simpanan'))
                    <span
                        class="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                @endif
            </a>

            <a href="{{ route('anggota.riwayat-setoran') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-1 py-2.5 transition-colors
                      {{ request()->routeIs('anggota.riwayat-setoran') ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-700' }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-[10px] font-medium">Setoran</span>
                @if(request()->routeIs('anggota.riwayat-setoran'))
                    <span
                        class="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                @endif
            </a>

            <a href="{{ route('anggota.riwayat') }}" wire:navigate
                class="flex flex-1 flex-col items-center gap-1 py-2.5 transition-colors
                      {{ request()->routeIs('anggota.riwayat') ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-400 dark:text-zinc-500 hover:text-zinc-700' }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                <span class="text-[10px] font-medium">Riwayat</span>
                @if(request()->routeIs('anggota.riwayat'))
                    <span
                        class="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
                @endif
            </a>

        </div>
    </nav>

    @fluxScripts
</body>

</html>