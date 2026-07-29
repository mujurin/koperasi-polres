<?php

use App\Models\Angsuran;
use App\Models\Penarikan;
use App\Models\Pinjaman;
use App\Models\SimpananPokok;
use App\Models\SimpananWajib;
use App\Models\TransaksiOperasional;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $selectedYear;
    public string $selectedMonth = 'semua';
    public int $perPage = 15;

    public function mount()
    {
        TransaksiOperasional::ensureTableExists();
        $this->selectedYear = (string) date('Y');
    }

    public function updatingSelectedYear(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedMonth(): void
    {
        $this->resetPage();
    }

    public function setMonth(string $month)
    {
        $this->selectedMonth = $month;
    }

    public function with(): array
    {
        TransaksiOperasional::ensureTableExists();
        $year = (int) $this->selectedYear;
        $month = $this->selectedMonth !== 'semua' ? (int) $this->selectedMonth : null;

        $years = collect()
            ->merge(SimpananPokok::selectRaw('YEAR(tanggal) as year')->pluck('year')->toArray())
            ->merge(SimpananWajib::selectRaw('YEAR(created_at) as year')->pluck('year')->toArray())
            ->merge(Penarikan::selectRaw('YEAR(tanggal) as year')->pluck('year')->toArray())
            ->merge(Pinjaman::selectRaw('YEAR(updated_at) as year')->pluck('year')->toArray())
            ->merge(Angsuran::selectRaw('YEAR(tanggal_bayar) as year')->pluck('year')->toArray())
            ->merge(TransaksiOperasional::selectRaw('YEAR(tanggal) as year')->pluck('year')->toArray())
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (empty($years)) {
            $years = [(int) date('Y')];
        }

        $entries = [];

        $makeEntry = function (string $tanggal, string $uraian, float $pemasukan, float $pengeluaran, int $priority, int $period = 0, string $detail = '') {
            return [
                'tanggal' => $tanggal,
                'uraian' => $uraian,
                'pemasukan' => $pemasukan,
                'pengeluaran' => $pengeluaran,
                'priority' => $priority,
                'period' => $period,
                'detail' => $detail,
            ];
        };

        $queryYear = fn ($query, $column) => $query->when($year, fn ($q) => $q->whereYear($column, $year));
        $queryMonth = fn ($query, $column) => $query->when($month, fn ($q) => $q->whereMonth($column, $month));

        $simpananPokok = $queryYear(SimpananPokok::query(), 'tanggal');
        if ($month) {
            $simpananPokok = $queryMonth($simpananPokok, 'tanggal');
        }
        foreach ($simpananPokok->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Simpanan Pokok dari ' . optional($item->user)->name,
                (float) $item->jumlah,
                0,
                2,
                0,
                optional($item->user)->name
            );
        }

        $simpananWajib = SimpananWajib::query();
        if ($year) {
            $simpananWajib->whereYear('created_at', $year);
        }
        if ($month) {
            $simpananWajib->whereMonth('created_at', $month);
        }
        foreach ($simpananWajib->orderBy('created_at')->get() as $item) {
            $date = $item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d') : date('Y-m-d');
            $period = ($item->tahun * 100) + $item->bulan;
            $entries[] = $makeEntry(
                $date,
                'Simpanan Wajib ' . $item->bulan . '/' . $item->tahun . ' oleh ' . optional($item->user)->name,
                (float) $item->jumlah,
                0,
                3,
                $period,
                optional($item->user)->name
            );
        }

        $angsurans = Angsuran::with('pinjaman.user')->where('status_pembayaran', 'lunas');
        if ($year) {
            $angsurans->whereYear('tanggal_bayar', $year);
        }
        if ($month) {
            $angsurans->whereMonth('tanggal_bayar', $month);
        }
        foreach ($angsurans->orderBy('tanggal_bayar')->get() as $item) {
            $entries[] = $makeEntry(
                Carbon::parse($item->tanggal_bayar)->format('Y-m-d'),
                'Pembayaran Angsuran oleh ' . optional($item->pinjaman->user)->name,
                (float) $item->jumlah_bayar,
                0,
                1,
                Carbon::parse($item->tanggal_bayar)->format('Ymd'),
                optional($item->pinjaman->user)->name
            );
        }

        $pendapatanLain = TransaksiOperasional::where('jenis', 'pendapatan_lain');
        if ($year) {
            $pendapatanLain->whereYear('tanggal', $year);
        }
        if ($month) {
            $pendapatanLain->whereMonth('tanggal', $month);
        }
        foreach ($pendapatanLain->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Pendapatan: ' . $item->kategori,
                (float) $item->nominal,
                0,
                4,
                Carbon::parse($item->tanggal)->format('Ymd'),
                $item->kategori
            );
        }

        $pinjamanDisetujui = Pinjaman::whereIn('status', ['disetujui', 'lunas']);
        if ($year) {
            $pinjamanDisetujui->whereYear('updated_at', $year);
        }
        if ($month) {
            $pinjamanDisetujui->whereMonth('updated_at', $month);
        }
        foreach ($pinjamanDisetujui->orderBy('updated_at')->get() as $item) {
            $entries[] = $makeEntry(
                Carbon::parse($item->updated_at)->format('Y-m-d'),
                'Pencairan Pinjaman untuk ' . optional($item->user)->name,
                0,
                (float) $item->jumlah_diterima,
                5,
                Carbon::parse($item->updated_at)->format('Ymd'),
                optional($item->user)->name
            );
        }

        $penarikan = Penarikan::where('status', 'disetujui');
        if ($year) {
            $penarikan->whereYear('tanggal', $year);
        }
        if ($month) {
            $penarikan->whereMonth('tanggal', $month);
        }
        foreach ($penarikan->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Penarikan oleh ' . optional($item->user)->name,
                0,
                (float) $item->jumlah,
                6,
                Carbon::parse($item->tanggal)->format('Ymd'),
                optional($item->user)->name
            );
        }

        $bebanOperasional = TransaksiOperasional::where('jenis', 'beban');
        if ($year) {
            $bebanOperasional->whereYear('tanggal', $year);
        }
        if ($month) {
            $bebanOperasional->whereMonth('tanggal', $month);
        }
        foreach ($bebanOperasional->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Beban: ' . $item->kategori,
                0,
                (float) $item->nominal,
                7,
                Carbon::parse($item->tanggal)->format('Ymd'),
                $item->kategori
            );
        }

        usort($entries, function ($a, $b) {
            if ($a['tanggal'] !== $b['tanggal']) {
                return $a['tanggal'] < $b['tanggal'] ? -1 : 1;
            }
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] < $b['priority'] ? -1 : 1;
            }
            if ($a['period'] !== $b['period']) {
                return $a['period'] < $b['period'] ? -1 : 1;
            }
            return strcmp($a['uraian'] . ' ' . $a['detail'], $b['uraian'] . ' ' . $b['detail']);
        });

        $runningBalance = 0;
        foreach ($entries as $index => $entry) {
            $runningBalance += $entry['pemasukan'] - $entry['pengeluaran'];
            $entries[$index]['saldo'] = $runningBalance;
        }

        $totalPemasukan = array_sum(array_column($entries, 'pemasukan'));
        $totalPengeluaran = array_sum(array_column($entries, 'pengeluaran'));
        $saldoAkhir = $runningBalance;

        $page = max(1, (int) ($this->page ?? request()->query('page', 1)));
        $paginatedEntries = new LengthAwarePaginator(
            array_slice($entries, ($page - 1) * $this->perPage, $this->perPage),
            count($entries),
            $this->perPage,
            $page,
            ['path' => url()->current()]
        );

        return array_merge(
            compact('years', 'totalPemasukan', 'totalPengeluaran', 'saldoAkhir'),
            ['entries' => $paginatedEntries]
        );
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-full mx-auto">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-800">
        <div class="flex items-center gap-3">
            <div class="flex size-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400 shadow-sm">
                <flux:icon name="scale" class="size-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Buku Kas</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Rekap pemasukan dan pengeluaran kas koperasi.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800 shadow-inner">
                <button wire:click="setMonth('semua')"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {{ $selectedMonth === 'semua' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                    Semua Bulan
                </button>
                @foreach(range(1, 12) as $month)
                    <button wire:click="setMonth('{{ $month }}')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {{ $selectedMonth === (string)$month ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                        {{ str_pad($month, 2, '0', STR_PAD_LEFT) }}
                    </button>
                @endforeach
            </div>

            <select wire:model.live="selectedYear"
                class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 outline-none transition-colors focus:border-emerald-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                @foreach($years as $yearOption)
                    <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                @endforeach
            </select>

            <a href="{{ route('laporan.buku-kas.download') }}?year={{ $selectedYear }}&month={{ $selectedMonth }}"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3.5 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/80 shadow-sm transition-colors"
                download="Buku_Kas_{{ $selectedYear }}_{{ $selectedMonth === 'semua' ? 'semua' : $selectedMonth }}.pdf">
                <flux:icon name="arrow-down-tray" class="size-4 text-zinc-500" /> Unduh PDF Buku Kas
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-3xl border border-emerald-200/70 bg-emerald-50/70 p-5 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/20">
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-emerald-700">Total Pemasukan</p>
            <p class="mt-4 text-2xl font-extrabold text-zinc-900 dark:text-white">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-3xl border border-rose-200/70 bg-rose-50/70 p-5 shadow-sm dark:border-rose-900/40 dark:bg-rose-950/20">
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-rose-700">Total Pengeluaran</p>
            <p class="mt-4 text-2xl font-extrabold text-zinc-900 dark:text-white">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/40">
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-zinc-500">Saldo Akhir</p>
            <p class="mt-4 text-2xl font-extrabold text-zinc-900 dark:text-white">Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-950">
        <table class="min-w-full border-collapse text-left text-sm text-zinc-700 dark:text-zinc-200">
            <thead class="bg-zinc-50 text-xs uppercase tracking-[0.15em] text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-4">No</th>
                    <th class="px-4 py-4">Tanggal</th>
                    <th class="px-4 py-4">Uraian</th>
                    <th class="px-4 py-4 text-right">Pemasukan</th>
                    <th class="px-4 py-4 text-right">Pengeluaran</th>
                    <th class="px-4 py-4 text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $index => $entry)
                    <tr class="border-t border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-4 font-medium text-zinc-800 dark:text-zinc-100">{{ $index + 1 }}</td>
                        <td class="px-4 py-4 text-zinc-600 dark:text-zinc-300">{{ $entry['tanggal'] }}</td>
                        <td class="px-4 py-4 text-zinc-800 dark:text-zinc-100">{{ $entry['uraian'] }}</td>
                        <td class="px-4 py-4 text-right text-emerald-600 dark:text-emerald-400">{{ $entry['pemasukan'] > 0 ? 'Rp ' . number_format($entry['pemasukan'], 0, ',', '.') : '-' }}</td>
                        <td class="px-4 py-4 text-right text-rose-600 dark:text-rose-400">{{ $entry['pengeluaran'] > 0 ? 'Rp ' . number_format($entry['pengeluaran'], 0, ',', '.') : '-' }}</td>
                        <td class="px-4 py-4 text-right text-zinc-900 dark:text-white">Rp {{ number_format($entry['saldo'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">Tidak ada data Buku Kas untuk periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($entries->hasPages())
        <div class="border-t border-zinc-100 dark:border-zinc-800 p-4">
            {{ $entries->links() }}
        </div>
    @endif
</div>
