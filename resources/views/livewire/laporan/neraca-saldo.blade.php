<?php

use App\Models\SimpananPokok;
use App\Models\SimpananWajib;
use App\Models\Penarikan;
use App\Models\Pinjaman;
use App\Models\Angsuran;
use App\Models\TransaksiOperasional;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $filterPeriode = 'semua'; // semua | tahun_ini | cut_off
    public string $cutOffDate;
    public string $formatTampilan = 'skontro'; // skontro (2 kolom) | stafel (vertikal)

    public function mount()
    {
        TransaksiOperasional::ensureTableExists();
        $this->cutOffDate = date('Y-m-d');
    }

    public function setFilter(string $val)
    {
        $this->filterPeriode = $val;
    }

    public function setFormat(string $val)
    {
        $this->formatTampilan = $val;
    }

    public function with(): array
    {
        TransaksiOperasional::ensureTableExists();

        $queryDateCutOff = null;
        if ($this->filterPeriode === 'tahun_ini') {
            $queryDateCutOff = Carbon::now()->endOfYear()->format('Y-m-d');
        } elseif ($this->filterPeriode === 'cut_off' && !empty($this->cutOffDate)) {
            $queryDateCutOff = Carbon::parse($this->cutOffDate)->endOfDay()->format('Y-m-d H:i:s');
        }

        // 1. Simpanan Pokok
        $pokokQuery = SimpananPokok::query();
        if ($queryDateCutOff) {
            $pokokQuery->where('tanggal', '<=', $queryDateCutOff);
        }
        $totalSimpananPokok = $pokokQuery->sum('jumlah');

        // 2. Simpanan Wajib
        $wajibQuery = SimpananWajib::query();
        if ($this->filterPeriode === 'tahun_ini') {
            $wajibQuery->where('tahun', '<=', date('Y'));
        } elseif ($this->filterPeriode === 'cut_off' && !empty($this->cutOffDate)) {
            $wajibQuery->where('created_at', '<=', Carbon::parse($this->cutOffDate)->endOfDay());
        }
        $totalSimpananWajib = $wajibQuery->sum('jumlah');

        // 3. Penarikan Simpanan
        $penarikanDisetujuiQuery = Penarikan::where('status', 'disetujui');
        $penarikanMenungguQuery = Penarikan::whereIn('status', ['menunggu', 'proses']);
        if ($queryDateCutOff) {
            $penarikanDisetujuiQuery->where('tanggal', '<=', $queryDateCutOff);
            $penarikanMenungguQuery->where('tanggal', '<=', $queryDateCutOff);
        }
        $totalPenarikanDisetujui = $penarikanDisetujuiQuery->sum('jumlah');
        $totalHutangPenarikan = $penarikanMenungguQuery->sum('jumlah');

        // 4. Pinjaman & Angsuran
        $pinjamanQuery = Pinjaman::whereIn('status', ['disetujui', 'lunas']);
        if ($queryDateCutOff) {
            $pinjamanQuery->where('updated_at', '<=', $queryDateCutOff);
        }
        $totalRealisasiPinjaman = $pinjamanQuery->sum('jumlah_ajuan');

        $angsuranQuery = Angsuran::with('pinjaman')->where('status_pembayaran', 'lunas');
        if ($queryDateCutOff) {
            $angsuranQuery->where('tanggal_bayar', '<=', $queryDateCutOff);
        }
        $angsurans = $angsuranQuery->get();

        $totalAngsuranMasuk = 0;
        $totalPokokTerbayar = 0;
        $totalJasaTerbayar = 0;

        foreach ($angsurans as $ang) {
            $ajuan = $ang->pinjaman->jumlah_ajuan ?? 0;
            $jasa = min($ang->jumlah_bayar, $ajuan * 0.01);
            $pokok = max(0, $ang->jumlah_bayar - $jasa);

            $totalAngsuranMasuk += $ang->jumlah_bayar;
            $totalPokokTerbayar += $pokok;
            $totalJasaTerbayar += $jasa;
        }

        // 5. Biaya Operasional & Pendapatan Lain
        $bebanQuery = TransaksiOperasional::where('jenis', 'beban');
        $pendapatanLainQuery = TransaksiOperasional::where('jenis', 'pendapatan_lain');
        if ($queryDateCutOff) {
            $bebanQuery->where('tanggal', '<=', $queryDateCutOff);
            $pendapatanLainQuery->where('tanggal', '<=', $queryDateCutOff);
        }
        $totalBiayaOperasional = $bebanQuery->sum('nominal');
        $totalPendapatanLain = $pendapatanLainQuery->sum('nominal');

        // ── PERHITUNGAN AKUN NERACA SALDO ──────────────────────────
        // AKTIVA (ASET)
        // Kas di Tangan & Bank = Inflows - Outflows
        $inflows = $totalSimpananPokok + $totalSimpananWajib + $totalAngsuranMasuk + $totalPendapatanLain;
        $outflows = $totalRealisasiPinjaman + $totalPenarikanDisetujui + $totalBiayaOperasional;
        $saldoKas = $inflows - $outflows;

        // Piutang Pinjaman Anggota = Pokok Pinjaman Disetujui - Pokok Cicilan Terbayar
        $piutangPinjaman = max(0, $totalRealisasiPinjaman - $totalPokokTerbayar);

        $totalAktiva = $saldoKas + $piutangPinjaman;

        // PASIVA (KEWAJIBAN & EKUITAS)
        // Kewajiban
        $totalLiabilitas = $totalHutangPenarikan;

        // Ekuitas (Modal Sendiri)
        $modalPokok = $totalSimpananPokok;
        // Simpanan Wajib Bersih dikurangi Penarikan Disetujui & Penarikan Menunggu (yang direklasifikasi ke liabilitas)
        $modalWajib = max(0, $totalSimpananWajib - $totalPenarikanDisetujui - $totalHutangPenarikan);
        // SHU / PHU Berjalan
        $shuBerjalan = ($totalJasaTerbayar + $totalPendapatanLain) - $totalBiayaOperasional;

        $totalEkuitas = $modalPokok + $modalWajib + $shuBerjalan;
        $totalPasiva = $totalLiabilitas + $totalEkuitas;

        $selisih = abs($totalAktiva - $totalPasiva);
        $isBalanced = ($selisih < 1.0); // toleransi floating point Rp 1

        return compact(
            'saldoKas',
            'piutangPinjaman',
            'totalAktiva',
            'totalLiabilitas',
            'totalHutangPenarikan',
            'modalPokok',
            'modalWajib',
            'shuBerjalan',
            'totalEkuitas',
            'totalPasiva',
            'isBalanced',
            'selisih',
            'totalSimpananPokok',
            'totalSimpananWajib',
            'totalPenarikanDisetujui',
            'totalRealisasiPinjaman',
            'totalAngsuranMasuk',
            'totalPokokTerbayar',
            'totalJasaTerbayar',
            'totalBiayaOperasional',
            'totalPendapatanLain'
        );
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-full mx-auto">
    {{-- Header & Toolbar --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-800">
        <div>
            <div class="flex items-center gap-2">
                <div class="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400">
                    <flux:icon name="scale" class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Neraca Saldo (Trial Balance)</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Posisi Keuangan & Keseimbangan Akun Aktiva dan Pasiva Koperasi</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Filter Periode Tabs --}}
            <div class="inline-flex rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800 shadow-inner">
                <button wire:click="setFilter('semua')"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {{ $filterPeriode === 'semua' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                    Semua Waktu
                </button>
                <button wire:click="setFilter('tahun_ini')"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {{ $filterPeriode === 'tahun_ini' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                    Tahun Ini ({{ date('Y') }})
                </button>
                <button wire:click="setFilter('cut_off')"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {{ $filterPeriode === 'cut_off' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                    Cut-Off Tanggal
                </button>
            </div>

            @if($filterPeriode === 'cut_off')
                <div class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-1 shadow-sm">
                    <flux:icon name="calendar" class="size-4 text-indigo-500" />
                    <input type="date" wire:model.live="cutOffDate"
                        class="border-0 bg-transparent text-xs font-semibold text-zinc-800 dark:text-zinc-200 focus:ring-0 p-0" />
                </div>
            @endif

            {{-- Toggle Layout Skontro / Stafel --}}
            <div class="inline-flex rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                <button wire:click="setFormat('skontro')" title="Format T (Skontro)"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-semibold flex items-center gap-1 transition-all {{ $formatTampilan === 'skontro' ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}">
                    <flux:icon name="table-cells" class="size-3.5" />
                    <span class="hidden sm:inline">Skontro (2 Kolom)</span>
                </button>
                <button wire:click="setFormat('stafel')" title="Format Daftar (Stafel)"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-semibold flex items-center gap-1 transition-all {{ $formatTampilan === 'stafel' ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}">
                    <flux:icon name="bars-3" class="size-3.5" />
                    <span class="hidden sm:inline">Stafel (Daftar)</span>
                </button>
            </div>

            {{-- Tombol Cetak / Print --}}
            <button onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/80 shadow-sm transition-colors">
                <flux:icon name="printer" class="size-4 text-zinc-500" />
                Cetak Neraca
            </button>
        </div>
    </div>

    {{-- Kpi Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        {{-- Card 1: Total Aktiva --}}
        <div class="relative overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/60 via-white to-white p-5 shadow-sm dark:border-indigo-900/50 dark:from-indigo-950/20 dark:via-zinc-900 dark:to-zinc-900 transition-all hover:shadow-md group">
            <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <flux:icon name="banknotes" class="size-24 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400">
                    <flux:icon name="arrow-up-right" class="size-5" />
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-100/80 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Aktiva / Aset</span>
            </div>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Aktiva (Kas + Piutang)</p>
            <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white mt-1 tracking-tight">
                Rp {{ number_format($totalAktiva, 0, ',', '.') }}
            </h3>
        </div>

        {{-- Card 2: Kewajiban / Liabilitas --}}
        <div class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/60 via-white to-white p-5 shadow-sm dark:border-amber-900/50 dark:from-amber-950/20 dark:via-zinc-900 dark:to-zinc-900 transition-all hover:shadow-md group">
            <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <flux:icon name="document-text" class="size-24 text-amber-600 dark:text-amber-400" />
            </div>
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                    <flux:icon name="clock" class="size-5" />
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100/80 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Kewajiban</span>
            </div>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Liabilitas (Hutang Penarikan)</p>
            <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white mt-1 tracking-tight">
                Rp {{ number_format($totalLiabilitas, 0, ',', '.') }}
            </h3>
        </div>

        {{-- Card 3: Ekuitas Modal & SHU --}}
        <div class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/60 via-white to-white p-5 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/20 dark:via-zinc-900 dark:to-zinc-900 transition-all hover:shadow-md group">
            <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <flux:icon name="wallet" class="size-24 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                    <flux:icon name="check-circle" class="size-5" />
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100/80 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Ekuitas Modal</span>
            </div>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Modal Sendiri & SHU</p>
            <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white mt-1 tracking-tight">
                Rp {{ number_format($totalEkuitas, 0, ',', '.') }}
            </h3>
        </div>

        {{-- Card 4: Status Keseimbangan (Balanced Status) --}}
        <div class="relative overflow-hidden rounded-2xl border {{ $isBalanced ? 'border-emerald-300 bg-emerald-500/10 dark:border-emerald-800 dark:bg-emerald-950/30' : 'border-rose-300 bg-rose-500/10 dark:border-rose-800 dark:bg-rose-950/30' }} p-5 shadow-sm transition-all hover:shadow-md flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl {{ $isBalanced ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900 dark:text-emerald-400' : 'bg-rose-100 text-rose-600 dark:bg-rose-900 dark:text-rose-400' }}">
                    <flux:icon name="{{ $isBalanced ? 'check-badge' : 'exclamation-triangle' }}" class="size-6" />
                </div>
                <span class="text-xs font-extrabold px-2.5 py-1 rounded-full {{ $isBalanced ? 'bg-emerald-500 text-white animate-pulse' : 'bg-rose-500 text-white' }}">
                    {{ $isBalanced ? 'SEIMBANG (BALANCED)' : 'TIDAK SEIMBANG' }}
                </span>
            </div>
            <div>
                <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Total Pasiva + Ekuitas</p>
                <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white mt-1 tracking-tight">
                    Rp {{ number_format($totalPasiva, 0, ',', '.') }}
                </h3>
            </div>
            @if(!$isBalanced)
                <p class="text-[11px] font-bold text-rose-600 dark:text-rose-400 mt-2">
                    Selisih: Rp {{ number_format($selisih, 0, ',', '.') }}
                </p>
            @endif
        </div>
    </div>

    {{-- Main Neraca Report Section --}}
    <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 shadow-sm overflow-hidden p-6 print:border-0 print:shadow-none print:p-0">
        {{-- Formal Kop Header untuk Print / Cetak --}}
        <div class="hidden print:block text-center border-b-2 border-black pb-4 mb-6">
            <h2 class="text-xl font-extrabold tracking-wide uppercase">Koperasi Polres Lombok Utara (Primkoppol Lotara)</h2>
            <p class="text-xs text-zinc-700">Jl. Raya Tanjung - Bayan, Lombok Utara, Nusa Tenggara Barat</p>
            <h3 class="text-lg font-bold underline mt-3 uppercase">Neraca Saldo Posisi Keuangan</h3>
            <p class="text-xs font-semibold text-zinc-800 mt-1">
                Periode: {{ $filterPeriode === 'semua' ? 'Semua Waktu' : ($filterPeriode === 'tahun_ini' ? 'Tahun ' . date('Y') : 'Per ' . Carbon::parse($cutOffDate)->translatedFormat('d F Y')) }}
            </p>
        </div>

        @if($formatTampilan === 'skontro')
            {{-- FORMAT SKONTRO (2 KOLOM: AKTIVA DI KIRI | PASIVA DI KANAN) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 divide-y lg:divide-y-0 lg:divide-x divide-zinc-200 dark:divide-zinc-800">
                {{-- SISI AKTIVA (HARTA) --}}
                <div class="pr-0 lg:pr-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b-2 border-indigo-500 mb-4">
                            <h3 class="text-base font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider flex items-center gap-2">
                                <flux:icon name="folder" class="size-4" />
                                I. AKTIVA (ASET KOPERASI)
                            </h3>
                        </div>

                        {{-- Aset Lancar --}}
                        <div class="space-y-4">
                            <div class="font-bold text-xs text-zinc-400 dark:text-zinc-500 uppercase tracking-widest pl-1">Aset Lancar</div>
                            
                            {{-- Kas & Bank --}}
                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/40 p-3.5 border border-zinc-200/60 dark:border-zinc-800 transition-colors hover:border-indigo-300">
                                <div class="flex justify-between items-start font-bold text-sm text-zinc-900 dark:text-white">
                                    <span>Kas & Bank Koperasi</span>
                                    <span class="font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($saldoKas, 0, ',', '.') }}</span>
                                </div>
                                <div class="mt-2 space-y-1 text-xs text-zinc-500 dark:text-zinc-400 border-t border-zinc-200/80 dark:border-zinc-800/80 pt-2 font-mono">
                                    <div class="flex justify-between"><span>(+) Simpanan Pokok Masuk:</span><span>Rp {{ number_format($totalSimpananPokok, 0, ',', '.') }}</span></div>
                                    <div class="flex justify-between"><span>(+) Simpanan Wajib Masuk:</span><span>Rp {{ number_format($totalSimpananWajib, 0, ',', '.') }}</span></div>
                                    <div class="flex justify-between"><span>(+) Angsuran Masuk (Pokok & Jasa):</span><span>Rp {{ number_format($totalAngsuranMasuk, 0, ',', '.') }}</span></div>
                                    @if($totalPendapatanLain > 0)
                                        <div class="flex justify-between text-emerald-600 dark:text-emerald-400"><span>(+) Pendapatan Lain-lain:</span><span>Rp {{ number_format($totalPendapatanLain, 0, ',', '.') }}</span></div>
                                    @endif
                                    <div class="flex justify-between text-rose-600 dark:text-rose-400"><span>(-) Realisasi Pencairan Pinjaman:</span><span>Rp {{ number_format($totalRealisasiPinjaman, 0, ',', '.') }}</span></div>
                                    <div class="flex justify-between text-rose-600 dark:text-rose-400"><span>(-) Penarikan Simpanan Disetujui:</span><span>Rp {{ number_format($totalPenarikanDisetujui, 0, ',', '.') }}</span></div>
                                    @if($totalBiayaOperasional > 0)
                                        <div class="flex justify-between text-rose-600 dark:text-rose-400"><span>(-) Biaya Operasional / Beban:</span><span>Rp {{ number_format($totalBiayaOperasional, 0, ',', '.') }}</span></div>
                                    @endif
                                </div>
                            </div>

                            {{-- Piutang Pinjaman --}}
                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/40 p-3.5 border border-zinc-200/60 dark:border-zinc-800 transition-colors hover:border-indigo-300">
                                <div class="flex justify-between items-start font-bold text-sm text-zinc-900 dark:text-white">
                                    <span>Piutang Pinjaman Anggota</span>
                                    <span class="font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($piutangPinjaman, 0, ',', '.') }}</span>
                                </div>
                                <div class="mt-2 space-y-1 text-xs text-zinc-500 dark:text-zinc-400 border-t border-zinc-200/80 dark:border-zinc-800/80 pt-2 font-mono">
                                    <div class="flex justify-between"><span>Total Pokok Pinjaman Disetujui:</span><span>Rp {{ number_format($totalRealisasiPinjaman, 0, ',', '.') }}</span></div>
                                    <div class="flex justify-between text-emerald-600 dark:text-emerald-400"><span>(-) Pokok Cicilan Terbayar:</span><span>Rp {{ number_format($totalPokokTerbayar, 0, ',', '.') }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Total Aktiva Footer --}}
                    <div class="mt-8 pt-4 border-t-2 border-indigo-600 dark:border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl p-4 flex justify-between items-center">
                        <span class="font-black text-sm uppercase text-indigo-950 dark:text-indigo-200">TOTAL AKTIVA (ASET)</span>
                        <span class="text-xl font-extrabold font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($totalAktiva, 0, ',', '.') }}</span>
                    </div>
                </div>

                {{-- SISI PASIVA (LIABILITAS & EKUITAS) --}}
                <div class="pt-6 lg:pt-0 pl-0 lg:pl-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b-2 border-amber-500 mb-4">
                            <h3 class="text-base font-extrabold text-amber-600 dark:text-amber-400 uppercase tracking-wider flex items-center gap-2">
                                <flux:icon name="folder-open" class="size-4" />
                                II. PASIVA (KEWAJIBAN & EKUITAS)
                            </h3>
                        </div>

                        {{-- Liabilitas --}}
                        <div class="space-y-4 mb-6">
                            <div class="font-bold text-xs text-zinc-400 dark:text-zinc-500 uppercase tracking-widest pl-1">A. Kewajiban Jangka Pendek (Liabilitas)</div>
                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/40 p-3.5 border border-zinc-200/60 dark:border-zinc-800 flex justify-between items-center text-sm font-bold text-zinc-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <span>Hutang Penarikan Simpanan</span>
                                    <span class="text-[10px] font-normal px-2 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Menunggu Proses</span>
                                </div>
                                <span class="font-mono text-amber-600 dark:text-amber-400">Rp {{ number_format($totalHutangPenarikan, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        {{-- Ekuitas (Modal) --}}
                        <div class="space-y-4">
                            <div class="font-bold text-xs text-zinc-400 dark:text-zinc-500 uppercase tracking-widest pl-1">B. Modal Koperasi & SHU (Ekuitas)</div>
                            
                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/40 p-3.5 border border-zinc-200/60 dark:border-zinc-800 flex justify-between items-center text-sm font-bold text-zinc-900 dark:text-white">
                                <span>Simpanan Pokok Anggota</span>
                                <span class="font-mono text-emerald-600 dark:text-emerald-400">Rp {{ number_format($modalPokok, 0, ',', '.') }}</span>
                            </div>

                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/40 p-3.5 border border-zinc-200/60 dark:border-zinc-800">
                                <div class="flex justify-between items-center text-sm font-bold text-zinc-900 dark:text-white">
                                    <span>Simpanan Wajib Anggota (Bersih)</span>
                                    <span class="font-mono text-emerald-600 dark:text-emerald-400">Rp {{ number_format($modalWajib, 0, ',', '.') }}</span>
                                </div>
                                <div class="mt-1 flex justify-between text-xs text-zinc-500 font-mono">
                                    <span>Akumulasi Wajib (Rp {{ number_format($totalSimpananWajib, 0, ',', '.') }}) dikurangi penarikan disetujui & menunggu</span>
                                </div>
                            </div>

                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/40 p-3.5 border border-zinc-200/60 dark:border-zinc-800">
                                <div class="flex justify-between items-center text-sm font-bold text-zinc-900 dark:text-white">
                                    <span>SHU Berjalan (PHU Tahun Berjalan)</span>
                                    <span class="font-mono {{ $shuBerjalan >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        Rp {{ number_format($shuBerjalan, 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="mt-1 space-y-1 text-xs text-zinc-500 font-mono border-t border-zinc-200/80 dark:border-zinc-800/80 pt-1">
                                    <div class="flex justify-between"><span>(+) Pendapatan Jasa Cicilan 1%:</span><span>Rp {{ number_format($totalJasaTerbayar, 0, ',', '.') }}</span></div>
                                    @if($totalPendapatanLain > 0)
                                        <div class="flex justify-between"><span>(+) Pendapatan Lainnya:</span><span>Rp {{ number_format($totalPendapatanLain, 0, ',', '.') }}</span></div>
                                    @endif
                                    @if($totalBiayaOperasional > 0)
                                        <div class="flex justify-between text-rose-600 dark:text-rose-400"><span>(-) Biaya Operasional / Beban:</span><span>Rp {{ number_format($totalBiayaOperasional, 0, ',', '.') }}</span></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Total Pasiva Footer --}}
                    <div class="mt-8 pt-4 border-t-2 border-amber-600 dark:border-amber-500 bg-amber-50/50 dark:bg-amber-950/20 rounded-xl p-4 flex justify-between items-center">
                        <span class="font-black text-sm uppercase text-amber-950 dark:text-amber-200">TOTAL PASIVA & EKUITAS</span>
                        <span class="text-xl font-extrabold font-mono text-amber-600 dark:text-amber-400">Rp {{ number_format($totalPasiva, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        @else
            {{-- FORMAT STAFEL (DAFTAR VERTIKAL ATAS - BAWAH) --}}
            <div class="space-y-8 max-w-4xl mx-auto">
                {{-- SISI AKTIVA --}}
                <div>
                    <h3 class="text-lg font-extrabold text-indigo-600 dark:text-indigo-400 pb-2 border-b-2 border-indigo-500 mb-4 uppercase">
                        I. AKTIVA (HARTA & PIUTANG KOPERASI)
                    </h3>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                            <tr class="bg-zinc-50 dark:bg-zinc-800/30">
                                <td class="py-3 px-4 font-bold">1. Kas & Bank Koperasi</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">Rp {{ number_format($saldoKas, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 pl-8 text-xs text-zinc-500">
                                    Simpanan Pokok (+Rp {{ number_format($totalSimpananPokok, 0, ',', '.') }}) &bull; Wajib (+Rp {{ number_format($totalSimpananWajib, 0, ',', '.') }}) &bull; Angsuran (+Rp {{ number_format($totalAngsuranMasuk, 0, ',', '.') }}) <br/>
                                    Dikurangi Realisasi Pinjaman (-Rp {{ number_format($totalRealisasiPinjaman, 0, ',', '.') }}) & Penarikan (-Rp {{ number_format($totalPenarikanDisetujui, 0, ',', '.') }})
                                </td>
                                <td></td>
                            </tr>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/30">
                                <td class="py-3 px-4 font-bold">2. Piutang Pinjaman Anggota</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">Rp {{ number_format($piutangPinjaman, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 pl-8 text-xs text-zinc-500">
                                    Total Pokok Pinjaman (Rp {{ number_format($totalRealisasiPinjaman, 0, ',', '.') }}) dikurangi Pokok Terbayar (Rp {{ number_format($totalPokokTerbayar, 0, ',', '.') }})
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-indigo-100/70 dark:bg-indigo-950/40 text-base font-extrabold border-t-2 border-indigo-600">
                                <td class="py-3 px-4 uppercase text-indigo-900 dark:text-indigo-200">TOTAL AKTIVA</td>
                                <td class="py-3 px-4 text-right font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($totalAktiva, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- SISI PASIVA --}}
                <div>
                    <h3 class="text-lg font-extrabold text-amber-600 dark:text-amber-400 pb-2 border-b-2 border-amber-500 mb-4 uppercase">
                        II. PASIVA (KEWAJIBAN & EKUITAS)
                    </h3>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                            <tr class="bg-amber-50/50 dark:bg-amber-950/10 text-xs font-bold uppercase text-zinc-500">
                                <td colspan="2" class="py-2 px-4">A. Kewajiban Jangka Pendek (Liabilitas)</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 pl-6">1. Hutang Penarikan Simpanan (Menunggu Proses)</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-amber-600 dark:text-amber-400">Rp {{ number_format($totalLiabilitas, 0, ',', '.') }}</td>
                            </tr>

                            <tr class="bg-amber-50/50 dark:bg-amber-950/10 text-xs font-bold uppercase text-zinc-500">
                                <td colspan="2" class="py-2 px-4">B. Ekuitas Modal & Sisa Hasil Usaha</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 pl-6">1. Simpanan Pokok Anggota</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($modalPokok, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 pl-6">2. Simpanan Wajib Anggota (Bersih)</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($modalWajib, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-4 pl-6">
                                    3. SHU Berjalan (PHU Tahun Berjalan)
                                    <div class="text-xs font-normal text-zinc-500">Pendapatan Jasa & Lainnya dikurangi Beban Operasional</div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-blue-600 dark:text-blue-400">Rp {{ number_format($shuBerjalan, 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-amber-100/70 dark:bg-amber-950/40 text-base font-extrabold border-t-2 border-amber-600">
                                <td class="py-3 px-4 uppercase text-amber-900 dark:text-amber-200">TOTAL PASIVA & EKUITAS</td>
                                <td class="py-3 px-4 text-right font-mono text-amber-600 dark:text-amber-400">Rp {{ number_format($totalPasiva, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Sign-Off Footer untuk Cetak / Print --}}
        <div class="hidden print:grid grid-cols-2 gap-12 mt-16 text-center text-sm font-bold">
            <div>
                <p>Mengetahui / Menyetujui,</p>
                <p class="mt-1">Ketua Primkoppol Lotara</p>
                <div class="h-20"></div>
                <p class="underline">( ............................................ )</p>
                <p class="text-xs font-normal">NRP. .................................</p>
            </div>
            <div>
                <p>Tanjung, {{ Carbon::now()->translatedFormat('d F Y') }}</p>
                <p class="mt-1">Bendahara Koperasi</p>
                <div class="h-20"></div>
                <p class="underline">( ............................................ )</p>
                <p class="text-xs font-normal">NRP. .................................</p>
            </div>
        </div>
    </div>
</div>
