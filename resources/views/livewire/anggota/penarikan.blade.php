<?php

use App\Models\Penarikan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.anggota')] class extends Component {

    public float $jumlah = 0;
    public string $keterangan = '';
    public bool $saved = false;

    public function mount(): void
    {
    }

    public function getSaldoSekarangProperty(): float
    {
        return Auth::user()->saldoAkhir();
    }

    public function getRiwayatPenarikanProperty()
    {
        return Auth::user()->penarikan()->latest('created_at')->get();
    }

    public function getAdaPenarikanProsesProperty(): bool
    {
        return Auth::user()->penarikan()->where('status', 'proses')->exists();
    }

    public function simpan(): void
    {
        if ($this->adaPenarikanProses) {
            $this->addError('jumlah', 'Anda masih memiliki penarikan dalam proses.');
            return;
        }

        $saldoAkhir = $this->saldoSekarang;

        $this->validate([
            'jumlah' => 'required|numeric|min:1',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($this->jumlah > $saldoAkhir) {
            $this->addError('jumlah', 'Jumlah penarikan tidak boleh melebihi saldo akhir Anda (Rp ' . number_format($saldoAkhir, 0, ',', '.') . ').');
            return;
        }

        Penarikan::create([
            'user_id' => Auth::user()->id,
            'jumlah' => $this->jumlah,
            'tanggal' => now(), // default today
            'keterangan' => $this->keterangan,
            'status' => 'proses', // default proses
        ]);

        $this->saved = true;
        $this->jumlah = 0;
        $this->keterangan = '';
    }
}; ?>

<div class="flex flex-col gap-4 p-4 pb-24">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('anggota.dashboard') }}" wire:navigate
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 shadow-sm transition-all dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white leading-tight">Tarik Dana</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Ajukan permohonan penarikan saldo</p>
        </div>
    </div>

    {{-- Saldo Card --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-600 p-5 shadow-md mt-2">
        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10"></div>
        <p class="text-xs text-blue-200 mb-1">Saldo Tersedia (Bisa Ditarik)</p>
        <p class="text-3xl font-bold text-white">Rp {{ number_format($this->saldoSekarang, 0, ',', '.') }}</p>
    </div>

    {{-- SUCCESS NOTIFICATION --}}
    @if($saved)
        <div x-data="{ show: true }" x-show="show" x-transition
            class="relative rounded-2xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/60 p-4 shadow-sm mt-2">
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/20">
                    <svg class="size-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">Permohonan berhasil dikirim!</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">Pengajuan penarikan dana Anda sedang diproses oleh admin. Silakan tunggu persetujuan.</p>
                </div>
            </div>
            <button @click="show = false"
                class="absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded-lg text-emerald-500 hover:bg-emerald-100 dark:hover:bg-emerald-900 transition-colors">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Form Request --}}
    @if($this->adaPenarikanProses)
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-8 text-center shadow-sm dark:border-blue-800 dark:bg-blue-950/60 mt-2">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/50">
                <svg class="size-7 text-blue-500 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">Sedang Diproses</h3>
            <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">Anda tidak bisa mengajukan penarikan baru karena masih ada pengajuan yang sedang diproses admin.</p>
        </div>
    @elseif($this->saldoSekarang <= 0)
        <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900 mt-2">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                <svg class="size-7 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-zinc-900 dark:text-white">Saldo Kosong</h3>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Anda belum memiliki saldo untuk ditarik.</p>
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden mt-2">
            <div class="p-5">
                <form wire:submit="simpan" class="flex flex-col gap-5">
                    
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah Penarikan (Rp)</label>
                        <input type="text" inputmode="numeric" required
                            x-data="{ 
                                init() {
                                    this.$el.value = $wire.jumlah ? parseInt($wire.jumlah).toLocaleString('id-ID') : '';
                                },
                                format(e) {
                                    let num = e.target.value.replace(/[^0-9]/g, '');
                                    if(num !== '') {
                                        let parsed = parseInt(num, 10);
                                        if (parsed > {{ $this->saldoSekarang }}) parsed = {{ $this->saldoSekarang }};
                                        e.target.value = parsed.toLocaleString('id-ID');
                                        $wire.jumlah = parsed;
                                    } else {
                                        e.target.value = '';
                                        $wire.jumlah = 0;
                                    }
                                }
                            }"
                            @input="format"
                            class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm font-semibold tracking-wider text-indigo-700 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-indigo-300 focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                            placeholder="Contoh: 5.000.000">
                        @error('jumlah') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tujuan Penarikan (opsional)</label>
                        <input wire:model="keterangan" type="text"
                            class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                            placeholder="Contoh: Beli susu anak">
                        @error('keterangan') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all active:scale-[0.98]">
                        Ajukan Penarikan
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Riwayat Penarikan Saya --}}
    <div class="mt-4">
        <h3 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-3">Status Pengajuan Penarikan</h3>
        
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            @if($this->riwayatPenarikan->isEmpty())
                <div class="p-6 text-center">
                    <p class="text-xs text-zinc-400">Belum ada riwayat pengajuan penarikan.</p>
                </div>
            @else
                @foreach($this->riwayatPenarikan as $item)
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full 
                                @if($item->status === 'disetujui') bg-emerald-100 dark:bg-emerald-900/40
                                @elseif($item->status === 'ditolak') bg-rose-100 dark:bg-rose-900/40
                                @else bg-orange-100 dark:bg-orange-900/40 @endif">
                                
                                @if($item->status === 'disetujui')
                                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @elseif($item->status === 'ditolak')
                                    <svg class="size-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                @else
                                    <svg class="size-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</p>
                                <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $item->created_at->format('d M Y, H:i') }}</p>
                                @if($item->keterangan)
                                    <p class="text-[10px] text-zinc-400 mt-0.5 max-w-[150px] truncate" title="{{ $item->keterangan }}">{{ $item->keterangan }}</p>
                                @endif
                            </div>
                        </div>
                        <div>
                            @if($item->status === 'disetujui')
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400">Selesai</span>
                            @elseif($item->status === 'ditolak')
                                <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600 border border-rose-200 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-400">Ditolak</span>
                            @else
                                <span class="inline-flex rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-semibold text-orange-600 border border-orange-200 dark:border-orange-800/50 dark:bg-orange-950/40 dark:text-orange-400">Diproses</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
