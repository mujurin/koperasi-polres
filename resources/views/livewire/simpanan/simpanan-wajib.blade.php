<?php

use App\Models\SimpananWajib;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public float $jumlah = 0;
    public int $bulan;
    public int $tahun;
    public string $keterangan = '';
    public bool $saved = false;

    public function mount(): void
    {
        $this->bulan = (int) now()->format('n');
        $this->tahun = (int) now()->format('Y');
    }

    public function simpan(): void
    {
        $userId = Auth::user()->id;

        // Cek manual apakah sudah ada untuk bulan & tahun ini
        $exists = SimpananWajib::where('user_id', $userId)
            ->where('bulan', $this->bulan)
            ->where('tahun', $this->tahun)
            ->exists();

        if ($exists) {
            $this->addError('bulan', 'Simpanan wajib untuk periode ini sudah dibayarkan.');
            return;
        }

        $this->validate([
            'jumlah' => 'required|numeric|min:1',
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000|max:2100',
            'keterangan' => 'nullable|string|max:255',
        ]);

        SimpananWajib::create([
            'user_id' => $userId,
            'jumlah' => $this->jumlah,
            'bulan' => $this->bulan,
            'tahun' => $this->tahun,
            'keterangan' => $this->keterangan,
        ]);

        $this->saved = true;
        // Reset form to default
        $this->jumlah = 0;
        $this->keterangan = '';

        // Majukan bulan untuk kemudahan input selanjutnya
        if ($this->bulan === 12) {
            $this->bulan = 1;
            $this->tahun++;
        } else {
            $this->bulan++;
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-2xl mx-auto">

    {{-- ===== HEADER BACK + TITLE ===== --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('simpanan.index') }}" wire:navigate
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 shadow-sm transition-all dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white">
            <flux:icon name="arrow-left" class="size-4" />
        </a>
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Setor Simpanan Wajib</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Penyetoran rutin bulanan anggota</p>
        </div>
    </div>

    {{-- ===== INFO BANNER ===== --}}
    <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-5 shadow-md">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                <flux:icon name="calendar-days" class="size-6 text-white" />
            </div>
            <div>
                <p class="font-semibold text-white">Setoran Rutin Bulanan</p>
                <p class="text-xs text-emerald-100 mt-0.5">Hanya dapat menyetor 1 kali per periode bulan/tahun</p>
            </div>
        </div>
    </div>

    {{-- ===== SUCCESS NOTIFICATION ===== --}}
    @if($saved)
        <div x-data="{ show: true }" x-show="show" x-transition
            class="relative rounded-2xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/60 p-4 pr-12 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20">
                    <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">Setoran berhasil disimpan!</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">Data simpanan wajib telah dicatat.</p>
                </div>
            </div>
            <button @click="show = false"
                class="absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded-lg text-emerald-500 hover:bg-emerald-100 hover:text-emerald-700 transition-colors dark:hover:bg-emerald-900">
                <flux:icon name="x-mark" class="size-4" />
            </button>
        </div>
    @endif

    {{-- ===== FORM CARD ===== --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
        <div class="border-b border-zinc-100 dark:border-zinc-800 px-6 py-4 bg-zinc-50/50 dark:bg-zinc-800/30">
            <h2 class="font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon name="pencil-square" class="size-4 text-zinc-500" />
                Form Setoran Simpanan Wajib
            </h2>
        </div>
        <div class="p-6">
            <form wire:submit="simpan" class="flex flex-col gap-5">

                {{-- Periode Bulan & Tahun --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:select wire:model="bulan" label="Periode Bulan" required>
                            @for($i = 1; $i <= 12; $i++)
                                <flux:select.option value="{{ $i }}">
                                    {{ \App\Models\SimpananWajib::namaBulan($i) }}
                                </flux:select.option>
                            @endfor
                        </flux:select>
                    </div>
                    <flux:input wire:model="tahun" label="Tahun" type="number" min="2000" max="2100" required />
                </div>

                {{-- Jumlah --}}
                <flux:input wire:model="jumlah" label="Jumlah Setoran (Rp)" type="number" min="1"
                    placeholder="Contoh: 100000" required />

                {{-- Keterangan --}}
                <flux:input wire:model="keterangan" label="Keterangan (opsional)"
                    placeholder="Contoh: Setoran potong gaji" />

                {{-- Action buttons --}}
                <div class="flex items-center justify-between pt-1 border-t border-zinc-100 dark:border-zinc-800 mt-1">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5">
                        <flux:icon name="information-circle" class="size-3.5" />
                        Satu setoran per bulan
                    </p>
                    <flux:button type="submit" variant="primary" icon="plus-circle">
                        Simpan Setoran
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</div>