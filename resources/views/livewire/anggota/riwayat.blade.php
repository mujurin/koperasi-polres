<?php

use App\Models\SimpananWajib;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.anggota')] class extends Component {

    public function with(): array
    {
        $user = Auth::user();

        $totalWajib = $user->simpananWajib()->sum('jumlah');
        $totalTarik = $user->penarikan()->sum('jumlah');

        $riwayatWajib = $user->simpananWajib()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->take(5)
            ->get();

        $riwayatTarik = $user->penarikan()
            ->orderByDesc('tanggal')
            ->get();

        $riwayatPinjaman = $user->pinjaman()
            ->orderByDesc('created_at')
            ->get();

        return compact('totalWajib', 'totalTarik', 'riwayatWajib', 'riwayatTarik', 'riwayatPinjaman');
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

    {{-- Riwayat Pinjaman --}}
    <div
        class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div
            class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-blue-50/60 dark:bg-blue-950/20">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500 text-white">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Riwayat Pinjaman</p>
            </div>
            <span class="text-xs font-bold text-blue-700 dark:text-blue-300">{{ $riwayatPinjaman->count() }} Ajuan</span>
        </div>

        @if($riwayatPinjaman->isEmpty())
            <div class="py-8 text-center text-zinc-400">
                <p class="text-sm">Belum ada riwayat pinjaman</p>
            </div>
        @else
            @foreach($riwayatPinjaman as $i => $item)
                <div class="flex items-center justify-between px-4 py-3
                            {{ $i < $riwayatPinjaman->count() - 1 ? 'border-b border-zinc-100 dark:border-zinc-800' : '' }}">
                    <div class="flex items-center gap-2.5">
                        <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg 
                            @if($item->status === 'disetujui') bg-emerald-100 dark:bg-emerald-900/40
                            @elseif($item->status === 'ditolak') bg-rose-100 dark:bg-rose-900/40
                            @else bg-orange-100 dark:bg-orange-900/40 @endif">
                            
                            @if($item->status === 'disetujui')
                                <svg class="size-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
                            @elseif($item->status === 'ditolak')
                                <svg class="size-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12"/></svg>
                            @else
                                <svg class="size-3.5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                Rp {{ number_format($item->jumlah_ajuan, 0, ',', '.') }}
                            </p>
                            <p class="text-[10px] text-zinc-400 mt-0.5">
                                {{ $item->tenor }} bln &bull; {{ $item->created_at->format('d M Y') }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        @if($item->status === 'disetujui')
                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-semibold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400">Disetujui</span>
                        @elseif($item->status === 'ditolak')
                            <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-semibold text-rose-600 border border-rose-200 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-400">Ditolak</span>
                        @else
                            <span class="inline-flex rounded-full bg-orange-50 px-2 py-0.5 text-[9px] font-semibold text-orange-600 border border-orange-200 dark:border-orange-800/50 dark:bg-orange-950/40 dark:text-orange-400">Proses</span>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <div class="h-4"></div>
</div>