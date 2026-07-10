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
        $totalTarik = $user->totalPenarikan();
        $saldo = $user->saldoAkhir();

        $riwayatTarik = $user->penarikan()
            ->orderByDesc('tanggal')
            ->take(5)
            ->get();

        $riwayatWajib = $user->simpananWajib()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->take(5)
            ->get();

        $pengajuanPinjaman = $user->pinjaman()
            ->withCount(['angsurans' => function ($query) {
                $query->where('status_pembayaran', 'Lunas');
            }])
            ->orderByDesc('created_at')
            ->take(3)
            ->get();

        return compact(
            'totalPokok',
            'totalWajib',
            'totalTarik',
            'saldo',
            'riwayatTarik',
            'riwayatWajib',
            'pengajuanPinjaman'
        );
    }
}; ?>

<div class="flex flex-col gap-0">

    {{-- ═══════════════════════════════════════════════════════
    HERO GRADIENT CARD — SALDO
    ════════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden bg-white px-5 pt-7 pb-16 border-b border-zinc-100 dark:bg-zinc-900 dark:border-zinc-800">
        {{-- decorative circles --}}
        <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-indigo-50/50 dark:bg-indigo-900/10"></div>
        <div class="absolute -right-4 top-16 h-24 w-24 rounded-full bg-blue-50/50 dark:bg-blue-900/10"></div>
        <div class="absolute left-0 bottom-0 h-20 w-full bg-gradient-to-t from-zinc-50 to-transparent dark:from-zinc-900"></div>

        <div class="relative">
            <p class="text-xs font-semibold text-zinc-500 uppercase tracking-widest mb-1">Saldo Koperasi Saya</p>
            <p class="text-4xl font-bold text-zinc-900 dark:text-white tracking-tight">Rp {{ number_format($saldo, 0, ',', '.') }}</p>
            <p class="mt-1.5 text-sm text-zinc-500">per {{ now()->translatedFormat('d F Y') }}</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    STAT CARDS — pulled up with negative margin
    ════════════════════════════════════════════════════════════ --}}
    <div class="relative -mt-10 px-4">
        <div
            class="grid grid-cols-3 gap-3 rounded-2xl bg-white dark:bg-zinc-900 shadow-lg border border-zinc-100 dark:border-zinc-800 divide-x divide-zinc-100 dark:divide-zinc-800 overflow-hidden">

            {{-- Simpanan Pokok --}}
            <div class="flex flex-col items-center py-4 px-2">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-950/50">
                    <svg class="size-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path d="M3 21h18M9 8h1m5 0h1M3 7l9-4 9 4M6 21V10m12 11V10M10 21v-6h4v6" />
                    </svg>
                </div>
                <p class="text-[10px] text-zinc-400 font-medium mb-0.5">Pokok</p>
                <p class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Rp
                    {{ number_format($totalPokok, 0, ',', '.') }}
                </p>
            </div>

            {{-- Simpanan Wajib --}}
            <div class="flex flex-col items-center py-4 px-2">
                <div
                    class="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/50">
                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-[10px] text-zinc-400 font-medium mb-0.5">Wajib</p>
                <p class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Rp
                    {{ number_format($totalWajib, 0, ',', '.') }}
                </p>
            </div>

            {{-- Penarikan --}}
            <div class="flex flex-col items-center py-4 px-2">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 dark:bg-rose-950/50">
                    <svg class="size-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path d="M7 11l5-5m0 0l5 5m-5-5v12" />
                    </svg>
                </div>
                <p class="text-[10px] text-zinc-400 font-medium mb-0.5">Tarik</p>
                <p class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Rp
                    {{ number_format($totalTarik, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    QUICK ACTIONS
    ════════════════════════════════════════════════════════════ --}}
    <div class="px-4 mt-6">
        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-3">Aksi Cepat</p>
        <div class="grid grid-cols-2 gap-3">
            <div x-data="{ showMenu: false }" class="relative">
                <button @click="showMenu = true" type="button" class="flex flex-col items-center gap-2 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 p-4 shadow-sm hover:shadow-md transition-all active:scale-95 w-full">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-500 text-white shadow-sm">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-[10px] font-semibold text-zinc-600 dark:text-zinc-400 text-center leading-tight">Permohonan<br>Pinjaman</span>
                </button>

                <template x-teleport="body">
                    <div x-show="showMenu" style="display: none;" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity">
                        <div x-show="showMenu" x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 opacity-0"
                            x-transition:enter-end="translate-y-0 sm:scale-100 opacity-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="translate-y-0 sm:scale-100 opacity-100"
                            x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-95 opacity-0"
                            @click.away="showMenu = false"
                            class="w-full max-w-md bg-white dark:bg-zinc-900 rounded-t-3xl sm:rounded-2xl shadow-2xl p-5 flex flex-col gap-4 border border-zinc-200 dark:border-zinc-800">
                            
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Jenis Pinjaman</h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Pilih skema permohonan pinjaman.</p>
                                </div>
                                <button @click="showMenu = false" class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200 transition-colors">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div class="flex flex-col gap-3 pb-2">
                                <a href="{{ route('anggota.pinjaman', ['type' => 'baru']) }}" wire:navigate class="group flex items-start gap-4 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 hover:border-orange-500 dark:hover:border-orange-500 bg-white dark:bg-zinc-900 hover:bg-orange-50 dark:hover:bg-orange-950/20 transition-all text-left w-full active:scale-[0.98]">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-900/50 dark:text-orange-400 group-hover:scale-110 transition-transform">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    </div>
                                    <div class="flex-1 mt-0.5">
                                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">Pengajuan Baru</h4>
                                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">Pilih ini jika Anda belum memiliki pinjaman berjalan.</p>
                                    </div>
                                </a>
                                
                                <a href="{{ route('anggota.pinjaman', ['type' => 'kompensasi']) }}" wire:navigate class="group flex items-start gap-4 p-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 hover:border-indigo-500 dark:hover:border-indigo-500 bg-white dark:bg-zinc-900 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 transition-all text-left w-full active:scale-[0.98]">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    </div>
                                    <div class="flex-1 mt-0.5">
                                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Kompensasi (Top-up)</h4>
                                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">Pengajuan baru sekaligus melunasi sisa pinjaman lama Anda.</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <a href="{{ route('anggota.penarikan') }}" wire:navigate
                class="flex flex-col items-center gap-2 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 p-4 shadow-sm hover:shadow-md transition-all active:scale-95">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-500 text-white shadow-sm">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M7 11l5-5m0 0l5 5m-5-5v12" />
                    </svg>
                </div>
                <span class="text-[10px] font-semibold text-zinc-600 dark:text-zinc-400 text-center leading-tight">Tarik
                    Dana</span>
            </a>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    RIWAYAT TERBARU
    ════════════════════════════════════════════════════════════ --}}
    <div class="px-4 mt-6">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Transaksi Terbaru</p>
            <a href="{{ route('anggota.riwayat') }}" wire:navigate
                class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Lihat semua</a>
        </div>

        <div
            class="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden">

            @php
                // Merge wajib dan tarik, sort by date/period desc
                $transaksi = collect();
                foreach ($riwayatWajib as $w) {
                    $transaksi->push([
                        'type' => 'wajib',
                        'label' => 'Simpanan Wajib · ' . \App\Models\SimpananWajib::namaBulan($w->bulan) . ' ' . $w->tahun,
                        'jumlah' => $w->jumlah,
                        'sort' => $w->tahun . str_pad($w->bulan, 2, '0', STR_PAD_LEFT),
                        'arah' => '+',
                    ]);
                }
                foreach ($riwayatTarik as $t) {
                    $transaksi->push([
                        'type' => 'tarik',
                        'label' => 'Penarikan' . ($t->keterangan ? ' · ' . $t->keterangan : ''),
                        'jumlah' => $t->jumlah,
                        'sort' => $t->tanggal->format('Ymd'),
                        'arah' => '-',
                    ]);
                }
                $transaksi = $transaksi->sortByDesc('sort')->take(2)->values();
            @endphp

            @if($transaksi->isEmpty())
                <div class="py-10 text-center">
                    <p class="text-sm text-zinc-400">Belum ada transaksi</p>
                </div>
            @else
                @foreach($transaksi as $i => $tx)
                    <div
                        class="flex items-center gap-3 px-4 py-3 {{ $i < $transaksi->count() - 1 ? 'border-b border-zinc-100 dark:border-zinc-800' : '' }}">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                                                            {{ $tx['type'] === 'wajib' ? 'bg-emerald-100 dark:bg-emerald-950/50' : 'bg-rose-100 dark:bg-rose-950/50' }}">
                            @if($tx['type'] === 'wajib')
                                <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path d="M12 20l-7-7 7-7" />
                                </svg>
                            @else
                                <svg class="size-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path d="M12 4l7 7-7 7" />
                                </svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200 truncate">{{ $tx['label'] }}</p>
                        </div>
                        <p class="text-sm font-bold shrink-0
                                                            {{ $tx['arah'] === '+' ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $tx['arah'] }}Rp {{ number_format($tx['jumlah'], 0, ',', '.') }}
                        </p>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    PROGRES PENGAJUAN PINJAMAN
    ════════════════════════════════════════════════════════════ --}}
    @if($pengajuanPinjaman->isNotEmpty())
        <div class="px-4 mt-6">
            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-3">Progres Pengajuan Pinjaman</p>
            
            <div class="flex flex-col gap-3">
                @foreach($pengajuanPinjaman as $pinjaman)
                    <a href="{{ route('anggota.riwayat-setoran') }}" wire:navigate class="block rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 p-4 shadow-sm hover:shadow-md active:scale-[0.99] transition-all">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $pinjaman->created_at->format('d M Y') }}</span>
                            @if($pinjaman->status === 'disetujui')
                                @if($pinjaman->angsurans_count > 0)
                                    <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600 border border-indigo-200 dark:border-indigo-800/50 dark:bg-indigo-950/40 dark:text-indigo-400">Sedang Berjalan</span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400">Disetujui</span>
                                @endif
                            @elseif($pinjaman->status === 'ditolak')
                                <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600 border border-rose-200 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-400">Ditolak</span>
                            @elseif($pinjaman->status === 'lunas')
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-300 dark:border-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Lunas</span>
                            @else
                                <span class="inline-flex rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-semibold text-orange-600 border border-orange-200 dark:border-orange-800/50 dark:bg-orange-950/40 dark:text-orange-400">Sedang Diproses</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-lg font-bold text-zinc-900 dark:text-white">Rp {{ number_format($pinjaman->jumlah_ajuan, 0, ',', '.') }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $pinjaman->tenor }} bln • {{ $pinjaman->jenis_permohonan ?? 'Biasa' }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- spacer for bottom nav --}}
    <div class="h-28"></div>
</div>