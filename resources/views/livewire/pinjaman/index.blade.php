<?php

use App\Models\Pinjaman;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    
    public $activeTab = 'aktif';

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function with(): array
    {
        $query = Pinjaman::with('user')->orderBy('updated_at', 'desc');

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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Daftar Pinjaman</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Daftar semua permohonan pinjaman yang aktif, ditolak, maupun sudah lunas.</p>
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
    <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
                <thead class="bg-zinc-50/50 text-[11px] uppercase text-zinc-500 dark:bg-zinc-800/20 dark:text-zinc-400">
                    <tr>
                        <th class="px-5 py-3.5 font-semibold">Pemohon</th>
                        <th class="px-5 py-3.5 font-semibold">Nominal / Jangka Waktu</th>
                        <th class="px-5 py-3.5 font-semibold">Rincian Angsuran</th>
                        @if($activeTab === 'ditolak')
                            <th class="px-5 py-3.5 font-semibold">Alasan Penolakan</th>
                        @else
                            <th class="px-5 py-3.5 font-semibold">Tanggal Disetujui</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/60">
                    @forelse($pinjaman as $item)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="px-5 py-4">
                                <p class="font-bold text-zinc-900 dark:text-white">{{ $item->user?->name ?? 'User Dihapus' }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <p class="text-[11px] font-mono text-zinc-500">NRP: {{ $item->user?->nrp ?? '-' }}</p>
                                    @if($activeTab === 'lunas' && str_contains(strtolower($item->keterangan), 'kompensasi'))
                                        <span class="inline-flex rounded-full bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-900/20 dark:text-emerald-400 uppercase tracking-wider">Lunas (Kompensasi)</span>
                                    @endif
                                </div>
                                <div class="mt-1.5 flex gap-1.5">
                                    <span class="inline-flex rounded-md bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-700 dark:text-zinc-300">{{ $item->jenis_permohonan }}</span>
                                </div>
                            </td>
                            
                            <td class="px-5 py-4">
                                @if($item->status === 'ditolak')
                                    <p class="font-semibold text-zinc-900 dark:text-zinc-200">Pengajuan: Rp {{ number_format($item->jumlah_ajuan ?? 0, 0, ',', '.') }}</p>
                                @else
                                    @if(str_contains(strtolower($item->keterangan), 'kompensasi') && $item->status === 'disetujui')
                                        <div class="mb-1">
                                            <span class="inline-flex rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 uppercase tracking-wider mb-0.5">Kompensasi</span>
                                            <p class="font-bold text-orange-600 dark:text-orange-400 text-xs">Pengajuan: Rp {{ number_format($item->jumlah_ajuan ?? 0, 0, ',', '.') }}</p>
                                        </div>
                                    @endif
                                    <p class="font-semibold text-zinc-900 dark:text-zinc-200">Diterima: Rp {{ number_format($item->jumlah_diterima ?? 0, 0, ',', '.') }}</p>
                                @endif
                                <p class="mt-0.5 text-xs">Selama: <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $item->tenor }} Bulan</span></p>
                            </td>

                            <td class="px-5 py-4">
                                @if($item->status === 'ditolak')
                                    <span class="text-xs text-zinc-400">-</span>
                                @else
                                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($item->angsuran_perbulan ?? 0, 0, ',', '.') }}</p>
                                    <p class="mt-0.5 text-[10px] text-zinc-500">Per Bulan</p>
                                @endif
                            </td>

                            <td class="px-5 py-4 max-w-[250px]">
                                @if($activeTab === 'ditolak')
                                    <p class="text-xs text-rose-600 dark:text-rose-400 font-medium truncate" title="{{ $item->keterangan }}">{{ $item->keterangan ?? '-' }}</p>
                                    <p class="mt-0.5 text-[10px] text-zinc-400">{{ $item->updated_at?->format('d M Y') ?? '-' }}</p>
                                @else
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $item->updated_at?->format('d M Y') ?? '-' }}</p>
                                        <a wire:navigate href="{{ route('pinjaman.show', $item->id) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-indigo-600 shadow-sm ring-1 ring-inset ring-zinc-200 hover:bg-zinc-50 dark:bg-zinc-800 dark:text-indigo-400 dark:ring-zinc-700/50 dark:hover:bg-zinc-700/80 transition-colors">
                                            Rincian Cicilan <flux:icon name="chevron-right" class="size-3" />
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
</div>
