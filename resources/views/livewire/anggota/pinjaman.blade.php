<?php

use App\Models\Pinjaman;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.anggota')] class extends Component {

    public string $jumlah_ajuan = '';
    public string $tenor = '';
    public string $keterangan = '';
    public string $jenis_permohonan = 'Biasa';
    public bool $saved = false;

    // Simulation properties
    public float $biaya_administrasi = 0;
    public float $jumlah_diterima = 0;
    public float $angsuran_perbulan = 0;

    public function mount()
    {
        $this->hitungSimulasi();
    }

    public function updated($property)
    {
        if (in_array($property, ['jumlah_ajuan', 'tenor'])) {
            $this->hitungSimulasi();
        }
    }

    public function hitungSimulasi(): void
    {
        $jumlah = (float) ($this->jumlah_ajuan ?: 0);
        $tenorBulan = (int) $this->tenor;

        if ($jumlah > 0 && $tenorBulan > 0) {
            // Jasa 1% dari total ajuan
            $this->biaya_administrasi = $jumlah * 0.01;
            
            // Diterima = Ajuan - Biaya Administrasi
            $this->jumlah_diterima = $jumlah - $this->biaya_administrasi;
            
            // Angsuran = (Ajuan / Tenor) + Biaya Administrasi (fixed per bulan, as per spec: "ditambah jasa 1% dari jumlah pengajuan")
            // Wait, "ditambah jasa 1% dari jumlah pengajuan" means 1% is added EVERY month? Or just once?
            // "dibagi tenor dan ditambah jasa 1% dari jumah pengajuan" -> (jumlah / tenor) + (1% * jumlah). Yes, added to monthly angsuran!
            $pokokAngsuran = $jumlah / $tenorBulan;
            $jasaBulan = $jumlah * 0.01; // 1% per bulan? Wait, the spec says "ditambah jasa 1% dari jumlah pengajuan"
            $this->angsuran_perbulan = $pokokAngsuran + $jasaBulan;
        } else {
            $this->biaya_administrasi = 0;
            $this->jumlah_diterima = 0;
            $this->angsuran_perbulan = 0;
        }
    }

    public function getRiwayatPinjamanProperty()
    {
        return Auth::user()->pinjaman()->latest('created_at')->get();
    }

    public function simpan(): void
    {
        $this->validate([
            'jumlah_ajuan' => 'required|numeric|min:500000',
            'tenor' => 'required|integer|min:1|max:120',
            'jenis_permohonan' => 'required|in:Biasa,Urgent',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $this->hitungSimulasi();

        Pinjaman::create([
            'user_id' => Auth::user()->id,
            'jumlah_ajuan' => $this->jumlah_ajuan,
            'tenor' => $this->tenor,
            'jasa_persen' => 1.00,
            'jumlah_diterima' => $this->jumlah_diterima,
            'angsuran_perbulan' => $this->angsuran_perbulan,
            'status' => 'proses',
            'jenis_permohonan' => $this->jenis_permohonan,
            'keterangan' => $this->keterangan,
        ]);

        $this->saved = true;
        // Reset form
        $this->jumlah_ajuan = '';
        $this->keterangan = '';
        $this->hitungSimulasi();
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
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white leading-tight">Pinjaman</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Pengajuan Pinjaman Koperasi</p>
        </div>
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
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">Pengajuan berhasil!</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">Sistem menjadwalkan permohonan pinjaman Anda untuk di-review oleh admin.</p>
                </div>
            </div>
            <button @click="show = false"
                class="absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded-lg text-emerald-500 hover:bg-emerald-100 dark:hover:bg-emerald-900 transition-colors">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Form Request & Simulasi --}}
    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 mt-2">
        <div class="p-5 flex flex-col gap-6">
            
            <form wire:submit="simpan" class="flex flex-col gap-5">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah Pengajuan Pinjaman (Rp)</label>
                    <input wire:model.live.debounce.500ms="jumlah_ajuan" type="number" min="500000" step="100000"
                        class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                        placeholder="Contoh: 10000000">
                    <p class="text-[10px] text-zinc-500 mt-1">Minimal pengajuan Rp 500.000</p>
                    @error('jumlah_ajuan') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tenor (Bulan)</label>
                        <input wire:model.live.debounce.500ms="tenor" type="number" min="1" max="120"
                            class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                            placeholder="Contoh: 12">
                        @error('tenor') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jenis Permohonan</label>
                        <select wire:model="jenis_permohonan" class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors">
                            <option value="Biasa">Biasa</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                        @error('jenis_permohonan') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Keterangan / Tujuan Pinjaman</label>
                    <input wire:model="keterangan" type="text"
                        class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                        placeholder="Contoh: Renovasi rumah">
                    @error('keterangan') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Simulasi Card --}}
                <div class="mt-2 rounded-xl bg-indigo-50/50 border border-indigo-100 p-4 dark:bg-indigo-950/20 dark:border-indigo-900/50">
                    <h3 class="text-xs font-bold text-indigo-800 dark:text-indigo-300 mb-3 flex items-center gap-1.5">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Simulasi Pinjaman
                    </h3>
                    
                    <div class="flex flex-col gap-2.5">
                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Total Pengajuan</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp {{ number_format((float)($jumlah_ajuan ?: 0), 0, ',', '.') }}</span>
                        </div>
                        
                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <div class="flex items-center gap-1">
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Potongan Administrasi (1%)</span>
                            </div>
                            <span class="text-sm font-semibold text-rose-600 dark:text-rose-400">- Rp {{ number_format($biaya_administrasi, 0, ',', '.') }}</span>
                        </div>

                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-emerald-100 dark:border-emerald-900/30">
                            <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-400">Jumlah Bersih Diterima</span>
                            <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($jumlah_diterima, 0, ',', '.') }}</span>
                        </div>

                        <div class="mt-2 text-center p-3 rounded-lg bg-indigo-600 text-white shadow-sm ring-1 ring-indigo-500/50">
                            <span class="block text-[10px] text-indigo-200 mb-1">Setoran Angsuran Per Bulan (Pokok + Jasa 1%)</span>
                            <span class="text-xl font-bold tracking-tight">Rp {{ number_format($angsuran_perbulan, 0, ',', '.') }}</span>
                            <span class="text-[10px] text-indigo-200 ml-1">/ bulan</span>
                        </div>
                    </div>
                </div>

                <button type="submit" @if(!(float)$jumlah_ajuan) disabled @endif
                    class="w-full mt-2 rounded-xl bg-zinc-900 dark:bg-white px-4 py-3.5 text-sm font-semibold text-white dark:text-zinc-900 shadow-xl hover:bg-zinc-800 dark:hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed">
                    Kirim Permohonan
                </button>
            </form>
        </div>
    </div>

    {{-- Riwayat Pinjaman --}}
    <div class="mt-4">
        <h3 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-3">Riwayat Pengajuan</h3>
        
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            @if($this->riwayatPinjaman->isEmpty())
                <div class="p-6 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-50 dark:bg-zinc-800/50">
                        <svg class="size-5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-xs text-zinc-400">Belum ada riwayat ajuan pinjaman.</p>
                </div>
            @else
                @foreach($this->riwayatPinjaman as $item)
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg 
                                @if($item->status === 'disetujui') bg-emerald-100 dark:bg-emerald-900/40
                                @elseif($item->status === 'ditolak') bg-rose-100 dark:bg-rose-900/40
                                @else bg-orange-100 dark:bg-orange-900/40 @endif">
                                
                                @if($item->status === 'disetujui')
                                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
                                @elseif($item->status === 'ditolak')
                                    <svg class="size-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                @else
                                    <svg class="size-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($item->jumlah_ajuan, 0, ',', '.') }}</p>
                                <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $item->tenor }} bln • {{ $item->jenis_permohonan }} • {{ $item->created_at->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            @if($item->status === 'disetujui')
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 border border-emerald-200 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-400">Disetujui</span>
                            @elseif($item->status === 'ditolak')
                                <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600 border border-rose-200 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-400">Ditolak</span>
                            @else
                                <span class="inline-flex rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-semibold text-orange-600 border border-orange-200 dark:border-orange-800/50 dark:bg-orange-950/40 dark:text-orange-400">Proses</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
