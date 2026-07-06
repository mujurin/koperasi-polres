<?php

use App\Models\Pinjaman;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Pinjaman $pinjaman;

    public function mount(Pinjaman $pinjaman)
    {
        $this->pinjaman = $pinjaman->load([
            'user',
            'angsurans' => function ($query) {
                $query->orderBy('angsuran_ke', 'asc');
            }
        ]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a wire:navigate href="{{ route('pinjaman.index') }}"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:hover:text-white transition-colors">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Riwayat Angsuran</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Rincian pembayaran cicilan pinjaman anggota.</p>
        </div>
        <div class="ml-auto">
            <a href="{{ route('pinjaman.cetak', $pinjaman->id) }}" target="_blank"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 shadow-sm transition-colors">
                <flux:icon name="printer" class="size-4" />
                <span class="hidden sm:inline">Cetak Persetujuan</span>
                <span class="sm:hidden">Cetak</span>
            </a>
        </div>
    </div>

    {{-- Detail Info Card --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-bold text-xl dark:bg-indigo-900/30 dark:text-indigo-400">
                    {{ strtoupper(substr($pinjaman->user?->name ?? '?', 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $pinjaman->user?->name ?? 'User Dihapus' }}
                    </h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 font-mono">NRP:
                        {{ $pinjaman->user?->nrp ?? '-' }}
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col items-start md:items-end gap-1 border-t md:border-t-0 md:border-l border-zinc-100 dark:border-zinc-800 pt-4 md:pt-0 md:pl-6">
                <span class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider font-semibold">Status
                    Pinjaman</span>
                @if($pinjaman->status === 'disetujui')
                    <span
                        class="inline-flex rounded-lg bg-emerald-100 dark:bg-emerald-900/30 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-400">Aktif</span>
                @elseif($pinjaman->status === 'lunas')
                    <span
                        class="inline-flex rounded-lg bg-indigo-100 dark:bg-indigo-900/30 px-3 py-1 text-xs font-bold text-indigo-700 dark:text-indigo-400">Lunas</span>
                @elseif($pinjaman->status === 'ditolak')
                    <span
                        class="inline-flex rounded-lg bg-rose-100 dark:bg-rose-900/30 px-3 py-1 text-xs font-bold text-rose-700 dark:text-rose-400">Ditolak</span>
                @else
                    <span
                        class="inline-flex rounded-lg bg-orange-100 dark:bg-orange-900/30 px-3 py-1 text-xs font-bold text-orange-700 dark:text-orange-400">Proses</span>
                @endif
            </div>
        </div>

        @php
            $lunasCount = $pinjaman->angsurans->where('status_pembayaran', 'lunas')->count();
            $totalJasaTerbayar = 0;
            $totalPokokTerbayar = 0;

            foreach ($pinjaman->angsurans->where('status_pembayaran', 'lunas') as $angs) {
                // Utamakan Jasa (1% dari pokok), sisa bayar masuk Pokok
                $jasa = min($angs->jumlah_bayar, $pinjaman->jumlah_ajuan * 0.01);
                $pokok = max(0, $angs->jumlah_bayar - $jasa);

                $totalJasaTerbayar += $jasa;
                $totalPokokTerbayar += $pokok;
            }

            $pinjamanLama = null;
            $nilaiDanaDitalangi = 0;
            $pinjamanBaru = null;

            if (str_contains(strtolower($pinjaman->keterangan), 'kompensasi')) {
                if (str_starts_with(strtolower($pinjaman->keterangan), '[kompensasi]')) {
                    $pinjamanLama = \App\Models\Pinjaman::where('user_id', $pinjaman->user_id)
                        ->where('id', '<', $pinjaman->id)
                        ->latest('id')
                        ->first();
                    if ($pinjamanLama) {
                        $angsuranPelunasan = \App\Models\Angsuran::where('pinjaman_id', $pinjamanLama->id)
                            ->where('angsuran_ke', 999)
                            ->first();
                        if ($angsuranPelunasan) {
                            $nilaiDanaDitalangi = $angsuranPelunasan->jumlah_bayar;
                        }
                    }
                } else if (str_contains(strtolower($pinjaman->keterangan), 'lunas via kompensasi')) {
                    $pinjamanBaru = \App\Models\Pinjaman::where('user_id', $pinjaman->user_id)
                        ->where('id', '>', $pinjaman->id)
                        ->orderBy('id', 'asc')
                        ->first();
                }
            }
        @endphp

        @if($pinjamanLama)
            <div
                class="mt-6 p-4 rounded-xl border border-orange-200 bg-orange-50 dark:border-orange-900/50 dark:bg-orange-900/20">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white dark:bg-zinc-900 text-orange-600 dark:text-orange-400 border border-orange-100 dark:border-orange-800">
                            <flux:icon name="arrow-path" class="size-5" />
                        </div>
                        <div>
                            <p class="text-xs md:text-sm font-bold text-orange-800 dark:text-orange-400">Pinjaman Berbasis
                                Kompensasi</p>
                            <p class="text-[11px] md:text-xs text-orange-600 dark:text-orange-500 mt-0.5">
                                Pinjaman ini mengkompensasi (menutup) pinjaman lama sebesar <span class="font-bold">Rp
                                    {{ number_format($nilaiDanaDitalangi, 0, ',', '.') }}</span> dari kompensasi baru
                                sebesar <span class="font-bold">Rp
                                    {{ number_format($pinjaman->jumlah_ajuan ?? 0, 0, ',', '.') }}</span>
                            </p>
                        </div>
                    </div>
                    <a wire:navigate href="{{ route('pinjaman.show', $pinjamanLama->id) }}"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-orange-100 px-3 py-2 text-[10px] md:text-xs font-bold text-zinc-900 border border-orange-200 shadow-sm hover:bg-orange-200 transition-colors">
                        Pinjaman Lama
                        <flux:icon name="chevron-right" class="size-3 hidden md:block" />
                    </a>
                </div>
            </div>
        @endif

        @if($pinjamanBaru)
            <div
                class="mt-6 p-4 rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-900/20">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white dark:bg-zinc-900 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800">
                            <flux:icon name="shield-check" class="size-5" />
                        </div>
                        <div>
                            <p class="text-xs md:text-sm font-bold text-emerald-800 dark:text-emerald-400">Dilunasi via
                                Kompensasi</p>
                            <p class="text-[11px] md:text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">
                                Sisa hutang telah ditutup otomatis dengan pencairan dari pinjaman baru sebesar <span
                                    class="font-bold">Rp
                                    {{ number_format($pinjamanBaru->jumlah_ajuan ?? 0, 0, ',', '.') }}</span>.
                            </p>
                        </div>
                    </div>
                    <a wire:navigate href="{{ route('pinjaman.show', $pinjamanBaru->id) }}"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 px-3 py-2 text-[10px] md:text-xs font-bold text-zinc-900 border border-emerald-200 shadow-sm hover:bg-emerald-200 transition-colors">
                        Pinjaman Baru
                        <flux:icon name="chevron-right" class="size-3 hidden md:block" />
                    </a>
                </div>
            </div>
        @endif
        <div
            class="grid grid-cols-2 lg:grid-cols-4 gap-y-6 gap-x-4 mt-6 pt-6 border-t border-zinc-100 dark:border-zinc-800">
            <div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Pokok Diterima</p>
                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-200 mt-1">Rp
                    {{ number_format($pinjaman->jumlah_diterima ?? 0, 0, ',', '.') }}
                </p>
            </div>
            <div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Total Kewajiban</p>
                <p class="text-sm font-bold text-rose-600 dark:text-rose-400 mt-1">
                    @php $totalKewajiban = $pinjaman->jumlah_ajuan + ($pinjaman->tenor * ($pinjaman->jumlah_ajuan * 0.01)); @endphp
                    Rp {{ number_format($totalKewajiban, 0, ',', '.') }}
                </p>
            </div>
            <div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Total Pokok Terbayar</p>
                <p class="text-sm font-bold text-teal-600 dark:text-teal-400 mt-1">Rp
                    {{ number_format($totalPokokTerbayar, 0, ',', '.') }}
                </p>
            </div>
            <div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Total Jasa Terbayar</p>
                <p class="text-sm font-bold text-orange-600 dark:text-orange-400 mt-1">Rp
                    {{ number_format($totalJasaTerbayar, 0, ',', '.') }}
                </p>
            </div>
        </div>

        <div
            class="grid grid-cols-2 lg:grid-cols-4 gap-y-6 gap-x-4 mt-6 pt-6 border-t border-dashed border-zinc-200 dark:border-zinc-800">
            <div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Cicilan Terbayar</p>
                <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ $lunasCount }} / {{ $pinjaman->tenor }}
                </p>
            </div>

            <div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Angsuran / Bulan</p>
                <p class="text-sm font-bold text-indigo-600 dark:text-indigo-400 mt-1">Rp
                    {{ number_format($pinjaman->angsuran_perbulan ?? 0, 0, ',', '.') }}
                </p>
            </div>
            <div>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Tenor Waktu</p>
                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-200 mt-1">{{ $pinjaman->tenor }} Bulan</p>
            </div>
        </div>
    </div>

    {{-- Installment Table --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden shadow-sm">
        <div
            class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 flex justify-between items-center bg-zinc-50/50 dark:bg-zinc-800/20">
            <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Riwayat Pembayaran</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
                <thead class="bg-zinc-50/50 text-[11px] uppercase text-zinc-500 dark:bg-zinc-800/20 dark:text-zinc-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Angsuran Ke-</th>
                        <th class="px-5 py-3 font-semibold">Tanggal Pembayaran</th>
                        <th class="px-5 py-3 font-semibold text-right">Pokok Terbayar</th>
                        <th class="px-5 py-3 font-semibold text-right">Jasa Terbayar</th>
                        <th class="px-5 py-3 font-semibold text-right">Total Angsuran</th>
                        <th class="px-5 py-3 font-semibold text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/60 font-mono text-xs">
                    @forelse($pinjaman->angsurans as $angsuran)
                        @php
                            $jasa = min($angsuran->jumlah_bayar, $pinjaman->jumlah_ajuan * 0.01);
                            $pokok = max(0, $angsuran->jumlah_bayar - $jasa);
                            $kurangTarget = $angsuran->jumlah_bayar < $pinjaman->angsuran_perbulan;
                        @endphp
                        <tr
                            class="{{ $kurangTarget ? 'bg-rose-50/60 hover:bg-rose-100/60 dark:bg-rose-950/30 dark:hover:bg-rose-900/40 text-rose-900 dark:text-rose-100' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/30' }} transition-colors">
                            <td
                                class="px-5 py-3 font-bold {{ $kurangTarget ? 'text-rose-700 dark:text-rose-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                @if($angsuran->angsuran_ke == 999)
                                    <span class="text-orange-600 dark:text-orange-400">Pelunasan Kompensasi</span>
                                @else
                                    Angsuran #{{ $angsuran->angsuran_ke }}
                                @endif
                                @if($kurangTarget)
                                    <flux:icon name="exclamation-triangle" class="size-4 inline-block ml-1.5 text-rose-500" />
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                {{ $angsuran->tanggal_bayar ? $angsuran->tanggal_bayar->format('d M Y') : '-' }}
                            </td>
                            <td
                                class="px-5 py-3 font-medium {{ $kurangTarget ? 'text-rose-700 dark:text-rose-300' : 'text-zinc-600 dark:text-zinc-400' }} text-right">
                                Rp {{ number_format($pokok, 0, ',', '.') }}
                            </td>
                            <td
                                class="px-5 py-3 font-medium {{ $kurangTarget ? 'text-orange-600 dark:text-orange-400' : 'text-rose-600 dark:text-rose-400' }} text-right">
                                + Rp {{ number_format($jasa, 0, ',', '.') }}
                            </td>
                            <td
                                class="px-5 py-3 font-bold {{ $kurangTarget ? 'text-rose-700 dark:text-rose-400' : 'text-indigo-600 dark:text-indigo-400' }} text-right">
                                Rp {{ number_format($angsuran->jumlah_bayar ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if($angsuran->status_pembayaran === 'lunas')
                                    <span
                                        class="inline-flex rounded bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-400 shadow-sm border border-emerald-200 dark:border-emerald-800/50">LUNAS</span>
                                @else
                                    <span
                                        class="inline-flex rounded bg-orange-100 dark:bg-orange-900/30 px-2 py-0.5 text-[10px] font-bold text-orange-700 dark:text-orange-400 shadow-sm border border-orange-200 dark:border-orange-800/50">TERTUNDA</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center bg-zinc-50/50 dark:bg-zinc-900/50">
                                <div class="flex flex-col items-center justify-center text-zinc-400 dark:text-zinc-500">
                                    <flux:icon name="clock" class="size-6 mb-2 opacity-50" />
                                    <p class="text-xs font-semibold">Belum ada riwayat cicilan untuk pinjaman ini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>