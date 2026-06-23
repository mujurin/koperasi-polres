<?php

use App\Models\SimpananWajib;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.anggota')] class extends Component {

    public function with(): array
    {
        $user = Auth::user();

        $totalPokok = $user->simpananPokok?->jumlah ?? 0;
        $totalWajib = $user->simpananWajib()->sum('jumlah');
        $totalTarik = $user->penarikan()->sum('jumlah');
        $saldo = ($totalPokok + $totalWajib) - $totalTarik;

        $riwayatWajib = $user->simpananWajib()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();

        $riwayatTarik = $user->penarikan()
            ->orderByDesc('tanggal')
            ->get();

        return compact('totalPokok', 'totalWajib', 'totalTarik', 'saldo', 'riwayatWajib', 'riwayatTarik');
    }
}; ?>

<div class="flex flex-col gap-4 p-4">

    {{-- Header --}}
    <div>
        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Riwayat Lengkap</p>
    </div>

    {{-- Simpanan Wajib --}}
    <div
        class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div
            class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-emerald-50/60 dark:bg-emerald-950/20">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 text-white">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Simpanan Wajib</p>
            </div>
            <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300">Total: Rp
                {{ number_format($totalWajib, 0, ',', '.') }}</span>
        </div>

        @if($riwayatWajib->isEmpty())
            <div class="py-8 text-center text-zinc-400">
                <p class="text-sm">Belum ada simpanan wajib</p>
            </div>
        @else
            @foreach($riwayatWajib as $i => $item)
                <div class="flex items-center justify-between px-4 py-3
                            {{ $i < $riwayatWajib->count() - 1 ? 'border-b border-zinc-100 dark:border-zinc-800' : '' }}">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                            <span
                                class="text-[8px] font-bold text-emerald-700">{{ str_pad($item->bulan, 2, '0', STR_PAD_LEFT) }}</span>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ SimpananWajib::namaBulan($item->bulan) }} {{ $item->tahun }}
                            </p>
                            @if($item->keterangan)
                                <p class="text-xs text-zinc-400">{{ $item->keterangan }}</p>
                            @endif
                        </div>
                    </div>
                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                        +Rp {{ number_format($item->jumlah, 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Penarikan --}}
    <div
        class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div
            class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-rose-50/60 dark:bg-rose-950/20">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-500 text-white">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path d="M7 11l5-5m0 0l5 5m-5-5v12" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Riwayat Penarikan</p>
            </div>
            <span class="text-xs font-bold text-rose-700 dark:text-rose-300">Total: Rp
                {{ number_format($totalTarik, 0, ',', '.') }}</span>
        </div>

        @if($riwayatTarik->isEmpty())
            <div class="py-8 text-center text-zinc-400">
                <p class="text-sm">Belum ada penarikan</p>
            </div>
        @else
            @foreach($riwayatTarik as $i => $item)
                <div class="flex items-center justify-between px-4 py-3
                            {{ $i < $riwayatTarik->count() - 1 ? 'border-b border-zinc-100 dark:border-zinc-800' : '' }}">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/40">
                            <svg class="size-3 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M7 11l5-5m0 0l5 5m-5-5v12" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $item->tanggal->format('d M Y') }}</p>
                            @if($item->keterangan)
                                <p class="text-xs text-zinc-400">{{ $item->keterangan }}</p>
                            @endif
                        </div>
                    </div>
                    <p class="text-sm font-bold text-rose-600 dark:text-rose-400">
                        -Rp {{ number_format($item->jumlah, 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Simpanan Pokok --}}
    <div
        class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div
            class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-blue-50/60 dark:bg-blue-950/20">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500 text-white">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path d="M3 21h18M9 8h1m5 0h1M3 7l9-4 9 4M6 21V10m12 11V10M10 21v-6h4v6" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Simpanan Pokok</p>
            </div>
        </div>
        <div class="px-4 py-3">
            @php $pokok = auth()->user()->simpananPokok; @endphp
            @if($pokok)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-zinc-400">Dibayar {{ $pokok->tanggal->format('d M Y') }}</p>
                        @if($pokok->keterangan)
                            <p class="text-xs text-zinc-400 mt-0.5">{{ $pokok->keterangan }}</p>
                        @endif
                    </div>
                    <p class="text-base font-bold text-blue-700 dark:text-blue-300">Rp
                        {{ number_format($pokok->jumlah, 0, ',', '.') }}</p>
                </div>
                <div
                    class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5">
                    <svg class="size-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="3">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-300">Sudah Dibayar</span>
                </div>
            @else
                <div class="flex items-center justify-between">
                    <p class="text-sm text-zinc-500">Belum membayar simpanan pokok</p>
                    <a href="{{ route('simpanan.pokok') }}" wire:navigate
                        class="inline-flex items-center rounded-lg bg-blue-500 px-3 py-1.5 text-xs font-semibold text-white">
                        Bayar
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="h-4"></div>
</div>