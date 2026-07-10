<?php

use App\Models\User;
use App\Models\SimpananPokok;
use App\Models\SimpananWajib;
use App\Models\Penarikan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public User $member;

    // Simpanan Pokok
    public float $pokokJumlah = 0;
    public string $pokokTanggal = '';
    public string $pokokKeterangan = '';
    public bool $pokokSaved = false;

    // Simpanan Wajib
    public float $wajibJumlah = 0;
    public int $wajibBulan;
    public int $wajibTahun;
    public string $wajibKeterangan = '';
    public bool $wajibSaved = false;

    // Penarikan
    public float $tarikJumlah = 0;
    public string $tarikTanggal = '';
    public string $tarikKeterangan = '';
    public bool $tarikSaved = false;

    public function mount(User $user): void
    {
        $this->member = $user;
        $this->pokokTanggal = now()->format('Y-m-d');
        $this->tarikTanggal = now()->format('Y-m-d');
        $this->wajibBulan = (int) now()->format('n');
        $this->wajibTahun = (int) now()->format('Y');
    }

    // ── HELPERS ───────────────────────────────────────────────
    public function getExistingPokokProperty(): ?SimpananPokok
    {
        return $this->member->simpananPokok;
    }

    public function getRiwayatWajibProperty()
    {
        return $this->member->simpananWajib()
            ->orderByDesc('tahun')->orderByDesc('bulan')->get();
    }

    public function getRiwayatTarikProperty()
    {
        return $this->member->penarikan()->orderByDesc('tanggal')->get();
    }

    public function getSaldoProperty(): float
    {
        return $this->member->saldoAkhir();
    }

    // ── SIMPAN POKOK ──────────────────────────────────────────
    public function simpanPokok(): void
    {
        if ($this->existingPokok) {
            $this->addError('pokokJumlah', 'Simpanan pokok sudah pernah dibayarkan.');
            return;
        }
        $this->validate([
            'pokokJumlah' => 'required|numeric|min:1',
            'pokokTanggal' => 'required|date',
            'pokokKeterangan' => 'nullable|string|max:255',
        ]);
        SimpananPokok::create([
            'user_id' => $this->member->id,
            'jumlah' => $this->pokokJumlah,
            'tanggal' => $this->pokokTanggal,
            'keterangan' => $this->pokokKeterangan,
        ]);
        $this->member->refresh();
        $this->pokokSaved = true;
    }

    // ── SIMPAN WAJIB ──────────────────────────────────────────
    public function simpanWajib(): void
    {
        $exists = SimpananWajib::where('user_id', $this->member->id)
            ->where('bulan', $this->wajibBulan)
            ->where('tahun', $this->wajibTahun)
            ->exists();
        if ($exists) {
            $this->addError('wajibBulan', 'Simpanan wajib periode ini sudah ada.');
            return;
        }
        $this->validate([
            'wajibJumlah' => 'required|numeric|min:1',
            'wajibBulan' => 'required|integer|min:1|max:12',
            'wajibTahun' => 'required|integer|min:2000|max:2100',
            'wajibKeterangan' => 'nullable|string|max:255',
        ]);
        SimpananWajib::create([
            'user_id' => $this->member->id,
            'jumlah' => $this->wajibJumlah,
            'bulan' => $this->wajibBulan,
            'tahun' => $this->wajibTahun,
            'keterangan' => $this->wajibKeterangan,
        ]);
        $this->member->refresh();
        $this->wajibSaved = true;
        $this->wajibJumlah = 0;
        $this->wajibKeterangan = '';
        if ($this->wajibBulan === 12) {
            $this->wajibBulan = 1;
            $this->wajibTahun++;
        } else {
            $this->wajibBulan++;
        }
    }

    // ── SIMPAN PENARIKAN ──────────────────────────────────────
    public function simpanTarik(): void
    {
        if ($this->tarikJumlah > $this->saldo) {
            $this->addError('tarikJumlah', 'Jumlah melebihi saldo akhir (Rp ' . number_format($this->saldo, 0, ',', '.') . ').');
            return;
        }
        $this->validate([
            'tarikJumlah' => 'required|numeric|min:1',
            'tarikTanggal' => 'required|date',
            'tarikKeterangan' => 'nullable|string|max:255',
        ]);
        Penarikan::create([
            'user_id' => $this->member->id,
            'jumlah' => $this->tarikJumlah,
            'tanggal' => $this->tarikTanggal,
            'keterangan' => $this->tarikKeterangan,
        ]);
        $this->member->refresh();
        $this->tarikSaved = true;
        $this->tarikJumlah = 0;
        $this->tarikKeterangan = '';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

    {{-- ═══════════════════════════════════════════════════════
    HEADER
    ════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('simpanan.index') }}" wire:navigate
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50 shadow-sm transition-all dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white">
            <flux:icon name="arrow-left" class="size-4" />
        </a>
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Detail Simpanan Anggota</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Kelola simpanan atas nama anggota berikut</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    MEMBER INFO + SALDO CARDS
    ════════════════════════════════════════════════════════════ --}}
    <div class="grid gap-4 lg:grid-cols-5">

        {{-- Member card --}}
        <div
            class="lg:col-span-2 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-700 p-5 shadow-md">
            <div class="flex items-center gap-4">
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm text-2xl font-bold text-white">
                    {{ strtoupper(substr($member->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-lg font-bold text-white leading-tight">{{ $member->name }}</p>
                    <p class="text-sm text-indigo-200 font-mono">NRP: {{ $member->nrp ?? '—' }}</p>
                    @if($member->email)
                        <p class="text-xs text-indigo-300 mt-0.5">{{ $member->email }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Mini stat cards --}}
        <div class="lg:col-span-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4">
            @php
                $totalPokok = $member->simpananPokok?->jumlah ?? 0;
                $totalWajib = $member->simpananWajib->sum('jumlah');
                $totalTarik = $member->totalPenarikan();
            @endphp

            <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 dark:border-blue-800/40 dark:bg-blue-950/40">
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-1 flex items-center gap-1">
                    <flux:icon name="building-library" class="size-3" /> Pokok
                </p>
                <p class="font-bold text-blue-900 dark:text-blue-100 text-sm">Rp
                    {{ number_format($totalPokok, 0, ',', '.') }}
                </p>
            </div>
            <div
                class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 dark:border-emerald-800/40 dark:bg-emerald-950/40">
                <p class="text-xs text-emerald-600 dark:text-emerald-400 mb-1 flex items-center gap-1">
                    <flux:icon name="calendar-days" class="size-3" /> Wajib
                </p>
                <p class="font-bold text-emerald-900 dark:text-emerald-100 text-sm">Rp
                    {{ number_format($totalWajib, 0, ',', '.') }}
                </p>
            </div>
            <div class="rounded-xl border border-rose-100 bg-rose-50 p-3 dark:border-rose-800/40 dark:bg-rose-950/40">
                <p class="text-xs text-rose-600 dark:text-rose-400 mb-1 flex items-center gap-1">
                    <flux:icon name="arrow-up-tray" class="size-3" /> Tarik
                </p>
                <p class="font-bold text-rose-900 dark:text-rose-100 text-sm">Rp
                    {{ number_format($totalTarik, 0, ',', '.') }}
                </p>
            </div>
            <div
                class="rounded-xl border border-violet-100 bg-violet-50 p-3 dark:border-violet-800/40 dark:bg-violet-950/40">
                <p class="text-xs text-violet-600 dark:text-violet-400 mb-1 flex items-center gap-1">
                    <flux:icon name="wallet" class="size-3" /> Saldo
                </p>
                <p class="font-bold text-violet-900 dark:text-violet-100 text-sm">Rp
                    {{ number_format($this->saldo, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    SECTION 1 — SIMPANAN POKOK
    ════════════════════════════════════════════════════════════ --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
        <div
            class="flex items-center gap-2.5 border-b border-zinc-100 dark:border-zinc-800 px-5 py-4 bg-blue-50/40 dark:bg-blue-950/20">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500">
                <flux:icon name="building-library" class="size-4 text-white" />
            </div>
            <div>
                <h2 class="font-semibold text-zinc-900 dark:text-white text-sm">Simpanan Pokok</h2>
                <p class="text-xs text-zinc-400">Iuran pokok keanggotaan — sekali bayar</p>
            </div>
            @if($this->existingPokok)
                <span
                    class="ml-auto inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                    <flux:icon name="check-circle" class="size-3.5" /> Sudah Dibayar
                </span>
            @else
                <span
                    class="ml-auto inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/40 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                    <flux:icon name="clock" class="size-3.5" /> Belum Dibayar
                </span>
            @endif
        </div>

        <div class="p-5">
            @if($pokokSaved)
                <div
                    class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/50 p-4 flex items-center gap-3">
                    <flux:icon name="check-circle" class="size-5 text-emerald-500 shrink-0" />
                    <p class="text-sm text-emerald-800 dark:text-emerald-200 font-medium">Simpanan pokok berhasil disimpan!
                    </p>
                </div>
            @elseif($this->existingPokok)
                <div class="grid sm:grid-cols-3 gap-3">
                    <div
                        class="rounded-xl border border-blue-100 dark:border-blue-800/40 bg-blue-50 dark:bg-blue-950/30 p-3">
                        <p class="text-xs text-blue-600 dark:text-blue-400 mb-1">Jumlah</p>
                        <p class="font-bold text-blue-900 dark:text-blue-100">Rp
                            {{ number_format($this->existingPokok->jumlah, 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 p-3">
                        <p class="text-xs text-zinc-500 mb-1">Tanggal</p>
                        <p class="font-semibold text-zinc-800 dark:text-zinc-200 text-sm">
                            {{ $this->existingPokok->tanggal->format('d M Y') }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 p-3">
                        <p class="text-xs text-zinc-500 mb-1">Keterangan</p>
                        <p class="font-medium text-zinc-700 dark:text-zinc-300 text-sm">
                            {{ $this->existingPokok->keterangan ?: '—' }}
                        </p>
                    </div>
                </div>
            @else
                <form wire:submit="simpanPokok" class="grid sm:grid-cols-3 gap-4">
                    <flux:input wire:model="pokokJumlah" label="Jumlah (Rp)" type="number" min="1" placeholder="500000"
                        required />
                    <flux:input wire:model="pokokTanggal" label="Tanggal Bayar" type="date" required />
                    <flux:input wire:model="pokokKeterangan" label="Keterangan (opsional)" placeholder="Catatan..." />
                    <div class="sm:col-span-3 flex justify-end">
                        <flux:button type="submit" variant="primary" icon="building-library">Simpan Pokok</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    SECTION 2 — SIMPANAN WAJIB
    ════════════════════════════════════════════════════════════ --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
        <div
            class="flex items-center gap-2.5 border-b border-zinc-100 dark:border-zinc-800 px-5 py-4 bg-emerald-50/40 dark:bg-emerald-950/20">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500">
                <flux:icon name="calendar-days" class="size-4 text-white" />
            </div>
            <div>
                <h2 class="font-semibold text-zinc-900 dark:text-white text-sm">Simpanan Wajib</h2>
                <p class="text-xs text-zinc-400">{{ $this->riwayatWajib->count() }} setoran tercatat</p>
            </div>
        </div>

        <div class="p-5 flex flex-col gap-4">

            {{-- Notif success --}}
            @if($wajibSaved)
                <div x-data="{ show: true }" x-show="show" x-transition
                    class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/50 p-3 flex items-center gap-3 pr-10 relative">
                    <flux:icon name="check-circle" class="size-4 text-emerald-500 shrink-0" />
                    <p class="text-sm text-emerald-700 dark:text-emerald-300">Setoran wajib berhasil disimpan.</p>
                    <button @click="show=false" class="absolute right-3 text-emerald-400 hover:text-emerald-600">
                        <flux:icon name="x-mark" class="size-4" />
                    </button>
                </div>
            @endif

            {{-- Form tambah --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 p-4">
                <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3">Tambah Setoran Baru</p>
                <form wire:submit="simpanWajib" class="grid sm:grid-cols-4 gap-3">
                    <flux:select wire:model="wajibBulan" label="Bulan" required>
                        @for($i = 1; $i <= 12; $i++)
                            <flux:select.option value="{{ $i }}">{{ \App\Models\SimpananWajib::namaBulan($i) }}
                            </flux:select.option>
                        @endfor
                    </flux:select>
                    <flux:input wire:model="wajibTahun" label="Tahun" type="number" min="2000" max="2100" required />
                    <flux:input wire:model="wajibJumlah" label="Jumlah (Rp)" type="number" min="1" placeholder="100000"
                        required />
                    <flux:input wire:model="wajibKeterangan" label="Keterangan" placeholder="Opsional" />
                    <div class="sm:col-span-4 flex justify-end">
                        <flux:button type="submit" variant="primary" icon="plus-circle">Simpan Setoran</flux:button>
                    </div>
                </form>
            </div>

            {{-- Riwayat table --}}
            @if($this->riwayatWajib->isEmpty())
                <div class="py-8 text-center text-zinc-400">
                    <flux:icon name="inbox" class="mx-auto mb-2 size-8" />
                    <p class="text-sm">Belum ada simpanan wajib</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/60">
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                    Periode</th>
                                <th
                                    class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                    Jumlah</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                    Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($this->riwayatWajib as $item)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                                                <span
                                                    class="text-[8px] font-bold text-emerald-700">{{ str_pad($item->bulan, 2, '0', STR_PAD_LEFT) }}</span>
                                            </span>
                                            <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                                {{ \App\Models\SimpananWajib::namaBulan($item->bulan) }} {{ $item->tahun }}
                                            </span>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-right text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                        Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2.5 text-xs text-zinc-500">{{ $item->keterangan ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/30 border-t-2 border-zinc-200 dark:border-zinc-700">
                                <td class="px-4 py-2.5 text-xs font-semibold text-zinc-500">Total</td>
                                <td class="px-4 py-2.5 text-right text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                    Rp {{ number_format($this->riwayatWajib->sum('jumlah'), 0, ',', '.') }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    SECTION 3 — PENARIKAN
    ════════════════════════════════════════════════════════════ --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">
        <div
            class="flex items-center gap-2.5 border-b border-zinc-100 dark:border-zinc-800 px-5 py-4 bg-rose-50/40 dark:bg-rose-950/20">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-500">
                <flux:icon name="arrow-up-tray" class="size-4 text-white" />
            </div>
            <div>
                <h2 class="font-semibold text-zinc-900 dark:text-white text-sm">Riwayat Penarikan</h2>
                <p class="text-xs text-zinc-400">{{ $this->riwayatTarik->count() }} transaksi</p>
            </div>
            {{-- Saldo badge --}}
            <div
                class="ml-auto flex items-center gap-1.5 rounded-xl border border-violet-200 dark:border-violet-800/40 bg-violet-50 dark:bg-violet-950/30 px-3 py-1">
                <flux:icon name="wallet" class="size-3.5 text-violet-600 dark:text-violet-400" />
                <span class="text-xs font-semibold text-violet-700 dark:text-violet-300">Saldo: Rp
                    {{ number_format($this->saldo, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="p-5 flex flex-col gap-4">

            @if($tarikSaved)
                <div x-data="{ show: true }" x-show="show" x-transition
                    class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/50 p-3 flex items-center gap-3 pr-10 relative">
                    <flux:icon name="check-circle" class="size-4 text-emerald-500 shrink-0" />
                    <p class="text-sm text-emerald-700 dark:text-emerald-300">Penarikan berhasil diproses.</p>
                    <button @click="show=false" class="absolute right-3 text-emerald-400 hover:text-emerald-600">
                        <flux:icon name="x-mark" class="size-4" />
                    </button>
                </div>
            @endif

            @if($this->saldo > 0)
                {{-- Form penarikan --}}
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 p-4">
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3">Tambah Penarikan</p>
                    <form wire:submit="simpanTarik" class="grid sm:grid-cols-3 gap-3">
                        <flux:input wire:model="tarikJumlah" label="Jumlah (Rp)" type="number" min="1"
                            max="{{ $this->saldo }}" placeholder="Contoh: 50000" required />
                        <flux:input wire:model="tarikTanggal" label="Tanggal" type="date" required />
                        <flux:input wire:model="tarikKeterangan" label="Tujuan" placeholder="Opsional" />
                        <div class="sm:col-span-3 flex items-center justify-between">
                            <p class="text-xs text-zinc-500">Maks: <span class="font-semibold text-violet-600">Rp
                                    {{ number_format($this->saldo, 0, ',', '.') }}</span></p>
                            <flux:button type="submit" variant="danger" icon="arrow-up-tray">Proses Penarikan</flux:button>
                        </div>
                    </form>
                </div>
            @else
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/30 p-3 flex items-center gap-2.5">
                    <flux:icon name="information-circle" class="size-4 text-amber-600 shrink-0" />
                    <p class="text-xs text-amber-700 dark:text-amber-300">Saldo anggota kosong. Tidak dapat melakukan
                        penarikan.</p>
                </div>
            @endif

            {{-- Tabel riwayat --}}
            @if($this->riwayatTarik->isEmpty())
                <div class="py-8 text-center text-zinc-400">
                    <flux:icon name="inbox" class="mx-auto mb-2 size-8" />
                    <p class="text-sm">Belum ada riwayat penarikan</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/60">
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                    Tanggal</th>
                                <th
                                    class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                    Aksi</th>
                                <th
                                    class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                    Jumlah</th>
                                <th
                                    class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                    Tujuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($this->riwayatTarik as $item)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-5 w-5 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/40">
                                                <flux:icon name="arrow-up" class="size-2.5 text-rose-600" />
                                            </div>
                                            <span
                                                class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $item->tanggal->format('d M Y') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if($item->status === 'disetujui')
                                            <a href="{{ route('penarikan.kwitansi', $item->id) }}" target="_blank"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1 text-[10px] font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50 transition-colors">
                                                <flux:icon name="printer" class="size-3" />
                                                Cetak
                                            </a>
                                        @else
                                            <span class="text-[10px] text-zinc-400">Belum disetujui</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-xs font-semibold text-rose-600 dark:text-rose-400">Rp
                                        {{ number_format($item->jumlah, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-zinc-500">{{ $item->keterangan ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/30 border-t-2 border-zinc-200 dark:border-zinc-700">
                                <td class="px-4 py-2.5 text-xs font-semibold text-zinc-500">Total Ditarik (Disetujui)</td>
                                <td class="px-4 py-2.5 text-right text-sm font-bold text-rose-700 dark:text-rose-300">Rp
                                    {{ number_format($member->totalPenarikan(), 0, ',', '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>