<?php

use App\Models\SimpananPokok;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public ?SimpananPokok $existing = null;
    public float $jumlah = 0;
    public string $tanggal = '';
    public string $keterangan = '';
    public bool $saved = false;

    public function mount(): void
    {
        $this->existing = Auth::user()->simpananPokok;
        $this->tanggal = now()->format('Y-m-d');
    }

    public function simpan(): void
    {
        if ($this->existing) {
            $this->addError('jumlah', 'Simpanan pokok sudah pernah dibayarkan.');
            return;
        }

        $this->validate([
            'jumlah' => 'required|numeric|min:1',
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string|max:255',
        ]);

        SimpananPokok::create([
            'user_id' => Auth::user()->id,
            'jumlah' => $this->jumlah,
            'tanggal' => $this->tanggal,
            'keterangan' => $this->keterangan,
        ]);

        $this->existing = Auth::user()->simpananPokok()->first();
        $this->saved = true;
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
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Simpanan Pokok</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Iuran pokok keanggotaan koperasi (sekali bayar)</p>
        </div>
    </div>

    @if($saved)
        {{-- ===== SUCCESS STATE ===== --}}
        <div class="rounded-2xl overflow-hidden border border-emerald-200 dark:border-emerald-800 shadow-sm">
            <div class="bg-gradient-to-br from-emerald-500 to-teal-500 p-6 text-center">
                <div
                    class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm">
                    <flux:icon name="check-circle" class="size-8 text-white" />
                </div>
                <h2 class="text-lg font-bold text-white">Simpanan Pokok Berhasil Disimpan!</h2>
                <p class="mt-1 text-emerald-100 text-sm">Data Anda telah tercatat dalam sistem koperasi</p>
            </div>
            <div class="bg-white dark:bg-zinc-900 p-5 text-center">
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    Rp {{ number_format($existing->jumlah, 0, ',', '.') }}
                </p>
                <p class="text-sm text-zinc-500 mt-1">Dibayar pada {{ $existing->tanggal->format('d M Y') }}</p>
                <a href="{{ route('simpanan.index') }}" wire:navigate
                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600 transition-colors">
                    <flux:icon name="arrow-left" class="size-4" />
                    Kembali ke Rekap Simpanan
                </a>
            </div>
        </div>

    @elseif($existing)
        {{-- ===== SUDAH TERDAFTAR ===== --}}
        <div class="rounded-2xl overflow-hidden border border-blue-200 dark:border-blue-800 shadow-sm">
            {{-- Header gradient --}}
            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-6">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                        <flux:icon name="check-badge" class="size-8 text-white" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-white">Simpanan Pokok Terdaftar</p>
                        <p class="text-sm text-blue-100">Simpanan pokok hanya dilakukan sekali</p>
                    </div>
                </div>
            </div>
            {{-- Detail --}}
            <div class="bg-white dark:bg-zinc-900 p-5">
                <div class="grid grid-cols-2 gap-4">
                    <div
                        class="rounded-xl border border-blue-100 dark:border-blue-900/50 bg-blue-50 dark:bg-blue-950/40 p-4">
                        <p class="text-xs font-medium text-blue-600 dark:text-blue-400 mb-1">
                            <flux:icon name="banknotes" class="size-3.5 inline mr-1" />
                            Jumlah Disetor
                        </p>
                        <p class="text-xl font-bold text-blue-900 dark:text-blue-100">
                            Rp {{ number_format($existing->jumlah, 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">
                            <flux:icon name="calendar" class="size-3.5 inline mr-1" />
                            Tanggal Bayar
                        </p>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $existing->tanggal->format('d M Y') }}
                        </p>
                    </div>
                </div>
                @if($existing->keterangan)
                    <div class="mt-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-3">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-0.5">Keterangan</p>
                        <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $existing->keterangan }}</p>
                    </div>
                @endif
                <div class="mt-4">
                    <a href="{{ route('simpanan.index') }}" wire:navigate
                        class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">
                        <flux:icon name="arrow-left" class="size-4" />
                        Kembali ke Rekap Simpanan
                    </a>
                </div>
            </div>
        </div>

    @else
        {{-- ===== FORM INPUT ===== --}}
        {{-- Info banner --}}
        <div class="rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 p-5 shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                    <flux:icon name="building-library" class="size-6 text-white" />
                </div>
                <div>
                    <p class="font-semibold text-white">Pendaftaran Simpanan Pokok</p>
                    <p class="text-xs text-blue-100 mt-0.5">Iuran wajib sekali bayar untuk menjadi anggota koperasi</p>
                </div>
            </div>
        </div>

        <div
            class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
            <div class="border-b border-zinc-100 dark:border-zinc-800 px-6 py-4 bg-zinc-50/50 dark:bg-zinc-800/30">
                <h2 class="font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                    <flux:icon name="pencil-square" class="size-4 text-zinc-500" />
                    Form Input Simpanan Pokok
                </h2>
            </div>
            <div class="p-6">
                <form wire:submit="simpan" class="flex flex-col gap-5">
                    <flux:input wire:model="jumlah" label="Jumlah Simpanan Pokok (Rp)" type="number" min="1"
                        placeholder="Contoh: 500000" required />

                    <flux:input wire:model="tanggal" label="Tanggal Pembayaran" type="date" required />

                    <flux:input wire:model="keterangan" label="Keterangan (opsional)" placeholder="Catatan tambahan..." />

                    {{-- Warning notice --}}
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/40 p-4 flex items-start gap-3">
                        <flux:icon name="information-circle"
                            class="size-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Perhatian</p>
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                                Simpanan pokok hanya dapat diinput <strong>sekali</strong> dan tidak dapat diubah setelah
                                disimpan.
                            </p>
                        </div>
                    </div>

                    <flux:button type="submit" variant="primary" icon="building-library" class="w-full">
                        Simpan Simpanan Pokok
                    </flux:button>
                </form>
            </div>
        </div>
    @endif
</div>