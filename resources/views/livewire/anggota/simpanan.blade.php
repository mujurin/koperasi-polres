<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.anggota')] class extends Component {

    public function with(): array
    {
        $user = Auth::user();
        $totalPokok = $user->simpananPokok?->jumlah ?? 0;
        $totalWajib = $user->simpananWajib()->sum('jumlah');
        $totalTarik = $user->totalPenarikan();
        $saldo = $user->saldoAkhir();
        $hasPokok = $user->simpananPokok !== null;

        return compact('totalPokok', 'totalWajib', 'totalTarik', 'saldo', 'hasPokok');
    }
}; ?>

<div class="flex flex-col gap-4 p-4">

    {{-- Header --}}
    <div>
        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Rekap Simpanan</p>
    </div>

    {{-- Saldo card --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-600 p-5 shadow-md">
        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10"></div>
        <p class="text-xs text-blue-200 mb-1">Saldo Akhir</p>
        <p class="text-3xl font-bold text-white">Rp {{ number_format($saldo, 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-blue-200">Pokok + Wajib − Penarikan</p>
    </div>

    {{-- Detail cards --}}
    <div class="grid grid-cols-1 gap-3">

        {{-- Pokok --}}
        <div
            class="flex items-center justify-between rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm px-4 py-3.5">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-950/50">
                    <svg class="size-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path d="M3 21h18M9 8h1m5 0h1M3 7l9-4 9 4M6 21V10m12 11V10M10 21v-6h4v6" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Simpanan Pokok</p>
                    <p class="text-xs text-zinc-400">{{ $hasPokok ? 'Sudah dibayar' : 'Belum dibayar' }}</p>
                </div>
            </div>
            <p class="font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format($totalPokok, 0, ',', '.') }}</p>
        </div>

        {{-- Wajib --}}
        <div
            class="flex items-center justify-between rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm px-4 py-3.5">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-950/50">
                    <svg class="size-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Simpanan Wajib</p>
                    <p class="text-xs text-zinc-400">Total akumulasi</p>
                </div>
            </div>
            <p class="font-bold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($totalWajib, 0, ',', '.') }}
            </p>
        </div>

        {{-- Penarikan --}}
        <div
            class="flex items-center justify-between rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm px-4 py-3.5">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-950/50">
                    <svg class="size-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path d="M7 11l5-5m0 0l5 5m-5-5v12" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">Total Penarikan</p>
                    <p class="text-xs text-zinc-400">Total sudah ditarik</p>
                </div>
            </div>
            <p class="font-bold text-rose-700 dark:text-rose-300">Rp {{ number_format($totalTarik, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Tip --}}
    <div
        class="rounded-xl border border-indigo-100 dark:border-indigo-800/50 bg-indigo-50 dark:bg-indigo-950/30 p-3.5 flex items-start gap-2.5">
        <svg class="size-4 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
        </svg>
        <p class="text-xs text-indigo-700 dark:text-indigo-300 leading-relaxed">
            Saldo akhir dihitung dari total simpanan pokok + wajib dikurangi total penarikan. Untuk setor atau tarik,
            gunakan menu Aksi Cepat di beranda.
        </p>
    </div>

    <div class="h-4"></div>
</div>