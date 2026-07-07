@php
    $totalAnggota = \App\Models\User::count();
    $totalSimpananWajib = \App\Models\SimpananWajib::sum('jumlah');
    $antrianPinjaman = \App\Models\Pinjaman::where('status', 'proses')->count();

    // Statistik Pinjaman
    $aktifPinjamanCount = \App\Models\Pinjaman::where('status', 'disetujui')->distinct()->count('user_id');

    $activePinjamans = \App\Models\Pinjaman::whereIn('status', ['disetujui', 'lunas'])->with(['angsurans' => function ($q) {
        $q->where('status_pembayaran', 'lunas');
    }])->get();

    $totalTerealisasi = 0;
    $totalPokok = 0;
    $totalJasa = 0;

    foreach ($activePinjamans as $pinjaman) {
        $totalTerealisasi += $pinjaman->jumlah_ajuan;
        $ajuan = $pinjaman->jumlah_ajuan ?? 0;
        foreach ($pinjaman->angsurans as $angsuran) {
            $jasa = min($angsuran->jumlah_bayar, $ajuan * 0.01);
            $pokok = max(0, $angsuran->jumlah_bayar - $jasa);
            $totalPokok += $pokok;
            $totalJasa += $jasa;
        }
    }
@endphp

<x-layouts.app>
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Dashboard Admin</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Selamat datang di panel administrasi Koperasi Polres.
            </p>
        </div>

        <livewire:admin.sync-anggota />

        {{-- Section 1: Ringkasan Umum --}}
        <div>
            <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-200 mb-3 flex items-center gap-2">
                <flux:icon name="squares-2x2" class="size-4 text-indigo-500" />
                Ringkasan Umum & Simpanan
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div
                    class="group relative overflow-hidden rounded-2xl bg-white border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 p-6 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="w-12 h-12 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                            <flux:icon name="users" class="size-6 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">Total</span>
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold text-zinc-900 dark:text-white tracking-tight">{{ $totalAnggota }}</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium mt-1">Total Anggota Koperasi</p>
                    </div>
                </div>

                <div
                    class="group relative overflow-hidden rounded-2xl bg-white border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 p-6 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-800">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="w-12 h-12 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                            <flux:icon name="banknotes" class="size-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">Simpanan</span>
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold text-zinc-900 dark:text-white tracking-tight">
                            Rp {{ number_format($totalSimpananWajib, 0, ',', '.') }}
                        </h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium mt-1">Total Simpanan Wajib</p>
                    </div>
                </div>

                <div
                    class="group relative overflow-hidden rounded-2xl bg-white border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 p-6 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow-md hover:border-orange-300 dark:hover:border-orange-800">
                    <div class="flex items-center justify-between mb-4">
                        <div
                            class="w-12 h-12 bg-orange-50 dark:bg-orange-900/30 rounded-xl flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                            <flux:icon name="clock" class="size-6 text-orange-600 dark:text-orange-400" />
                        </div>
                        @if($antrianPinjaman > 0)
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 animate-pulse">
                                <span class="size-1.5 rounded-full bg-orange-500"></span>
                                Perlu Review
                            </span>
                        @else
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">Aman</span>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold text-zinc-900 dark:text-white tracking-tight">
                            {{ $antrianPinjaman }} <span class="text-base font-normal text-zinc-500">Permohonan</span>
                        </h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium mt-1">Antrian Pinjaman</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Statistik & Performa Pinjaman --}}
        <div class="mt-2">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                        <flux:icon name="chart-bar" class="size-4 text-blue-500" />
                        Statistik & Performa Pinjaman
                    </h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Akuntabilitas realisasi pinjaman, setoran masuk, dan pendapatan koperasi.</p>
                </div>
                <a wire:navigate href="{{ route('pinjaman.rekap') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors self-start sm:self-auto">
                    Lihat Rekap Lengkap
                    <flux:icon name="arrow-right" class="size-3" />
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- 1. Anggota Aktif Pinjam --}}
                <div
                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-50/50 via-white to-white border border-violet-200/80 dark:from-violet-950/20 dark:via-zinc-900 dark:to-zinc-900 dark:border-violet-900/50 p-6 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow-md hover:border-violet-300 dark:hover:border-violet-700">
                    <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <flux:icon name="users" class="size-28 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div
                                class="w-10 h-10 bg-violet-100 dark:bg-violet-900/50 rounded-xl flex items-center justify-center text-violet-600 dark:text-violet-400 transition-transform duration-300 group-hover:scale-110">
                                <flux:icon name="users" class="size-5" />
                            </div>
                            <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-violet-100/80 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300">Aktif</span>
                        </div>
                        <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight mt-2">
                            {{ $aktifPinjamanCount }} <span class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">Orang</span>
                        </h3>
                        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mt-1">Peminjam Aktif</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-violet-100 dark:border-violet-900/30 flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="information-circle" class="mr-1.5 size-3.5 text-violet-500 shrink-0" />
                        <span>Sedang memiliki pinjaman berjalan</span>
                    </div>
                </div>

                {{-- 2. Total Terealisasi --}}
                <div
                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-50/50 via-white to-white border border-blue-200/80 dark:from-blue-950/20 dark:via-zinc-900 dark:to-zinc-900 dark:border-blue-900/50 p-6 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700">
                    <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <flux:icon name="banknotes" class="size-28 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div
                                class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-xl flex items-center justify-center text-blue-600 dark:text-blue-400 transition-transform duration-300 group-hover:scale-110">
                                <flux:icon name="arrow-up-right" class="size-5" />
                            </div>
                            <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-100/80 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">Realisasi</span>
                        </div>
                        <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight mt-2">
                            Rp {{ number_format($totalTerealisasi, 0, ',', '.') }}
                        </h3>
                        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mt-1">Total Terealisasi</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-blue-100 dark:border-blue-900/30 flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="information-circle" class="mr-1.5 size-3.5 text-blue-500 shrink-0" />
                        <span>Total nominal disetujui / dipinjamkan</span>
                    </div>
                </div>

                {{-- 3. Pokok Setoran Masuk --}}
                <div
                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-50/50 via-white to-white border border-emerald-200/80 dark:from-emerald-950/20 dark:via-zinc-900 dark:to-zinc-900 dark:border-emerald-900/50 p-6 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <flux:icon name="wallet" class="size-28 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div
                                class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/50 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 transition-transform duration-300 group-hover:scale-110">
                                <flux:icon name="arrow-down-tray" class="size-5" />
                            </div>
                            <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-100/80 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">Pokok</span>
                        </div>
                        <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight mt-2">
                            Rp {{ number_format($totalPokok, 0, ',', '.') }}
                        </h3>
                        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mt-1">Pokok Setoran Masuk</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-emerald-100 dark:border-emerald-900/30 flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="information-circle" class="mr-1.5 size-3.5 text-emerald-500 shrink-0" />
                        <span>Porsi pengembalian pokok piutang</span>
                    </div>
                </div>

                {{-- 4. Pendapatan Jasa --}}
                <div
                    class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-50/50 via-white to-white border border-amber-200/80 dark:from-amber-950/20 dark:via-zinc-900 dark:to-zinc-900 dark:border-amber-900/50 p-6 flex flex-col justify-between shadow-sm transition-all duration-300 hover:shadow-md hover:border-amber-300 dark:hover:border-amber-700">
                    <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <flux:icon name="chart-bar" class="size-28 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div
                                class="w-10 h-10 bg-amber-100 dark:bg-amber-900/50 rounded-xl flex items-center justify-center text-amber-600 dark:text-amber-400 transition-transform duration-300 group-hover:scale-110">
                                <flux:icon name="arrow-trending-up" class="size-5" />
                            </div>
                            <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-100/80 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">Jasa 1%</span>
                        </div>
                        <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight mt-2">
                            Rp {{ number_format($totalJasa, 0, ',', '.') }}
                        </h3>
                        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mt-1">Pendapatan Jasa</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-amber-100 dark:border-amber-900/30 flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="information-circle" class="mr-1.5 size-3.5 text-amber-500 shrink-0" />
                        <span>Akumulasi jasa angsuran lunas</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>