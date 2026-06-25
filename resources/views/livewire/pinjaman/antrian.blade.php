<?php

use App\Models\Pinjaman;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public function with(): array
    {
        $antrian = Pinjaman::with('user')
            ->where('status', 'proses')
            ->orderBy('created_at', 'asc')
            ->get();

        return compact('antrian');
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Antrian Pinjaman</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Daftar permohonan pinjaman anggota yang menunggu
            persetujuan.</p>
    </div>

    {{-- Daftar Antrian --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">

        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 px-5 py-4">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/50">
                    <flux:icon name="clock" class="size-4 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-white text-sm">Menunggu Persetujuan</h2>
                    <p class="text-xs text-zinc-400">{{ $antrian->count() }} pengajuan pinjaman</p>
                </div>
            </div>
        </div>

        @if($antrian->isEmpty())
            <div class="py-16 text-center">
                <div
                    class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="check-circle" class="size-7 text-zinc-400" />
                </div>
                <p class="font-medium text-zinc-600 dark:text-zinc-400">Semua permohonan sudah diproses</p>
                <p class="mt-1 text-xs text-zinc-400">Saat ini tidak ada antrian permohonan pinjaman baru.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-zinc-50 dark:bg-zinc-800/60">
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Tanggal</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Pemohon</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Pengajuan Awal (Rp)</th>
                            <th
                                class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Rincian</th>
                            <th
                                class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($antrian as $pinjaman)
                            <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition-colors">
                                <td class="px-5 py-3 text-zinc-500 dark:text-zinc-400 text-xs">
                                    {{ $pinjaman->created_at->format('d M Y') }}<br>
                                    {{ $pinjaman->created_at->format('H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/40">
                                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                                                {{ strtoupper(substr($pinjaman->user->name ?? '?', 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-zinc-800 dark:text-zinc-200 text-sm">
                                                {{ $pinjaman->user->name ?? 'User Dihapus' }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <p class="text-xs text-zinc-400 font-mono">{{ $pinjaman->user->nrp ?? '-' }}</p>
                                                @if($pinjaman->user && $pinjaman->user->pinjaman()->where('status', 'disetujui')->where('id', '!=', $pinjaman->id)->exists())
                                                    <span class="inline-flex rounded-full bg-orange-100 px-1.5 py-0.5 text-[9px] font-bold text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 uppercase tracking-wider">Kompensasi</span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 uppercase tracking-wider">Baru</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">Rp
                                        {{ number_format($pinjaman->jumlah_ajuan, 0, ',', '.') }}</span>
                                    <p class="text-[10px] text-zinc-500 mt-0.5">Diterima: Rp
                                        {{ number_format($pinjaman->jumlah_diterima, 0, ',', '.') }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-center text-xs text-zinc-600 dark:text-zinc-400">
                                    <span
                                        class="inline-flex rounded-md bg-zinc-100 dark:bg-zinc-800 px-2 py-1 font-medium">{{ $pinjaman->tenor }}
                                        Bulan</span>
                                    <span
                                        class="inline-flex rounded-md {{ $pinjaman->jenis_permohonan === 'Urgent' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' }} px-2 py-1 font-medium ml-1">{{ $pinjaman->jenis_permohonan }}</span>
                                    <div class="mt-1 max-w-[200px] truncate mx-auto italic text-[10px]">
                                        {{ $pinjaman->keterangan ?: 'Tidak ada keterangan' }}
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <a wire:navigate href="{{ route('pinjaman.review', $pinjaman->id) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors dark:border-indigo-800/50 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/40 shadow-sm">
                                        <flux:icon name="magnifying-glass" class="size-4" />
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Modal Review --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" class="relative z-50" aria-labelledby="modal-title"
        role="dialog" aria-modal="true" style="display: none;">
        <!-- Backdrop -->
        <div x-show="show" x-transition.opacity
            class="fixed inset-0 bg-zinc-900/80 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal panel -->
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="show" x-transition
                    class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 w-full sm:max-w-md dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">

                    <div
                        class="border-b border-zinc-100 dark:border-zinc-800 px-6 py-4 flex justify-between items-center bg-zinc-50/50 dark:bg-zinc-800/20">
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white" id="modal-title">Review Pengajuan
                            Pinjaman</h3>
                        <button wire:click="$set('showModal', false)" </div>
                    </div>