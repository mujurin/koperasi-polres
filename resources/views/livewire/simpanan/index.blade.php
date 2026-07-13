<?php

use App\Models\SimpananPokok;
use App\Models\SimpananWajib;
use App\Models\Penarikan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $user = Auth::user();

        // ── Rekap CURRENT USER ──────────────────────────────────
        $totalPokok = $user->simpananPokok?->jumlah ?? 0;
        $totalWajib = $user->simpananWajib()->sum('jumlah');
        $totalTarik = $user->penarikan()->sum('jumlah');
        $saldoAkhir = ($totalPokok + $totalWajib) - $totalTarik;

        $riwayatWajib = $user->simpananWajib()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();

        $riwayatTarik = $user->penarikan()
            ->orderByDesc('tanggal')
            ->get();

        // ── Akumulasi SEMUA ANGGOTA ──────────────────────────────
        $globalPokok = SimpananPokok::sum('jumlah');
        $globalWajib = SimpananWajib::sum('jumlah');
        $globalTarik = Penarikan::where('status', 'disetujui')->sum('jumlah');
        $globalSaldo = ($globalPokok + $globalWajib) - $globalTarik;

        // ── Daftar Anggota ───────────────────────────────────────
        $query = User::with(['simpananPokok', 'simpananWajib', 'penarikan'])
            ->where(function ($q) {
                $q->whereHas('simpananPokok')
                    ->orWhereHas('simpananWajib')
                    ->orWhereHas('penarikan');
            });

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('nrp', 'like', '%' . $this->search . '%');
            });
        }

        $anggotaPaginator = $query->orderBy('name')->paginate(15);

        return compact(
            'totalPokok',
            'totalWajib',
            'totalTarik',
            'saldoAkhir',
            'riwayatWajib',
            'riwayatTarik',
            'globalPokok',
            'globalWajib',
            'globalTarik',
            'globalSaldo',
            'anggotaPaginator'
        );
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">

    <livewire:admin.sync-simpanan />

    {{-- ═══════════════════════════════════════════════════════
    AKUMULASI SEMUA ANGGOTA
    ════════════════════════════════════════════════════════════ --}}
    <div>
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Akumulasi
            Seluruh Anggota</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

            <div
                class="flex items-center gap-3 rounded-2xl border border-blue-100 bg-blue-50/60 px-4 py-3 dark:border-blue-800/40 dark:bg-blue-950/40">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500 text-white">
                    <flux:icon name="building-library" class="size-4" />
                </div>
                <div>
                    <p class="text-xs text-blue-600 dark:text-blue-400">Total Pokok</p>
                    <p class="font-bold text-blue-900 dark:text-blue-100 text-sm">Rp
                        {{ number_format($globalPokok, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            <div
                class="flex items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 dark:border-emerald-800/40 dark:bg-emerald-950/40">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white">
                    <flux:icon name="calendar-days" class="size-4" />
                </div>
                <div>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">Total Wajib</p>
                    <p class="font-bold text-emerald-900 dark:text-emerald-100 text-sm">Rp
                        {{ number_format($globalWajib, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            <div
                class="flex items-center gap-3 rounded-2xl border border-rose-100 bg-rose-50/60 px-4 py-3 dark:border-rose-800/40 dark:bg-rose-950/40">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-500 text-white">
                    <flux:icon name="arrow-up-tray" class="size-4" />
                </div>
                <div>
                    <p class="text-xs text-rose-600 dark:text-rose-400">Total Penarikan</p>
                    <p class="font-bold text-rose-900 dark:text-rose-100 text-sm">Rp
                        {{ number_format($globalTarik, 0, ',', '.') }}
                    </p>
                </div>
            </div>

            <div
                class="flex items-center gap-3 rounded-2xl border border-violet-100 bg-violet-50/60 px-4 py-3 dark:border-violet-800/40 dark:bg-violet-950/40">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500 text-white">
                    <flux:icon name="wallet" class="size-4" />
                </div>
                <div>
                    <p class="text-xs text-violet-600 dark:text-violet-400">Total Saldo</p>
                    <p class="font-bold text-violet-900 dark:text-violet-100 text-sm">Rp
                        {{ number_format($globalSaldo, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
    DAFTAR ANGGOTA SIMPANAN
    ════════════════════════════════════════════════════════════ --}}
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">

        {{-- Card header --}}
        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 px-5 py-3">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/50">
                    <flux:icon name="users" class="size-4 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-white text-sm">Daftar Anggota</h2>
                    <p class="text-[10px] sm:text-xs text-zinc-400">{{ $anggotaPaginator->total() }} anggota terdaftar
                    </p>
                </div>
            </div>

            <div class="w-40 sm:w-56 shrink-0">
                <flux:input wire:model.live.debounce.300ms="search" size="sm" placeholder="Cari NRP / NAMA"
                    icon="magnifying-glass" clearable />
            </div>
        </div>

        @if($anggotaPaginator->isEmpty())
            <div class="py-16 text-center">
                <div
                    class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="users" class="size-7 text-zinc-400" />
                </div>
                <p class="font-medium text-zinc-600 dark:text-zinc-400">Belum ada anggota dengan simpanan</p>
                <p class="mt-1 text-xs text-zinc-400">Anggota yang telah menyimpan akan muncul di sini</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-zinc-50 dark:bg-zinc-800/60">
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                No</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Anggota</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Simpanan Pokok</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Simpanan Wajib</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Penarikan</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Saldo Akhir</th>
                            <th
                                class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($anggotaPaginator as $i => $u)
                            @php
                                $pokok = $u->simpananPokok?->jumlah ?? 0;
                                $wajib = $u->simpananWajib->sum('jumlah');
                                $tarik = $u->totalPenarikan();
                                $saldo = $u->saldoAkhir();
                            @endphp
                            <tr class="hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40 transition-colors">
                                <td class="px-5 py-3 text-zinc-400 text-xs">{{ $anggotaPaginator->firstItem() + $i }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/40">
                                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300">
                                                {{ strtoupper(substr($u->name, 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-zinc-800 dark:text-zinc-200 text-sm">{{ $u->name }}
                                            </p>
                                            <p class="text-xs text-zinc-400 font-mono">{{ $u->nrp ?? '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if($pokok > 0)
                                        <span class="font-semibold text-blue-600 dark:text-blue-400 text-xs">Rp
                                            {{ number_format($pokok, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-zinc-400 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400 text-xs">Rp
                                        {{ number_format($wajib, 0, ',', '.') }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if($tarik > 0)
                                        <span class="font-semibold text-rose-600 dark:text-rose-400 text-xs">Rp
                                            {{ number_format($tarik, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-zinc-400 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span
                                        class="font-bold text-xs {{ $saldo >= 0 ? 'text-violet-700 dark:text-violet-300' : 'text-red-600' }}">
                                        Rp {{ number_format($saldo, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <a href="{{ route('simpanan.anggota.detail', $u->id) }}" wire:navigate
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors dark:border-indigo-800/50 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/40">
                                        <flux:icon name="eye" class="size-3.5" />
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($anggotaPaginator->hasPages())
                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    {{ $anggotaPaginator->links() }}
                </div>
            @endif
        @endif
    </div>

</div>