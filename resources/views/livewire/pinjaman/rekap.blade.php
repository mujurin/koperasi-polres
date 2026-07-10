<?php

use App\Models\Pinjaman;
use App\Models\Angsuran;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public $filter = 'semua';
    public $year;

    public function mount()
    {
        $this->year = date('Y');
    }

    public function setFilter($val)
    {
        $this->filter = $val;
    }

    public function setYear($val)
    {
        $this->year = $val;
    }

    public function updatedYear()
    {
        $this->filter = 'tahun';
    }

    public function with(): array
    {
        $activePinjamans = Pinjaman::whereIn('status', ['disetujui', 'lunas'])->with([
            'user',
            'angsurans' => function ($q) {
                $q->where('status_pembayaran', 'lunas');
            }
        ])->get();

        $totalPinjamanCair = 0;
        $totalPokok = 0;
        $totalJasa = 0;
        $totalTunggakanPokok = 0;
        $totalTunggakanJasa = 0;

        $rekapBulan = [];
        $totalPerBulan = array_fill(1, 12, ['pokok' => 0, 'jasa' => 0]);
        $now = Carbon::now();

        foreach ($activePinjamans as $pinjaman) {
            $user = $pinjaman->user;
            if (!$user)
                continue;

            $userId = $user->id;

            if (!isset($rekapBulan[$userId])) {
                $rekapBulan[$userId] = [
                    'user' => $user,
                    'months' => array_fill(1, 12, ['pokok' => 0, 'jasa' => 0]),
                    'total_pokok' => 0,
                    'total_jasa' => 0,
                    'tunggakan_pokok' => 0,
                    'tunggakan_jasa' => 0,
                    'tunggakan_bulan' => 0,
                    'is_kompensasi' => false,
                    'kompensasi_month' => null,
                    'acc_month' => null,
                    'max_valid_month' => 12,
                    'current_month' => Carbon::now()->month
                ];
            }

            if (str_contains(strtolower($pinjaman->keterangan ?? ''), 'kompensasi')) {
                $rekapBulan[$userId]['is_kompensasi'] = true;
                $rekapBulan[$userId]['kompensasi_month'] = Carbon::parse($pinjaman->updated_at)->month;
            }

            if ($pinjaman->status === 'disetujui' && $pinjaman->created_at) {
                $pencairanDate = Carbon::parse($pinjaman->created_at)->startOfMonth();
                $currentDate = $now->copy()->startOfMonth();
                
                if ($pencairanDate->year < $this->year) {
                    $rekapBulan[$userId]['acc_month'] = 0;
                } elseif ($pencairanDate->year == $this->year) {
                    $rekapBulan[$userId]['acc_month'] = $pencairanDate->month;
                } else {
                    $rekapBulan[$userId]['acc_month'] = 12; // future loan in selected year
                }
                
                $endDate = $pencairanDate->copy()->addMonths((int) $pinjaman->tenor);
                if ($endDate->year < $this->year) {
                    $rekapBulan[$userId]['max_valid_month'] = 0;
                } elseif ($endDate->year == $this->year) {
                    $rekapBulan[$userId]['max_valid_month'] = $endDate->month;
                } else {
                    $rekapBulan[$userId]['max_valid_month'] = 12;
                }
                
                $expectedMonths = $pencairanDate->diffInMonths($currentDate);

                $expectedMonths = min($expectedMonths, $pinjaman->tenor);

                $lunasCount = $pinjaman->angsurans->count();

                if ($expectedMonths > $lunasCount) {
                    $missedMonths = $expectedMonths - $lunasCount;
                    $pokokBulan = $pinjaman->jumlah_ajuan / $pinjaman->tenor;
                    $jasaBulan = $pinjaman->jumlah_ajuan * 0.01;

                    $rekapBulan[$userId]['tunggakan_bulan'] += $missedMonths;
                    $rekapBulan[$userId]['tunggakan_pokok'] += ($missedMonths * $pokokBulan);
                    $rekapBulan[$userId]['tunggakan_jasa'] += ($missedMonths * $jasaBulan);

                    $totalTunggakanPokok += ($missedMonths * $pokokBulan);
                    $totalTunggakanJasa += ($missedMonths * $jasaBulan);
                }
            }

            $includePinjaman = false;
            if ($this->filter === 'semua') {
                $includePinjaman = true;
            } elseif ($this->filter === 'tahun' && Carbon::parse($pinjaman->created_at)->year == $this->year) {
                $includePinjaman = true;
            } elseif ($this->filter === 'bulan' && Carbon::parse($pinjaman->created_at)->isCurrentMonth()) {
                $includePinjaman = true;
            } elseif ($this->filter === 'minggu' && Carbon::parse($pinjaman->created_at)->isCurrentWeek()) {
                $includePinjaman = true;
            }

            if ($includePinjaman) {
                $totalPinjamanCair += $pinjaman->jumlah_ajuan;
            }

            foreach ($pinjaman->angsurans as $angsuran) {
                $tglBayar = Carbon::parse($angsuran->tanggal_bayar);

                // Filter for Cards (Top Summary)
                $includeAngsuranCard = false;
                if ($this->filter === 'semua') {
                    $includeAngsuranCard = true;
                } elseif ($this->filter === 'tahun' && $tglBayar->year == $this->year) {
                    $includeAngsuranCard = true;
                } elseif ($this->filter === 'bulan' && $tglBayar->isCurrentMonth()) {
                    $includeAngsuranCard = true;
                } elseif ($this->filter === 'minggu' && $tglBayar->isCurrentWeek()) {
                    $includeAngsuranCard = true;
                }

                $ajuan = $pinjaman->jumlah_ajuan ?? 0;
                $jasa = min($angsuran->jumlah_bayar, $ajuan * 0.01);
                $pokok = max(0, $angsuran->jumlah_bayar - $jasa);

                if ($includeAngsuranCard) {
                    $totalPokok += $pokok;
                    $totalJasa += $jasa;
                }

                // Filter for Matrix Table (Strictly Year-based)
                if ($tglBayar->year == $this->year) {
                    $month = $tglBayar->month;

                    $rekapBulan[$userId]['months'][$month]['pokok'] += $pokok;
                    $rekapBulan[$userId]['months'][$month]['jasa'] += $jasa;
                    $rekapBulan[$userId]['total_pokok'] += $pokok;
                    $rekapBulan[$userId]['total_jasa'] += $jasa;

                    $totalPerBulan[$month]['pokok'] += $pokok;
                    $totalPerBulan[$month]['jasa'] += $jasa;
                }
            }
        }

        $rekapBulan = array_filter($rekapBulan, function ($row) {
            return $row['total_pokok'] > 0 || $row['total_jasa'] > 0 || $row['tunggakan_bulan'] > 0;
        });

        usort($rekapBulan, function ($a, $b) {
            return strcmp($a['user']->name, $b['user']->name);
        });

        return compact('totalPinjamanCair', 'totalPokok', 'totalJasa', 'rekapBulan', 'totalPerBulan', 'totalTunggakanPokok', 'totalTunggakanJasa');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-full mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Rekap Pinjaman</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Ringkasan total pinjaman terealisasi, cicilan pokok, dan
                jasa.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('pinjaman.rekap.download') }}?year={{ $year }}&filter={{ $filter }}" target="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-black shadow-sm hover:bg-red-500 transition-all cursor-pointer mr-2">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                PDF
            </a>
            
            {{-- Custom Tabs Filter --}}
        <div class="inline-flex rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800 self-start sm:self-auto shrink-0 shadow-sm">
            <button wire:click="setFilter('minggu')"
                class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $filter === 'minggu' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }} transition-all">
                Minggu Ini
            </button>
            <button wire:click="setFilter('bulan')"
                class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $filter === 'bulan' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }} transition-all">
                Bulan Ini
            </button>
            <button wire:click="setFilter('tahun')"
                class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $filter === 'tahun' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }} transition-all">
                Tahun Ini
            </button>
            <button wire:click="setFilter('semua')"
                class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $filter === 'semua' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }} transition-all">
                Semua Waktu
            </button>
        </div>
    </div>
    </div>

    {{-- Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6 mt-4">
        {{-- Card: Total Terealisasi --}}
        <div
            class="relative overflow-hidden rounded-2xl border border-blue-200 bg-gradient-to-b from-blue-50 to-white p-6 shadow-sm dark:border-blue-900/50 dark:from-blue-950/20 dark:to-zinc-900 group">
            <div class="absolute right-0 top-0 -mr-6 -mt-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <flux:icon name="banknotes" class="size-32 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">
                        <flux:icon name="arrow-up-right" class="size-5" />
                    </div>
                    <p class="mt-4 text-sm font-semibold text-zinc-600 dark:text-zinc-400">Total Terealisasi</p>
                </div>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">
                    Rp {{ number_format($totalPinjamanCair, 0, ',', '.') }}
                </div>
                <div class="mt-1 flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="mr-1 inline-flex items-center text-blue-600 dark:text-blue-400">
                        <flux:icon name="information-circle" class="mr-1 size-3" />
                    </span>
                    Nominal disetujui / dipinjamkan
                </div>
            </div>
        </div>

        {{-- Card: Setoran Pokok --}}
        <div
            class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-b from-emerald-50 to-white p-6 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/20 dark:to-zinc-900 group">
            <div class="absolute right-0 top-0 -mr-6 -mt-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <flux:icon name="arrow-down-tray" class="size-32 text-emerald-600 dark:emerald-400" />
            </div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                        <flux:icon name="wallet" class="size-5" />
                    </div>
                    <p class="mt-4 text-sm font-semibold text-zinc-600 dark:text-zinc-400">Pokok Setoran Masuk</p>
                </div>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">
                    Rp {{ number_format($totalPokok, 0, ',', '.') }}
                </div>
                <div class="mt-1 flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="mr-1 inline-flex items-center text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="information-circle" class="mr-1 size-3" />
                    </span>
                    Porsi angsuran ke saldo piutang
                </div>
            </div>
        </div>

        {{-- Card: Pendapatan Jasa --}}
        <div
            class="relative overflow-hidden rounded-2xl border border-orange-200 bg-gradient-to-b from-orange-50 to-white p-6 shadow-sm dark:border-orange-900/50 dark:from-orange-950/20 dark:to-zinc-900 group">
            <div class="absolute right-0 top-0 -mr-6 -mt-6 opacity-10 group-hover:opacity-20 transition-opacity">
                <flux:icon name="chart-bar" class="size-32 text-orange-600 dark:text-orange-400" />
            </div>
            <div class="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-900/50 dark:text-orange-400">
                        <flux:icon name="arrow-trending-up" class="size-5" />
                    </div>
                    <p class="mt-4 text-sm font-semibold text-zinc-600 dark:text-zinc-400">Pendapatan Jasa (1%)</p>
                </div>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">
                    Rp {{ number_format($totalJasa, 0, ',', '.') }}
                </div>
                <div class="mt-1 flex items-center text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="mr-1 inline-flex items-center text-orange-600 dark:text-orange-400">
                        <flux:icon name="information-circle" class="mr-1 size-3" />
                    </span>
                    Total jasa dari setoran lunas
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Matrix Table --}}
    <div
        class="mt-6 rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden shadow-sm">
        <div
            class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 flex justify-between items-center bg-zinc-50/50 dark:bg-zinc-800/20">
            <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Rincian Angsuran Anggota</h3>

            @if($filter === 'tahun' || $filter === 'semua')
                <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 font-semibold">
                    <span>Tahun:</span>
                    <select wire:model.live="year"
                        class="rounded-md border border-zinc-200 bg-white text-zinc-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 py-1.5 pl-3 pr-8 transition-colors">
                        @for($i = date('Y') + 1; $i >= date('Y') - 5; $i--)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-[11px] md:text-sm text-zinc-600 dark:text-zinc-400 min-w-max">
                <thead
                    class="bg-zinc-50/80 text-[10px] sm:text-xs uppercase text-zinc-500 dark:bg-zinc-800/30 dark:text-zinc-400">
                    <tr>
                        <th
                            class="px-4 py-3 font-bold sticky left-0 z-10 bg-zinc-50 dark:bg-zinc-900 min-w-[160px] border-r border-zinc-200 dark:border-zinc-800 shadow-[1px_0_0_rgba(0,0,0,0.05)] dark:shadow-[1px_0_0_rgba(255,255,255,0.02)]">
                            Nama / NRP</th>
                        @foreach(['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'] as $m)
                            <th
                                class="px-2 py-3 font-semibold text-center border-r border-zinc-200/60 dark:border-zinc-800/60">
                                {{ $m }}
                            </th>
                        @endforeach
                        <th class="px-4 py-3 font-bold text-right bg-zinc-100/50 dark:bg-zinc-800/50">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/60">
                    @forelse($rekapBulan as $row)
                        <tr
                            class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors group text-[10px] sm:text-xs">
                            <td
                                class="px-4 py-3 sticky left-0 z-10 bg-white dark:bg-zinc-900 group-hover:bg-zinc-50/80 dark:group-hover:bg-zinc-800/50 transition-colors border-r border-zinc-200 dark:border-zinc-800 shadow-[1px_0_0_rgba(0,0,0,0.05)] dark:shadow-[1px_0_0_rgba(255,255,255,0.02)]">
                                <p class="font-bold text-zinc-900 dark:text-zinc-200 truncate max-w-[140px] sm:max-w-[200px]"
                                    title="{{ $row['user']->name }}">{{ $row['user']->name }}</p>
                                <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                    <p
                                        class="text-[9px] text-zinc-500 dark:text-zinc-500 monospace font-mono tracking-tight">
                                        {{ $row['user']->nrp }}
                                    </p>

                                    @if($row['tunggakan_bulan'] > 0)
                                        <span
                                            class="inline-flex rounded-sm bg-rose-100 px-1 py-px text-[7px] font-bold text-rose-700 uppercase tracking-widest dark:bg-rose-900/40 dark:text-rose-400">Nunggak
                                            {{ $row['tunggakan_bulan'] }} Bln</span>
                                    @endif
                                </div>
                                @if($row['tunggakan_bulan'] > 0)
                                    <div
                                        class="mt-1 flex flex-col gap-0.5 mt-1 border-t border-rose-100 dark:border-rose-900/30 pt-1">
                                        <div class="flex justify-between w-full pr-1">
                                            <span class="text-[7px] text-rose-600 dark:text-rose-400 font-bold uppercase">Tgk
                                                P:</span>
                                            <span
                                                class="text-[8px] text-rose-600 dark:text-rose-400 font-mono font-bold">{{ number_format($row['tunggakan_pokok'], 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between w-full pr-1">
                                            <span
                                                class="text-[7px] text-orange-600 dark:text-orange-400 font-bold uppercase">Tgk
                                                J:</span>
                                            <span
                                                class="text-[8px] text-orange-600 dark:text-orange-400 font-mono font-bold">{{ number_format($row['tunggakan_jasa'], 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @endif
                            </td>

                            @for($i = 1; $i <= 12; $i++)
                                <td class="px-2 py-2 border-r border-zinc-200/50 dark:border-zinc-800/50">
                                    @if($row['months'][$i]['pokok'] > 0 || $row['months'][$i]['jasa'] > 0)
                                        <div class="flex flex-col gap-1 items-end min-w-[70px]">
                                            @if($row['months'][$i]['pokok'] > 0)
                                                <div class="flex items-center gap-1 w-full justify-between">
                                                    <span
                                                        class="text-[8px] font-bold text-emerald-600/70 dark:text-emerald-500/70 uppercase">P:</span>
                                                    <span
                                                        class="text-zinc-700 dark:text-zinc-300 font-mono tracking-tight">{{ number_format($row['months'][$i]['pokok'], 0, ',', '.') }}</span>
                                                </div>
                                            @endif
                                            @if($row['months'][$i]['jasa'] > 0)
                                                <div class="flex items-center gap-1 w-full justify-between">
                                                    <span
                                                        class="text-[8px] font-bold text-blue-500/80 dark:text-blue-500/80 uppercase">J:</span>
                                                    <span
                                                        class="text-zinc-700 dark:text-zinc-300 font-mono tracking-tight">{{ number_format($row['months'][$i]['jasa'], 0, ',', '.') }}</span>
                                                </div>
                                            @endif
                                            @if(isset($row['is_kompensasi']) && $row['is_kompensasi'] && $i == $row['kompensasi_month'])
                                                <div class="mt-0.5 w-full text-center text-[7px] font-bold text-amber-600 uppercase tracking-widest bg-amber-100 dark:bg-amber-900/40 dark:text-amber-400 py-0.5 rounded shadow-sm">Kompensasi</div>
                                            @endif
                                        </div>
                                    @else
                                        @if(isset($row['is_kompensasi']) && $row['is_kompensasi'] && $i <= $row['kompensasi_month'])
                                            <div class="text-center text-[8px] font-bold text-amber-500/80 uppercase tracking-widest">Kompensasi</div>
                                        @elseif(isset($row['acc_month']) && $i > $row['acc_month'] && $i <= $row['current_month'] && $i <= ($row['max_valid_month'] ?? 12))
                                            <div class="flex items-center justify-center w-full h-full">
                                                <div class="inline-flex rounded bg-rose-100 px-1.5 py-0.5 text-[7.5px] font-bold text-rose-700 uppercase tracking-widest dark:bg-rose-900/40 dark:text-rose-400 shadow-sm">Nunggak</div>
                                            </div>
                                        @else
                                            <div class="text-center text-zinc-300 dark:text-zinc-700/50 font-black">-</div>
                                        @endif
                                    @endif
                                </td>
                            @endfor

                            <td class="px-4 py-3 bg-zinc-50/50 dark:bg-zinc-800/20 text-right">
                                <div class="flex flex-col gap-1.5 items-end min-w-[90px]">
                                    <div class="flex items-center gap-1.5 w-full justify-between">
                                        <span
                                            class="text-[9px] font-bold text-emerald-600 dark:text-emerald-500 uppercase">Tot
                                            P:</span>
                                        <span
                                            class="font-bold text-zinc-900 dark:text-white font-mono tracking-tighter">{{ number_format($row['total_pokok'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 w-full justify-between">
                                        <span class="text-[9px] font-bold text-blue-600 dark:text-blue-500 uppercase">Tot
                                            J:</span>
                                        <span
                                            class="font-bold text-zinc-900 dark:text-white font-mono tracking-tighter">{{ number_format($row['total_jasa'], 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-5 py-12 bg-zinc-50/50 dark:bg-zinc-900/50">
                                <div
                                    class="flex flex-col items-center justify-center text-zinc-400 dark:text-zinc-500 font-semibold">
                                    <flux:icon name="document-magnifying-glass" class="size-8 mb-3 opacity-20" />
                                    Tidak ada data untuk periode terpilih.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($rekapBulan) > 0)
                    <tfoot
                        class="bg-zinc-100/80 text-[10px] sm:text-xs uppercase text-zinc-800 dark:bg-zinc-800/80 dark:text-zinc-200">
                        <tr>
                            <th
                                class="px-4 py-3 font-bold sticky left-0 z-10 bg-zinc-100/90 dark:bg-zinc-800/90 border-t border-zinc-300 dark:border-zinc-700 shadow-[1px_0_0_rgba(0,0,0,0.05)] dark:shadow-[1px_0_0_rgba(255,255,255,0.02)]">
                                <div>TOTAL</div>
                                @if($totalTunggakanPokok > 0 || $totalTunggakanJasa > 0)
                                    <div
                                        class="mt-1 flex flex-col gap-0.5 border-t border-rose-200/50 dark:border-rose-900/30 pt-1 text-[9px] font-normal leading-tight">
                                        <div class="flex justify-between w-full max-w-[140px] pr-1">
                                            <span class="text-rose-600 dark:text-rose-400 font-bold uppercase">Tot Tgk P:</span>
                                            <span
                                                class="text-rose-600 dark:text-rose-400 font-mono font-bold">{{ number_format($totalTunggakanPokok, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between w-full max-w-[140px] pr-1">
                                            <span class="text-blue-600 dark:text-blue-400 font-bold uppercase">Tot Tgk J:</span>
                                            <span
                                                class="text-blue-600 dark:text-blue-400 font-mono font-bold">{{ number_format($totalTunggakanJasa, 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @endif
                            </th>
                            @for($i = 1; $i <= 12; $i++)
                                <th class="px-2 py-3 border-t border-r border-zinc-200 dark:border-zinc-700/50">
                                    @if($totalPerBulan[$i]['pokok'] > 0 || $totalPerBulan[$i]['jasa'] > 0)
                                        <div class="flex flex-col gap-1 items-end min-w-[70px]">
                                            @if($totalPerBulan[$i]['pokok'] > 0)
                                                <div class="flex items-center gap-1 w-full justify-between">
                                                    <span class="text-[8px] font-bold text-emerald-700 dark:text-emerald-400">P:</span>
                                                    <span
                                                        class="font-mono font-bold tracking-tight">{{ number_format($totalPerBulan[$i]['pokok'], 0, ',', '.') }}</span>
                                                </div>
                                            @endif
                                            @if($totalPerBulan[$i]['jasa'] > 0)
                                                <div class="flex items-center gap-1 w-full justify-between">
                                                    <span class="text-[8px] font-bold text-blue-700 dark:text-blue-400">J:</span>
                                                    <span
                                                        class="font-mono font-bold tracking-tight">{{ number_format($totalPerBulan[$i]['jasa'], 0, ',', '.') }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-center text-zinc-400 dark:text-zinc-600 font-black">-</div>
                                    @endif
                                </th>
                            @endfor
                            <th
                                class="px-4 py-3 border-t border-zinc-300 dark:border-zinc-700 bg-zinc-200/50 dark:bg-zinc-700/50 text-right">
                                <div class="flex flex-col gap-1.5 items-end min-w-[90px]">
                                    <div class="flex items-center gap-1.5 w-full justify-between">
                                        <span
                                            class="text-[9px] font-bold text-emerald-700 dark:text-emerald-400 uppercase">Tot
                                            P:</span>
                                        <span
                                            class="font-bold text-zinc-900 dark:text-white font-mono tracking-tighter">{{ number_format($totalPokok, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 w-full justify-between">
                                        <span class="text-[9px] font-bold text-blue-700 dark:text-blue-400 uppercase">Tot
                                            J:</span>
                                        <span
                                            class="font-bold text-zinc-900 dark:text-white font-mono tracking-tighter">{{ number_format($totalJasa, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>