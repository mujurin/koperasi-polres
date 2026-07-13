<?php

use App\Models\Angsuran;
use App\Models\Pinjaman;
use App\Models\TransaksiOperasional;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $activeTab = 'ringkasan'; // ringkasan | kelola_beban | simulasi_shu
    public int $selectedYear;
    public string $selectedMonth = 'semua'; // semua | 1..12

    // Form input / edit untuk Transaksi Operasional (Biaya & Pendapatan Lain)
    public bool $showModalForm = false;
    public ?int $transaksiId = null;
    public string $formTanggal;
    public string $formJenis = 'beban'; // beban | pendapatan_lain
    public string $formKategori = '';
    public string $formNominal = '';
    public string $formKeterangan = '';

    // Persentase Simulasi Pembagian SHU (dapat disesuaikan)
    public float $p_cadangan = 30.0;
    public float $p_jasa_anggota = 25.0;
    public float $p_jasa_pinjaman = 20.0;
    public float $p_pengurus = 10.0;
    public float $p_karyawan = 5.0;
    public float $p_pendidikan = 5.0;
    public float $p_sosial = 5.0;

    public function mount()
    {
        TransaksiOperasional::ensureTableExists();
        $this->selectedYear = (int) date('Y');
        $this->formTanggal = date('Y-m-d');
        $this->formKategori = TransaksiOperasional::kategoriBebanList()[0] ?? 'Beban Administrasi & ATK';
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedFormJenis()
    {
        if ($this->formJenis === 'beban') {
            $this->formKategori = TransaksiOperasional::kategoriBebanList()[0] ?? 'Beban Administrasi & ATK';
        } else {
            $this->formKategori = TransaksiOperasional::kategoriPendapatanLainList()[0] ?? 'Pendapatan Administrasi Bank / Jasa Giro';
        }
    }

    public function openCreateModal(string $jenis = 'beban')
    {
        $this->resetValidation();
        $this->transaksiId = null;
        $this->formJenis = $jenis;
        $this->formTanggal = date('Y-m-d');
        $this->formNominal = '';
        $this->formKeterangan = '';
        $this->updatedFormJenis();
        $this->showModalForm = true;
    }

    public function openEditModal(int $id)
    {
        $this->resetValidation();
        $item = TransaksiOperasional::findOrFail($id);
        $this->transaksiId = $item->id;
        $this->formTanggal = $item->tanggal->format('Y-m-d');
        $this->formJenis = $item->jenis;
        $this->formKategori = $item->kategori;
        $this->formNominal = (string) $item->nominal;
        $this->formKeterangan = $item->keterangan ?? '';
        $this->showModalForm = true;
    }

    public function saveTransaksi()
    {
        $this->validate([
            'formTanggal' => 'required|date',
            'formJenis' => 'required|in:beban,pendapatan_lain',
            'formKategori' => 'required|string|max:255',
            'formNominal' => 'required|numeric|min:0',
            'formKeterangan' => 'nullable|string|max:1000',
        ]);

        TransaksiOperasional::updateOrCreate(
            ['id' => $this->transaksiId],
            [
                'tanggal' => $this->formTanggal,
                'jenis' => $this->formJenis,
                'kategori' => $this->formKategori,
                'nominal' => $this->formNominal,
                'keterangan' => $this->formKeterangan,
            ]
        );

        $this->showModalForm = false;
        session()->flash('message', 'Data transaksi berhasil disimpan!');
    }

    public function deleteTransaksi(int $id)
    {
        TransaksiOperasional::where('id', $id)->delete();
        session()->flash('message', 'Data transaksi berhasil dihapus.');
    }

    public function resetSimulasi()
    {
        $this->p_cadangan = 30.0;
        $this->p_jasa_anggota = 25.0;
        $this->p_jasa_pinjaman = 20.0;
        $this->p_pengurus = 10.0;
        $this->p_karyawan = 5.0;
        $this->p_pendidikan = 5.0;
        $this->p_sosial = 5.0;
    }

    public function with(): array
    {
        TransaksiOperasional::ensureTableExists();

        // 1. Ambil Angsuran Lunas untuk Menghitung Pendapatan Jasa Pinjaman (1%)
        $angsuranQuery = Angsuran::with('pinjaman.user')->where('status_pembayaran', 'lunas')
            ->whereYear('tanggal_bayar', $this->selectedYear);

        if ($this->selectedMonth !== 'semua') {
            $angsuranQuery->whereMonth('tanggal_bayar', (int) $this->selectedMonth);
        }

        $angsuranList = $angsuranQuery->get();

        $totalJasaPinjaman = 0;
        $totalPokokPinjamanMasuk = 0;
        $pendapatanPerBulan = array_fill(1, 12, 0);

        foreach ($angsuranList as $ang) {
            $tgl = Carbon::parse($ang->tanggal_bayar);
            $ajuan = $ang->pinjaman->jumlah_ajuan ?? 0;
            $jasa = min($ang->jumlah_bayar, $ajuan * 0.01);
            $pokok = max(0, $ang->jumlah_bayar - $jasa);

            $totalJasaPinjaman += $jasa;
            $totalPokokPinjamanMasuk += $pokok;
            $pendapatanPerBulan[$tgl->month] += $jasa;
        }

        // 2. Ambil Transaksi Operasional (Pendapatan Lain & Beban)
        $transaksiQuery = TransaksiOperasional::whereYear('tanggal', $this->selectedYear);
        if ($this->selectedMonth !== 'semua') {
            $transaksiQuery->whereMonth('tanggal', (int) $this->selectedMonth);
        }
        $semuaTransaksi = $transaksiQuery->orderByDesc('tanggal')->get();

        $pendapatanLainList = $semuaTransaksi->where('jenis', 'pendapatan_lain');
        $bebanList = $semuaTransaksi->where('jenis', 'beban');

        $totalPendapatanLain = $pendapatanLainList->sum('nominal');
        $totalBebanOperasional = $bebanList->sum('nominal');

        // Total Pendapatan Koperasi & Laba Rugi (SHU Bersih)
        $totalPendapatan = $totalJasaPinjaman + $totalPendapatanLain;
        $shuBersih = $totalPendapatan - $totalBebanOperasional;

        // Kelompokkan beban per kategori
        $bebanPerKategori = $bebanList->groupBy('kategori')->map(function ($rows) {
            return $rows->sum('nominal');
        });

        // Kelompokkan pendapatan lain per kategori
        $pendapatanLainPerKategori = $pendapatanLainList->groupBy('kategori')->map(function ($rows) {
            return $rows->sum('nominal');
        });

        // Pagination untuk Tab Kelola Beban
        $transaksiPaginated = TransaksiOperasional::whereYear('tanggal', $this->selectedYear)
            ->when($this->selectedMonth !== 'semua', function ($q) {
                $q->whereMonth('tanggal', (int) $this->selectedMonth);
            })
            ->orderByDesc('tanggal')
            ->paginate(15);

        return compact(
            'totalJasaPinjaman',
            'totalPendapatanLain',
            'totalPendapatan',
            'totalBebanOperasional',
            'shuBersih',
            'bebanPerKategori',
            'pendapatanLainPerKategori',
            'pendapatanPerBulan',
            'transaksiPaginated',
            'angsuranList'
        );
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-full mx-auto">
    {{-- Flash Message --}}
    @if(session()->has('message'))
        <div class="rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-4 flex items-center justify-between text-emerald-600 dark:text-emerald-400">
            <div class="flex items-center gap-2.5 font-semibold text-sm">
                <flux:icon name="check-circle" class="size-5 shrink-0" />
                <span>{{ session('message') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-xs hover:underline">Tutup</button>
        </div>
    @endif

    {{-- Header & Navigation Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="flex size-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400 shadow-sm">
                <flux:icon name="document-chart-bar" class="size-6" />
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-zinc-900 dark:text-white tracking-tight">Laporan Laba Rugi (PHU)</h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Perhitungan Hasil Usaha (SHU), Pendapatan Jasa, dan Beban Operasional Koperasi</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Filter Tahun --}}
            <div class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-1.5 shadow-sm text-xs font-semibold">
                <flux:icon name="calendar" class="size-4 text-emerald-500" />
                <span class="text-zinc-500">Tahun:</span>
                <select wire:model.live="selectedYear" class="border-0 bg-transparent text-xs font-bold text-zinc-800 dark:text-zinc-200 focus:ring-0 py-0 pr-6 pl-1 cursor-pointer">
                    @for($y = date('Y') + 1; $y >= date('Y') - 5; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>

            {{-- Filter Bulan --}}
            <div class="flex items-center gap-1.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl px-3 py-1.5 shadow-sm text-xs font-semibold">
                <span class="text-zinc-500">Bulan:</span>
                <select wire:model.live="selectedMonth" class="border-0 bg-transparent text-xs font-bold text-zinc-800 dark:text-zinc-200 focus:ring-0 py-0 pr-6 pl-1 cursor-pointer">
                    <option value="semua">Semua Bulan (Jan - Des)</option>
                    @foreach([1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'] as $num => $nama)
                        <option value="{{ $num }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tombol Print --}}
            <button onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/80 shadow-sm transition-colors">
                <flux:icon name="printer" class="size-4 text-zinc-500" />
                Cetak Laporan
            </button>
        </div>
    </div>

    {{-- Tabs Menu --}}
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-6 text-sm font-semibold">
        <button wire:click="setTab('ringkasan')"
            class="pb-3 border-b-2 flex items-center gap-2 transition-all {{ $activeTab === 'ringkasan' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}">
            <flux:icon name="chart-pie" class="size-4" />
            Laporan Utama PHU
        </button>
        <button wire:click="setTab('kelola_beban')"
            class="pb-3 border-b-2 flex items-center gap-2 transition-all {{ $activeTab === 'kelola_beban' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}">
            <flux:icon name="wrench-screwdriver" class="size-4" />
            Kelola Biaya & Pendapatan Lain
        </button>
        <button wire:click="setTab('simulasi_shu')"
            class="pb-3 border-b-2 flex items-center gap-2 transition-all {{ $activeTab === 'simulasi_shu' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}">
            <flux:icon name="calculator" class="size-4" />
            Simulasi Pembagian SHU
        </button>
    </div>

    {{-- KPI Cards Overview --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        {{-- Total Pendapatan --}}
        <div class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/60 via-white to-white p-5 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/20 dark:via-zinc-900 dark:to-zinc-900 transition-all hover:shadow-md group">
            <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <flux:icon name="arrow-trending-up" class="size-24 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                    <flux:icon name="banknotes" class="size-5" />
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100/80 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Pendapatan</span>
            </div>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Pendapatan Usaha</p>
            <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white mt-1 tracking-tight">
                Rp {{ number_format($totalPendapatan, 0, ',', '.') }}
            </h3>
        </div>

        {{-- Total Beban & Biaya --}}
        <div class="relative overflow-hidden rounded-2xl border border-rose-200/80 bg-gradient-to-br from-rose-50/60 via-white to-white p-5 shadow-sm dark:border-rose-900/50 dark:from-rose-950/20 dark:via-zinc-900 dark:to-zinc-900 transition-all hover:shadow-md group">
            <div class="absolute right-0 top-0 -mr-4 -mt-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <flux:icon name="arrow-trending-down" class="size-24 text-rose-600 dark:text-rose-400" />
            </div>
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400">
                    <flux:icon name="document-minus" class="size-5" />
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-rose-100/80 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">Beban Operasional</span>
            </div>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Pengeluaran / Biaya</p>
            <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white mt-1 tracking-tight">
                Rp {{ number_format($totalBebanOperasional, 0, ',', '.') }}
            </h3>
        </div>

        {{-- SHU Bersih / Laba Rugi --}}
        <div class="relative overflow-hidden rounded-2xl border {{ $shuBersih >= 0 ? 'border-blue-300 bg-blue-50/70 dark:border-blue-800 dark:bg-blue-950/40' : 'border-rose-300 bg-rose-50/70 dark:border-rose-800 dark:bg-rose-950/40' }} p-5 shadow-sm transition-all hover:shadow-md group">
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl {{ $shuBersih >= 0 ? 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400' : 'bg-rose-100 text-rose-600 dark:bg-rose-900 dark:text-rose-400' }}">
                    <flux:icon name="{{ $shuBersih >= 0 ? 'trophy' : 'exclamation-circle' }}" class="size-5" />
                </div>
                <span class="text-xs font-extrabold px-2.5 py-1 rounded-full {{ $shuBersih >= 0 ? 'bg-blue-600 text-white shadow-sm' : 'bg-rose-600 text-white' }}">
                    {{ $shuBersih >= 0 ? 'SURPLUS (LABA)' : 'DEFISIT (RUGI)' }}
                </span>
            </div>
            <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">SHU Bersih (PHU)</p>
            <h3 class="text-2xl font-extrabold {{ $shuBersih >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-rose-600 dark:text-rose-400' }} mt-1 tracking-tight">
                Rp {{ number_format($shuBersih, 0, ',', '.') }}
            </h3>
        </div>

        {{-- Marjin / Efisiensi --}}
        <div class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/60 via-white to-white p-5 shadow-sm dark:border-amber-900/50 dark:from-amber-950/20 dark:via-zinc-900 dark:to-zinc-900 transition-all hover:shadow-md group">
            <div class="flex items-center justify-between mb-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                    <flux:icon name="chart-bar" class="size-5" />
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100/80 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Marjin SHU</span>
            </div>
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Rasio Laba Terhadap Pendapatan</p>
            <h3 class="text-2xl font-extrabold text-zinc-900 dark:text-white mt-1 tracking-tight">
                {{ $totalPendapatan > 0 ? number_format(($shuBersih / $totalPendapatan) * 100, 1, ',', '.') : '0' }}%
            </h3>
        </div>
    </div>

    {{-- TAB 1: RINGKASAN UTAMA LABA RUGI --}}
    @if($activeTab === 'ringkasan')
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 shadow-sm overflow-hidden p-6 print:border-0 print:shadow-none print:p-0">
            {{-- Formal Header untuk Print --}}
            <div class="hidden print:block text-center border-b-2 border-black pb-4 mb-6">
                <h2 class="text-xl font-extrabold tracking-wide uppercase">Koperasi Polres Lombok Utara (Primkoppol Lotara)</h2>
                <h3 class="text-lg font-bold underline mt-2 uppercase">Laporan Perhitungan Hasil Usaha (Laba Rugi)</h3>
                <p class="text-xs font-semibold text-zinc-800 mt-1">
                    Periode: {{ $selectedMonth === 'semua' ? 'Tahun ' . $selectedYear : date('F', mktime(0, 0, 0, (int)$selectedMonth, 1)) . ' ' . $selectedYear }}
                </p>
            </div>

            <div class="space-y-8 max-w-4xl mx-auto">
                {{-- 1. PENDAPATAN OPERASIONAL --}}
                <div>
                    <div class="flex items-center justify-between pb-2 border-b-2 border-emerald-500 mb-3">
                        <h3 class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                            <flux:icon name="arrow-trending-up" class="size-4" />
                            I. PENDAPATAN OPERASIONAL KOPERASI
                        </h3>
                    </div>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 font-medium">
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="py-3 px-4 pl-6">
                                    <div class="font-bold text-zinc-900 dark:text-white">1. Pendapatan Jasa Pinjaman Anggota (1%)</div>
                                    <div class="text-xs text-zinc-500">Akumulasi jasa cicilan pinjaman lunas periode terpilih</div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ number_format($totalJasaPinjaman, 0, ',', '.') }}
                                </td>
                            </tr>
                            @foreach($pendapatanLainPerKategori as $kat => $nom)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                    <td class="py-3 px-4 pl-6 text-zinc-700 dark:text-zinc-300">
                                        <div>{{ $loop->iteration + 1 }}. {{ $kat }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        Rp {{ number_format($nom, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-emerald-50/80 dark:bg-emerald-950/30 text-sm font-extrabold border-t-2 border-emerald-600">
                                <td class="py-3 px-4 uppercase text-emerald-900 dark:text-emerald-200">JUMLAH PENDAPATAN OPERASIONAL</td>
                                <td class="py-3 px-4 text-right font-mono text-emerald-600 dark:text-emerald-400">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- 2. BEBAN & BIAYA OPERASIONAL --}}
                <div>
                    <div class="flex items-center justify-between pb-2 border-b-2 border-rose-500 mb-3">
                        <h3 class="text-base font-extrabold text-rose-600 dark:text-rose-400 uppercase tracking-wider flex items-center gap-2">
                            <flux:icon name="arrow-trending-down" class="size-4" />
                            II. BEBAN / BIAYA OPERASIONAL
                        </h3>
                    </div>
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 font-medium">
                            @forelse($bebanPerKategori as $kat => $nom)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                    <td class="py-3 px-4 pl-6 text-zinc-700 dark:text-zinc-300">{{ $loop->iteration }}. {{ $kat }}</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-rose-600 dark:text-rose-400">Rp {{ number_format($nom, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-6 px-4 text-center text-xs italic text-zinc-400">
                                        Belum ada catatan biaya operasional untuk periode ini. <br/>
                                        <button wire:click="setTab('kelola_beban')" class="text-indigo-600 hover:underline mt-1 not-italic font-bold">+ Tambah Biaya Operasional</button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-rose-50/80 dark:bg-rose-950/30 text-sm font-extrabold border-t-2 border-rose-600">
                                <td class="py-3 px-4 uppercase text-rose-900 dark:text-rose-200">JUMLAH BEBAN OPERASIONAL</td>
                                <td class="py-3 px-4 text-right font-mono text-rose-600 dark:text-rose-400">Rp {{ number_format($totalBebanOperasional, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- 3. SISA HASIL USAHA (SHU BERSIH) --}}
                <div class="rounded-2xl border-2 {{ $shuBersih >= 0 ? 'border-blue-600 bg-gradient-to-br from-blue-500/10 to-blue-600/5 dark:from-blue-950/50 dark:to-blue-900/20' : 'border-rose-600 bg-rose-500/10' }} p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <h4 class="text-lg font-extrabold {{ $shuBersih >= 0 ? 'text-blue-700 dark:text-blue-300' : 'text-rose-700 dark:text-rose-300' }} uppercase tracking-wide">
                            III. SISA HASIL USAHA (SHU BERSIH / PHU)
                        </h4>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                            Total Pendapatan (Rp {{ number_format($totalPendapatan, 0, ',', '.') }}) dikurangi Total Beban (Rp {{ number_format($totalBebanOperasional, 0, ',', '.') }})
                        </p>
                    </div>
                    <div class="text-3xl font-black font-mono {{ $shuBersih >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-rose-600 dark:text-rose-400' }}">
                        Rp {{ number_format($shuBersih, 0, ',', '.') }}
                    </div>
                </div>
            </div>

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
    @endif

    {{-- TAB 2: KELOLA BIAYA & PENDAPATAN LAIN --}}
    @if($activeTab === 'kelola_beban')
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 shadow-sm overflow-hidden p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Daftar Transaksi Operasional</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Catat dan kelola pengeluaran biaya operasional atau pendapatan lain-lain.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="openCreateModal('beban')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                        <flux:icon name="plus" class="size-4" />
                        Catat Beban Operasional
                    </button>
                    <button wire:click="openCreateModal('pendapatan_lain')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                        <flux:icon name="plus" class="size-4" />
                        Catat Pendapatan Lain
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
                    <thead class="bg-zinc-50/80 text-xs uppercase text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-bold">Tanggal</th>
                            <th class="px-4 py-3 font-bold">Jenis</th>
                            <th class="px-4 py-3 font-bold">Kategori / Pos</th>
                            <th class="px-4 py-3 font-bold">Keterangan</th>
                            <th class="px-4 py-3 font-bold text-right">Nominal</th>
                            <th class="px-4 py-3 font-bold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                        @forelse($transaksiPaginated as $item)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="px-4 py-3 text-xs font-mono">{{ $item->tanggal->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-bold {{ $item->jenis === 'beban' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' }}">
                                        {{ $item->jenis === 'beban' ? 'Beban Operasional' : 'Pendapatan Lain' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-bold text-zinc-900 dark:text-white">{{ $item->kategori }}</td>
                                <td class="px-4 py-3 text-xs text-zinc-500 max-w-xs truncate">{{ $item->keterangan ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold {{ $item->jenis === 'beban' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $item->jenis === 'beban' ? '-' : '+' }} Rp {{ number_format($item->nominal, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button wire:click="openEditModal({{ $item->id }})" title="Edit"
                                            class="p-1 rounded-lg text-zinc-500 hover:text-indigo-600 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                            <flux:icon name="pencil-square" class="size-4" />
                                        </button>
                                        <button wire:click="deleteTransaksi({{ $item->id }})" wire:confirm="Yakin ingin menghapus data ini?" title="Hapus"
                                            class="p-1 rounded-lg text-zinc-500 hover:text-rose-600 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                            <flux:icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-zinc-400 dark:text-zinc-500">
                                    <flux:icon name="folder-open" class="size-8 mx-auto mb-2 opacity-30" />
                                    Belum ada transaksi pengeluaran / pendapatan lain tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $transaksiPaginated->links() }}
            </div>
        </div>
    @endif

    {{-- TAB 3: SIMULASI PEMBAGIAN SHU (AD / ART) --}}
    @if($activeTab === 'simulasi_shu')
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 border-b border-zinc-200 dark:border-zinc-800 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Simulasi Alokasi SHU Bersih Koperasi</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Pembagian Sisa Hasil Usaha sebesar <span class="font-bold text-blue-600">Rp {{ number_format($shuBersih, 0, ',', '.') }}</span> berdasarkan proporsi AD/ART.</p>
                </div>
                <button wire:click="resetSimulasi" class="text-xs font-bold text-indigo-600 hover:underline self-start md:self-auto">
                    Reset ke Standar (100%)
                </button>
            </div>

            @php
                $totalPersen = $p_cadangan + $p_jasa_anggota + $p_jasa_pinjaman + $p_pengurus + $p_karyawan + $p_pendidikan + $p_sosial;
            @endphp

            @if(abs($totalPersen - 100) > 0.01)
                <div class="rounded-xl bg-amber-500/10 border border-amber-500/30 p-3.5 text-xs text-amber-700 dark:text-amber-300 font-bold mb-6 flex items-center justify-between">
                    <span>Perhatian: Total alokasi saat ini adalah {{ number_format($totalPersen, 1) }}% (seharusnya tepat 100%).</span>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                {{-- Form Pengaturan Persentase --}}
                <div class="space-y-4 bg-zinc-50 dark:bg-zinc-800/40 p-5 rounded-2xl border border-zinc-200/60 dark:border-zinc-800">
                    <h4 class="font-bold text-sm text-zinc-800 dark:text-zinc-200">Proporsi Alokasi (% AD/ART)</h4>
                    
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block font-semibold text-zinc-600 dark:text-zinc-400 mb-1">Cadangan Koperasi (%)</label>
                            <input type="number" step="0.5" wire:model.live="p_cadangan" class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold font-mono" />
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-600 dark:text-zinc-400 mb-1">Jasa Simpanan Anggota (%)</label>
                            <input type="number" step="0.5" wire:model.live="p_jasa_anggota" class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold font-mono" />
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-600 dark:text-zinc-400 mb-1">Jasa Pinjaman Anggota (%)</label>
                            <input type="number" step="0.5" wire:model.live="p_jasa_pinjaman" class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold font-mono" />
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-600 dark:text-zinc-400 mb-1">Dana Pengurus & Pengawas (%)</label>
                            <input type="number" step="0.5" wire:model.live="p_pengurus" class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold font-mono" />
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-600 dark:text-zinc-400 mb-1">Dana Karyawan / Staff (%)</label>
                            <input type="number" step="0.5" wire:model.live="p_karyawan" class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold font-mono" />
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-600 dark:text-zinc-400 mb-1">Dana Pendidikan (%)</label>
                            <input type="number" step="0.5" wire:model.live="p_pendidikan" class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold font-mono" />
                        </div>
                        <div class="col-span-2">
                            <label class="block font-semibold text-zinc-600 dark:text-zinc-400 mb-1">Dana Sosial / Pembangunan (%)</label>
                            <input type="number" step="0.5" wire:model.live="p_sosial" class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 text-xs font-bold font-mono" />
                        </div>
                    </div>
                </div>

                {{-- Tabel Hasil Alokasi --}}
                <div class="space-y-4">
                    <h4 class="font-bold text-sm text-zinc-800 dark:text-zinc-200">Hasil Distribusi SHU Bersih</h4>
                    
                    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 font-bold uppercase text-zinc-600 dark:text-zinc-300">
                                <tr>
                                    <th class="px-4 py-2.5">Pos Alokasi SHU</th>
                                    <th class="px-3 py-2.5 text-center">Persentase</th>
                                    <th class="px-4 py-2.5 text-right">Nominal Alokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                                <tr>
                                    <td class="px-4 py-3 font-bold text-zinc-800 dark:text-zinc-200">Cadangan Koperasi</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $p_cadangan }}%</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($shuBersih * ($p_cadangan/100), 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-bold text-zinc-800 dark:text-zinc-200">Jasa Simpanan Anggota</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $p_jasa_anggota }}%</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-blue-600 dark:text-blue-400">Rp {{ number_format($shuBersih * ($p_jasa_anggota/100), 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-bold text-zinc-800 dark:text-zinc-200">Jasa Pinjaman Anggota</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $p_jasa_pinjaman }}%</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-blue-600 dark:text-blue-400">Rp {{ number_format($shuBersih * ($p_jasa_pinjaman/100), 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">Dana Pengurus & Pengawas</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $p_pengurus }}%</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format($shuBersih * ($p_pengurus/100), 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">Dana Karyawan / Staff</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $p_karyawan }}%</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format($shuBersih * ($p_karyawan/100), 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">Dana Pendidikan</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $p_pendidikan }}%</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format($shuBersih * ($p_pendidikan/100), 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">Dana Sosial & Pembangunan</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $p_sosial }}%</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format($shuBersih * ($p_sosial/100), 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 font-extrabold text-sm border-t-2 border-indigo-600">
                                <tr>
                                    <td class="px-4 py-3 uppercase">Total Alokasi SHU</td>
                                    <td class="px-3 py-3 text-center font-mono">{{ $totalPersen }}%</td>
                                    <td class="px-4 py-3 text-right font-mono text-indigo-600 dark:text-indigo-400">Rp {{ number_format($shuBersih * ($totalPersen/100), 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL INPUT & EDIT TRANSAKSI OPERASIONAL --}}
    @if($showModalForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 backdrop-blur-sm p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 p-6 shadow-2xl border border-zinc-200 dark:border-zinc-800 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between pb-4 border-b border-zinc-200 dark:border-zinc-800">
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">
                        {{ $transaksiId ? 'Edit Transaksi' : 'Catat Transaksi Baru' }}
                    </h3>
                    <button wire:click="$set('showModalForm', false)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                <form wire:submit="saveTransaksi" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1">Jenis Transaksi</label>
                        <select wire:model.live="formJenis" class="w-full rounded-xl border border-zinc-200 bg-white text-xs font-semibold dark:border-zinc-700 dark:bg-zinc-800 dark:text-white py-2">
                            <option value="beban">Beban Operasional (Pengeluaran)</option>
                            <option value="pendapatan_lain">Pendapatan Lainnya (Pemasukan)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1">Tanggal Transaksi</label>
                        <input type="date" wire:model="formTanggal" class="w-full rounded-xl border border-zinc-200 bg-white text-xs font-semibold dark:border-zinc-700 dark:bg-zinc-800 dark:text-white py-2" required />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1">Kategori / Pos</label>
                        @if($formJenis === 'beban')
                            <select wire:model="formKategori" class="w-full rounded-xl border border-zinc-200 bg-white text-xs font-semibold dark:border-zinc-700 dark:bg-zinc-800 dark:text-white py-2">
                                @foreach(TransaksiOperasional::kategoriBebanList() as $item)
                                    <option value="{{ $item }}">{{ $item }}</option>
                                @endforeach
                            </select>
                        @else
                            <select wire:model="formKategori" class="w-full rounded-xl border border-zinc-200 bg-white text-xs font-semibold dark:border-zinc-700 dark:bg-zinc-800 dark:text-white py-2">
                                @foreach(TransaksiOperasional::kategoriPendapatanLainList() as $item)
                                    <option value="{{ $item }}">{{ $item }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1">Nominal (Rp)</label>
                        <input type="number" wire:model="formNominal" placeholder="Contoh: 500000" class="w-full rounded-xl border border-zinc-200 bg-white text-xs font-mono font-bold dark:border-zinc-700 dark:bg-zinc-800 dark:text-white py-2" required min="0" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1">Keterangan / Catatan (Opsional)</label>
                        <textarea wire:model="formKeterangan" rows="3" placeholder="Rincian keterangan pengeluaran / pendapatan..." class="w-full rounded-xl border border-zinc-200 bg-white text-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <button type="button" wire:click="$set('showModalForm', false)"
                            class="rounded-xl px-4 py-2 text-xs font-bold text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                            class="rounded-xl bg-indigo-600 hover:bg-indigo-500 px-5 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                            Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
