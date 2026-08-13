<?php

use App\Models\Pinjaman;
use App\Models\Angsuran;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    #[Url]
    public $type = 'reguler';

    public $activeTab = 'aktif';

    public $importMonth;
    public $importYear;
    public $excelFile;

    public function mount()
    {
        $this->importMonth = date('n');
        $this->importYear = date('Y');
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function with(): array
    {
        $query = Pinjaman::with('user')->orderBy('updated_at', 'desc');

        if ($this->type === 'primer') {
            $query->whereIn('jenis_permohonan', ['Handphone', 'Motor', 'Barang Lain']);
        } else {
            $query->whereNotIn('jenis_permohonan', ['Handphone', 'Motor', 'Barang Lain']);
        }

        if ($this->activeTab === 'aktif') {
            $query->where('status', 'disetujui');
        } elseif ($this->activeTab === 'ditolak') {
            $query->where('status', 'ditolak');
        } elseif ($this->activeTab === 'lunas') {
            $query->where('status', 'lunas');
        }

        $pinjaman = $query->paginate(10);

        return compact('pinjaman');
    }

    public function importExcel()
    {
        $this->validate([
            'importMonth' => 'required|numeric|min:1|max:12',
            'importYear' => 'required|numeric',
            'excelFile' => 'required|file|mimes:xlsx,xls,csv|max:5120'
        ]);

        try {
            $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
                public function array(array $array) {}
            }, $this->excelFile->getRealPath());
            
            if (empty($data) || empty($data[0])) {
                $this->addError('excelFile', 'File Excel kosong atau format tidak valid.');
                return;
            }
            
            $rows = $data[0];
            $successCount = 0;
            $skipCount = 0;
            
            $targetDate = Carbon::create($this->importYear, $this->importMonth, 1)->endOfMonth();

            foreach ($rows as $index => $row) {
                if ($index === 0) continue; // Skip header
                
                $nrp = $row[0] ?? null;
                $jumlahStr = $row[2] ?? 0;
                
                if (empty($nrp)) continue;
                
                $jumlahStr = preg_replace('/[^0-9]/', '', (string)$jumlahStr);
                $jumlahSetor = (float)$jumlahStr;
                
                if ($jumlahSetor <= 0) {
                    $skipCount++;
                    continue;
                }
                
                $query = Pinjaman::whereHas('user', function($q) use ($nrp) {
                    $q->where('nrp', $nrp);
                })->where('status', 'disetujui');
                
                if ($this->type === 'primer') {
                    $query->whereIn('jenis_permohonan', ['Handphone', 'Motor', 'Barang Lain']);
                } else {
                    $query->where(function ($q) {
                        $q->whereNotIn('jenis_permohonan', ['Handphone', 'Motor', 'Barang Lain'])
                          ->orWhereNull('jenis_permohonan');
                    });
                }
                
                $pinjaman = $query->first();
                
                if ($pinjaman) {
                    $angsuranKe = $pinjaman->angsurans()->where('status_pembayaran', 'lunas')->count() + 1;
                    
                    Angsuran::create([
                        'pinjaman_id' => $pinjaman->id,
                        'angsuran_ke' => $angsuranKe,
                        'jumlah_bayar' => $jumlahSetor,
                        'tanggal_bayar' => $targetDate->format('Y-m-d'),
                        'status_pembayaran' => 'lunas'
                    ]);
                    
                    if ($angsuranKe >= $pinjaman->tenor) {
                        $pinjaman->update(['status' => 'lunas']);
                    }
                    
                    $successCount++;
                } else {
                    $skipCount++;
                }
            }
            
            Flux::modal('import-excel')->close();
            $this->reset('excelFile');
            
            Flux::toast(
                text: "Berhasil memproses Excel. $successCount setoran berhasil, $skipCount dilewati.",
                heading: 'Import Sukses',
                variant: 'success'
            );
            
        } catch (\Exception $e) {
            $this->addError('excelFile', 'Terjadi kesalahan saat memproses file: ' . $e->getMessage());
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Daftar Pinjaman {{ $type === 'primer' ? 'Primer' : 'Baru & Kompensasi' }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Daftar semua permohonan pinjaman yang aktif, ditolak, maupun
                sudah lunas.</p>
        </div>
        
        <div>
            <flux:modal.trigger name="import-excel">
                <flux:button icon="document-arrow-up" variant="primary">Import Angsuran</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-px">
        <button wire:click="setTab('aktif')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors {{ $activeTab === 'aktif' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            Aktif (Disetujui)
        </button>
        <button wire:click="setTab('ditolak')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors {{ $activeTab === 'ditolak' ? 'border-rose-600 text-rose-600 dark:text-rose-400 dark:border-rose-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            Ditolak
        </button>
        <button wire:click="setTab('lunas')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors {{ $activeTab === 'lunas' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400 dark:border-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            Lunas
        </button>
    </div>

    {{-- List Table --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
                <thead class="bg-zinc-50/50 text-[11px] uppercase text-zinc-500 dark:bg-zinc-800/20 dark:text-zinc-400">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold">Pemohon</th>
                        <th class="px-5 py-3.5 font-semibold">Nominal / Jangka Waktu</th>
                        <th class="px-5 py-3.5 font-semibold">Rincian Angsuran</th>
                        @if($activeTab === 'ditolak')
                            <th class="px-5 py-3.5 font-semibold leading-tight">Alasan Penolakan <br><span
                                    class="text-[9px] font-normal text-zinc-400">Tgl Pengajuan & Ditolak</span></th>
                        @else
                            <th class="px-5 py-3.5 font-semibold leading-tight">Tanggal <br><span
                                    class="text-[9px] font-normal text-zinc-400">Pengajuan & Persetujuan</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/60">
                    @forelse($pinjaman as $item)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-bold text-zinc-900 dark:text-white">
                                    {{ $item->user?->name ?? 'User Dihapus' }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <p class="text-[11px] font-mono text-zinc-500">NRP: {{ $item->user?->nrp ?? '-' }}</p>
                                    @if($activeTab === 'lunas' && str_contains(strtolower($item->keterangan), 'kompensasi'))
                                        <span
                                            class="inline-flex rounded-full bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-900/20 dark:text-emerald-400 uppercase tracking-wider">Lunas
                                            (Kompensasi)</span>
                                    @endif
                                </div>
                                <div class="mt-1.5 flex gap-1.5">
                                    <span
                                        class="inline-flex rounded-md bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-700 dark:text-zinc-300">{{ $item->jenis_permohonan }}</span>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                @if($item->status === 'ditolak')
                                    <p class="font-semibold text-zinc-900 dark:text-zinc-200">Pengajuan: Rp
                                        {{ number_format($item->jumlah_ajuan ?? 0, 0, ',', '.') }}</p>
                                @else
                                    @if(str_contains(strtolower($item->keterangan), 'kompensasi') && $item->status === 'disetujui')
                                        <div class="mb-1">
                                            <span
                                                class="inline-flex rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 uppercase tracking-wider mb-0.5">Kompensasi</span>
                                            <p class="font-bold text-orange-600 dark:text-orange-400 text-xs">Pengajuan: Rp
                                                {{ number_format($item->jumlah_ajuan ?? 0, 0, ',', '.') }}</p>
                                        </div>
                                    @endif
                                    <p class="font-semibold text-zinc-900 dark:text-zinc-200">Diterima: Rp
                                        {{ number_format($item->jumlah_diterima ?? 0, 0, ',', '.') }}</p>
                                @endif
                                <p class="mt-0.5 text-xs">Selama: <span
                                        class="font-bold text-indigo-600 dark:text-indigo-400">{{ $item->tenor }}
                                        Bulan</span></p>
                            </td>

                            <td class="px-5 py-4">
                                @if($item->status === 'ditolak')
                                    <span class="text-xs text-zinc-400">-</span>
                                @else
                                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp
                                        {{ number_format($item->angsuran_perbulan ?? 0, 0, ',', '.') }}</p>
                                    <p class="mt-0.5 text-[10px] text-zinc-500">Per Bulan</p>
                                @endif
                            </td>

                            <td class="px-5 py-4 max-w-[250px]">
                                @if($activeTab === 'ditolak')
                                    <p class="text-xs text-rose-600 dark:text-rose-400 font-medium truncate mb-1"
                                        title="{{ $item->keterangan }}">{{ $item->keterangan ?? '-' }}</p>
                                    <div class="flex items-center gap-1.5 text-[9px] text-zinc-400">
                                        <span
                                            class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded">{{ $item->created_at?->format('d M Y') ?? '-' }}</span>
                                        <flux:icon name="arrow-right" class="size-2 opacity-50" />
                                        <span
                                            class="px-1 py-0.5 bg-rose-50 text-rose-600 dark:bg-rose-900/20 dark:text-rose-400 rounded">{{ $item->updated_at?->format('d M Y') ?? '-' }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center justify-between">
                                        <div class="flex flex-col gap-0.5 mr-3">
                                            <div class="flex items-center gap-1.5 text-[9px] text-zinc-500">
                                                <span class="uppercase font-bold tracking-widest text-[8px] w-8">Pgn</span>
                                                <span
                                                    class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded">{{ $item->created_at?->format('d M Y') ?? '-' }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-[9px] text-zinc-500">
                                                <span
                                                    class="uppercase font-bold tracking-widest text-[8px] w-8 text-indigo-600 dark:text-indigo-400">Acc</span>
                                                <span
                                                    class="px-1 py-0.5 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 rounded font-semibold">{{ $item->updated_at?->format('d M Y') ?? '-' }}</span>
                                            </div>
                                        </div>
                                        <a wire:navigate href="{{ route('pinjaman.show', $item->id) }}"
                                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-indigo-600 shadow-sm ring-1 ring-inset ring-zinc-200 hover:bg-zinc-50 dark:bg-zinc-800 dark:text-indigo-400 dark:ring-zinc-700/50 dark:hover:bg-zinc-700/80 transition-colors">
                                            Rincian
                                            <flux:icon name="chevron-right" class="size-2.5" />
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center">
                                <div class="flex flex-col items-center justify-center text-zinc-400 dark:text-zinc-500">
                                    <flux:icon name="folder-open" class="size-8 mb-3 opacity-20" />
                                    <p class="text-sm font-medium">Tidak ada data pinjaman {{ $activeTab }}.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pinjaman->hasPages())
            <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                {{ $pinjaman->links() }}
            </div>
        @endif
    </div>

    {{-- Import Excel Modal --}}
    <flux:modal name="import-excel" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Import Angsuran via Excel</flux:heading>
                <flux:subheading>Pastikan format Excel memiliki kolom NRP, Nama, dan Jumlah secara berurutan.</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="importMonth" label="Bulan" placeholder="Pilih Bulan">
                    @foreach(['1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April', '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus', '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'] as $key => $val)
                        <flux:select.option value="{{ $key }}">{{ $val }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model="importYear" label="Tahun" placeholder="Pilih Tahun">
                    @for($i = date('Y') + 1; $i >= date('Y') - 5; $i--)
                        <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
                    @endfor
                </flux:select>
            </div>

            <div x-data="{ uploading: false, progress: 0 }"
                 x-on:livewire-upload-start="uploading = true"
                 x-on:livewire-upload-finish="uploading = false"
                 x-on:livewire-upload-error="uploading = false"
                 x-on:livewire-upload-progress="progress = $event.detail.progress">
                 
                <flux:input type="file" wire:model="excelFile" label="File Excel" accept=".xlsx,.xls,.csv" />
                
                <div x-show="uploading" class="w-full bg-zinc-200 rounded-full h-1.5 mt-2 dark:bg-zinc-700" style="display: none;">
                    <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="importExcel" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="importExcel">Import Data</span>
                    <span wire:loading wire:target="importExcel">Memproses...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>