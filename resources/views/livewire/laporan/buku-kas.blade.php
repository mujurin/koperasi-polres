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
    public string $selectedMonth;
    public int $perPage = 15;

    public string $formTanggal = '';
    public string $formJenis = 'pendapatan_lain';
    public string $formKategori = '';
    public string $formNominal = '';
    public string $formKeterangan = '';

    public function mount()
    {
        TransaksiOperasional::ensureTableExists();
        $this->selectedYear = (string) date('Y');
        $this->selectedMonth = (string) date('n');
        $this->formTanggal = date('Y-m-d');
    }

    public function simpanTransaksi()
    {
        $this->validate([
            'formTanggal' => 'required|date',
            'formJenis' => 'required|in:beban,pendapatan_lain',
            'formKategori' => 'required|string|max:255',
            'formNominal' => 'required|numeric|min:1',
            'formKeterangan' => 'nullable|string',
        ]);

        TransaksiOperasional::create([
            'tanggal' => $this->formTanggal,
            'jenis' => $this->formJenis,
            'kategori' => $this->formKategori,
            'nominal' => $this->formNominal,
            'keterangan' => $this->formKeterangan,
        ]);

        $this->reset(['formKategori', 'formNominal', 'formKeterangan']);
        $this->formJenis = 'pendapatan_lain';
        $this->formTanggal = date('Y-m-d');

        \Flux::modal('modal-tambah-kas')->close();
        \Flux::toast('Data kas berhasil ditambahkan');
    }

    public function filterData(): void
    {
        $this->resetPage();
    }

    public function downloadExcel()
    {
        $data = $this->getEntries();
        
        $filename = 'Buku_Kas_' . $this->selectedYear;
        if ($this->selectedMonth !== 'semua') {
            $filename .= '_' . \Carbon\Carbon::create()->month((int)$this->selectedMonth)->translatedFormat('F');
        }
        $filename .= '.csv';

        return response()->streamDownload(function () use ($data) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, ['No', 'Tanggal', 'Uraian', 'Pemasukan', 'Pengeluaran', 'Saldo']);
            
            foreach ($data['entries'] as $index => $entry) {
                fputcsv($file, [
                    $index + 1,
                    $entry['tanggal'],
                    $entry['uraian'],
                    $entry['pemasukan'],
                    $entry['pengeluaran'],
                    $entry['saldo']
                ]);
            }
            fputcsv($file, ['', '', 'Total', $data['totalPemasukan'], $data['totalPengeluaran'], $data['saldoAkhir']]);
            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function getEntries(): array
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
        
        return compact('entries', 'years', 'totalPemasukan', 'totalPengeluaran', 'saldoAkhir');
    }

    public function with(): array
    {
        $data = $this->getEntries();
        $entries = $data['entries'];

        $page = max(1, (int) ($this->page ?? request()->query('page', 1)));
        $paginatedEntries = new LengthAwarePaginator(
            array_slice($entries, ($page - 1) * $this->perPage, $this->perPage),
            count($entries),
            $this->perPage,
            $page,
            ['path' => url()->current()]
        );

        return array_merge($data, ['entries' => $paginatedEntries]);
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

        <div class="flex flex-wrap items-center gap-2">
            <flux:modal.trigger name="modal-tambah-kas">
                <button
                    class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/50 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 shadow-sm transition-colors">
                    <flux:icon name="plus" class="size-4 text-indigo-600 dark:text-indigo-400" />
                    <span>Tambah Kas</span>
                </button>
            </flux:modal.trigger>

            <select wire:model="selectedMonth"
                class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 outline-none transition-colors focus:border-emerald-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                <option value="semua">Semua Bulan</option>
                @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                @endforeach
            </select>

            <select wire:model="selectedYear"
                class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 outline-none transition-colors focus:border-emerald-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                @foreach($years as $yearOption)
                    <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                @endforeach
            </select>

            <button wire:click="filterData" wire:loading.attr="disabled" wire:target="filterData"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3.5 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700/80 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="filterData" class="size-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                </svg>
                <svg wire:loading wire:target="filterData" class="size-4 animate-spin text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="filterData">Filter</span>
                <span wire:loading wire:target="filterData">Memproses...</span>
            </button>

            <button wire:click="downloadExcel" wire:loading.attr="disabled" wire:target="downloadExcel"
                class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <flux:icon wire:loading.remove wire:target="downloadExcel" name="table-cells" class="size-4 text-emerald-600 dark:text-emerald-400" />
                <svg wire:loading wire:target="downloadExcel" class="size-4 animate-spin text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="downloadExcel">Unduh Excel</span>
                <span wire:loading wire:target="downloadExcel">Memproses...</span>
            </button>

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
    
    <flux:modal name="modal-tambah-kas" class="md:w-[32rem]">
        <form wire:submit="simpanTransaksi" class="space-y-6">
            <div>
                <flux:heading size="lg">Tambah Kas Manual</flux:heading>
                <flux:subheading>Input pendapatan atau pengeluaran yang tidak terotomatisasi di sistem.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input type="date" wire:model="formTanggal" label="Tanggal" />
                
                <flux:radio.group wire:model.live="formJenis" label="Jenis Transaksi">
                    <flux:radio value="pendapatan_lain" label="Pemasukan" />
                    <flux:radio value="beban" label="Pengeluaran" />
                </flux:radio.group>

                <flux:input wire:model="formKategori" label="Kategori / Uraian Singkat" placeholder="Contoh: Pembelian ATK" />

                <div x-data="{
                    raw: $wire.entangle('formNominal'),
                    display: '',
                    init() {
                        this.$watch('raw', val => this.format(val));
                        this.format(this.raw);
                    },
                    format(val) {
                        let num = String(val||'').replace(/[^0-9]/g, '');
                        this.display = num ? new Intl.NumberFormat('id-ID').format(num) : '';
                    },
                    updateVal(e) {
                        let num = e.target.value.replace(/[^0-9]/g, '');
                        this.raw = num;
                        this.display = num ? new Intl.NumberFormat('id-ID').format(num) : '';
                    }
                }">
                    <flux:input x-model="display" @input="updateVal" type="text" inputmode="numeric" label="Nominal (Rp)" placeholder="Contoh: 150.000" />
                </div>

                <flux:textarea wire:model="formKeterangan" label="Keterangan Lengkap (Opsional)" rows="3" placeholder="Tambahkan catatan jika perlu..." />
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan Transaksi</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
