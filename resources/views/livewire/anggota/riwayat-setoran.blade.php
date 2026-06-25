<?php

use App\Models\Angsuran;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.anggota')] class extends Component {

    public function with(): array
    {
        $user = Auth::user();

        $riwayatSetoran = Angsuran::whereHas('pinjaman', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with('pinjaman')
            ->orderByDesc('tanggal_bayar')
            ->get();

        $totalSetoran = $riwayatSetoran->where('status_pembayaran', 'Lunas')->sum('jumlah_bayar');

        $pinjamanAktif = \App\Models\Pinjaman::where('user_id', $user->id)
            ->whereIn('status', ['disetujui', 'proses'])
            ->latest()
            ->first();

        $sisaPinjaman = 0;
        $statusSisa = 'Tidak Ada Pinjaman Aktif';
        $statusSisaPokok = '-';

        if ($pinjamanAktif && $pinjamanAktif->status === 'disetujui') {
            $totalKewajiban = $pinjamanAktif->jumlah_ajuan + ($pinjamanAktif->tenor * ($pinjamanAktif->jumlah_ajuan * 0.01));
            
            $angsuransLunas = Angsuran::where('pinjaman_id', $pinjamanAktif->id)
                ->where('status_pembayaran', 'Lunas')
                ->get();

            $totalBayarLunas = $angsuransLunas->sum('jumlah_bayar');

            $totalPokokTerbayar = 0;
            foreach ($angsuransLunas as $angs) {
                $jasa = min($angs->jumlah_bayar, $pinjamanAktif->jumlah_ajuan * 0.01);
                $pokok = max(0, $angs->jumlah_bayar - $jasa);
                $totalPokokTerbayar += $pokok;
            }

            $sisaPinjaman = max(0, $totalKewajiban - $totalBayarLunas);
            $sisaPokok = max(0, $pinjamanAktif->jumlah_ajuan - $totalPokokTerbayar);

            $statusSisa = 'Rp ' . number_format($sisaPinjaman, 0, ',', '.');
            $statusSisaPokok = 'Rp ' . number_format($sisaPokok, 0, ',', '.');
        } elseif ($pinjamanAktif && $pinjamanAktif->status === 'proses') {
            $statusSisa = 'Sedang Diproses';
            $statusSisaPokok = 'Sedang Diproses';
        }

        return compact('riwayatSetoran', 'totalSetoran', 'statusSisa', 'statusSisaPokok');
    }
}; ?>

<div class="flex flex-col gap-4 p-4 pb-24">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('anggota.dashboard') }}" wire:navigate
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 shadow-sm transition-all dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white leading-tight">Riwayat Setoran</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Daftar setoran pinjaman Anda</p>
        </div>
    </div>

    {{-- Sisa Pinjaman Card --}}
    <div class="rounded-2xl border border-rose-100 dark:border-rose-900/50 bg-rose-50 dark:bg-rose-950/20 p-4 shadow-sm">
        <div class="flex justify-between items-center border-b border-rose-200/60 dark:border-rose-900/40 pb-3 mb-3">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-rose-800 dark:text-rose-200">Kewajiban Pinjaman</h3>
                    <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-0.5">Sisa Pokok + Bunga Jasa</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-rose-700 dark:text-rose-300">{{ $statusSisa }}</p>
            </div>
        </div>
        <div class="flex justify-between items-center px-1">
            <div class="flex items-center gap-2">
                <h3 class="text-xs font-semibold text-rose-700/80 dark:text-rose-300/80">Sisa Pokok Saja</h3>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-rose-700/80 dark:text-rose-300/80">{{ $statusSisaPokok }}</p>
            </div>
        </div>
    </div>

    {{-- Setoran List --}}
    <div class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden mt-1">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-indigo-50/60 dark:bg-indigo-950/20">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500 text-white">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Riwayat Pembayaran</p>
            </div>
            <div class="text-right">
                <span class="block text-[10px] text-zinc-500 dark:text-zinc-400">Total Setoran</span>
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">Rp {{ number_format($totalSetoran, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($riwayatSetoran->isEmpty())
            <div class="py-8 text-center text-zinc-400">
                <p class="text-sm">Belum ada riwayat setoran</p>
            </div>
        @else
            @foreach($riwayatSetoran as $i => $item)
                <div class="flex items-center justify-between px-4 py-3
                            {{ $i < $riwayatSetoran->count() - 1 ? 'border-b border-zinc-100 dark:border-zinc-800' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-50 dark:bg-zinc-800/50">
                            <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">Ke-{{ $item->angsuran_ke }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                Angsuran Pinjaman
                            </p>
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                {{ \Carbon\Carbon::parse($item->tanggal_bayar)->format('d M Y') }}
                            </p>
                            @if($item->status_pembayaran === 'Lunas')
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 mt-1 py-0.5 text-[10px] font-semibold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400">Diterima</span>
                            @else
                                <span class="inline-flex rounded-full bg-orange-50 px-2 mt-1 py-0.5 text-[10px] font-semibold text-orange-600 border border-orange-200 dark:border-orange-800/50 dark:bg-orange-950/40 dark:text-orange-400">{{ $item->status_pembayaran }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            +Rp {{ number_format($item->jumlah_bayar, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
