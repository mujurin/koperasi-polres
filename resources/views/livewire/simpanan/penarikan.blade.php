<?php

use App\Models\Penarikan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'proses'; // proses, disetujui, ditolak, all

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function setujui(int $id): void
    {
        $penarikan = Penarikan::with('user')->findOrFail($id);
        if ($penarikan->status === 'proses') {
            // Cek saldo user mencukupi
            $saldo = $penarikan->user->saldoAkhir();
            if ($saldo >= $penarikan->jumlah) {
                $penarikan->update(['status' => 'disetujui']);
            } else {
                $this->addError("error_$id", "Saldo anggota tidak mencukupi untuk disetujui.");
            }
        }
    }

    public function tolak(int $id): void
    {
        $penarikan = Penarikan::findOrFail($id);
        if ($penarikan->status === 'proses') {
            $penarikan->update(['status' => 'ditolak']);
        }
    }

    public function with(): array
    {
        $query = Penarikan::with('user')
            ->when($this->search, function ($q) {
                $q->whereHas('user', function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('nrp', 'like', '%' . $this->search . '%');
                });
            });

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $penarikanList = $query->orderBy('created_at', 'desc')->paginate(15);

        return [
            'penarikanList' => $penarikanList
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Persetujuan Penarikan Dana</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Kelola dan setujui permintaan penarikan dana dari
                anggota</p>
        </div>
        <flux:button :href="route('simpanan.index')" variant="ghost" icon="arrow-left" wire:navigate>
            Kembali ke Simpanan
        </flux:button>
    </div>

    {{-- Filter & Search --}}
    <div class="flex flex-col sm:flex-row gap-4 mb-2">
        <div class="flex-1 max-w-md relative">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Cari nama atau NRP..." />
        </div>
        <div class="flex gap-2">
            @foreach(['proses' => 'Baru (Proses)', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'all' => 'Semua'] as $val => $label)
                    <button wire:click="$set('statusFilter', '{{ $val }}')"
                        class="px-4 py-2 text-sm font-medium rounded-xl transition-all border
                                {{ $statusFilter === $val
                ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:border-zinc-100'
                : 'bg-white text-zinc-600 border-zinc-200 hover:bg-zinc-50 dark:bg-zinc-900 dark:text-zinc-400 dark:border-zinc-800 dark:hover:bg-zinc-800/50' }}">
                        {{ $label }}
                    </button>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
                <thead
                    class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Tgl Pengajuan</th>
                        <th class="px-6 py-4 font-semibold">Anggota</th>
                        <th class="px-6 py-4 font-semibold">Jumlah</th>
                        <th class="px-6 py-4 font-semibold">Keterangan</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($penarikanList as $item)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/25 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ $item->created_at->format('d M Y') }}
                                <div class="text-[10px] text-zinc-400 mt-0.5">{{ $item->created_at->format('H:i') }} WIB
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $item->user->name }}</div>
                                <div class="text-xs mt-0.5 text-zinc-500">{{ $item->user->nrp }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-semibold text-zinc-900 dark:text-white">Rp
                                    {{ number_format($item->jumlah, 0, ',', '.') }}</span>
                                @error("error_{$item->id}") <div
                                    class="text-[10px] text-rose-500 mt-1 max-w-[150px] whitespace-normal">{{ $message }}
                                </div> @enderror
                            </td>
                            <td class="px-6 py-4 max-w-xs truncate" title="{{ $item->keterangan ?? '-' }}">
                                {{ $item->keterangan ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($item->status === 'disetujui')
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400">
                                        <flux:icon name="check-circle" class="size-3.5" /> Setuju
                                    </span>
                                @elseif($item->status === 'ditolak')
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600 border border-rose-200 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-400">
                                        <flux:icon name="x-circle" class="size-3.5" /> Tolak
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-600 border border-orange-200 dark:border-orange-800/50 dark:bg-orange-950/40 dark:text-orange-400">
                                        <flux:icon name="clock" class="size-3.5" /> Proses
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                @if($item->status === 'proses')
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="setujui({{ $item->id }})" title="Setujui"
                                            class="inline-flex h-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 px-3 text-xs font-medium hover:bg-emerald-100 hover:text-emerald-700 transition-colors border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50">
                                            Setujui
                                        </button>
                                        <button wire:click="tolak({{ $item->id }})" title="Tolak"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 hover:text-rose-700 transition-colors border border-rose-200 dark:border-rose-800/50 dark:bg-rose-900/30 dark:text-rose-400 dark:hover:bg-rose-900/50">
                                            <flux:icon name="x-mark" class="size-4" />
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm">
                                <div class="flex flex-col items-center justify-center text-zinc-400">
                                    <flux:icon name="inbox" class="size-10 mb-3 opacity-20" />
                                    <p>Tidak ada data penarikan untuk filter ini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($penarikanList->hasPages())
            <div class="border-t border-zinc-100 dark:border-zinc-800 p-4">
                {{ $penarikanList->links(data: ['scrollTo' => false]) }}
            </div>
        @endif
    </div>
</div>