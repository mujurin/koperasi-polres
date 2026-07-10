<?php

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public $bulan;
    public $tahun;
    public $dataTable = [];
    public $isLoading = false;

    public function mount()
    {
        $this->bulan = (int) date('n');
        $this->tahun = (int) date('Y');
    }

    public function tarikData()
    {
        $this->isLoading = true;

        try {
            $response = Http::get('https://siapklu.com/api/tarik-setoran', [
                'bulan' => $this->bulan,
                'tahun' => $this->tahun,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $items = [];
                if (isset($json['data']['items']) && is_array($json['data']['items'])) {
                    $items = $json['data']['items'];
                } else if (isset($json['data']) && is_array($json['data'])) {
                    $items = isset($json['data'][0]) ? $json['data'] : [];
                }

                // Cross-check with local DB to pre-fill 'Disetor' state
                foreach ($items as &$item) {
                    $nrp = $item['nrp'] ?? $item['nip'] ?? null;
                    $item['status'] = 'Belum';

                    if ($nrp) {
                        $user = \App\Models\User::where('nrp', $nrp)->first();
                        if ($user) {
                            $pinjaman = $user->pinjaman()->where('status', 'disetujui')->first();
                            if ($pinjaman) {
                                $sudahAda = \App\Models\Angsuran::where('pinjaman_id', $pinjaman->id)
                                    ->whereYear('tanggal_bayar', $this->tahun)
                                    ->whereMonth('tanggal_bayar', $this->bulan)
                                    ->exists();

                                if ($sudahAda) {
                                    $item['status'] = 'Disetor';
                                }
                            }
                        }
                    }
                }
                unset($item);

                $this->dataTable = $items;
            } else {
                $this->dataTable = [];
            }
        } catch (\Exception $e) {
            $this->dataTable = [];
            // Handle error
        }

        $this->isLoading = false;
    }

    public function inputSetoran()
    {
        if (empty($this->dataTable))
            return;

        // Set the transfer date exactly using standard payroll date, usually 5th of the month
        // or ensure it safely anchors to the target month.
        $tanggalRekam = \Carbon\Carbon::create($this->tahun, $this->bulan, 5)->format('Y-m-d');

        foreach ($this->dataTable as &$row) {
            if (($row['status'] ?? '') === 'Disetor')
                continue;

            $nrp = $row['nrp'] ?? $row['nip'] ?? null;
            if (!$nrp)
                continue;

            $user = \App\Models\User::where('nrp', $nrp)->first();
            if (!$user)
                continue;

            $pinjaman = $user->pinjaman()->where('status', 'disetujui')->first();
            if ($pinjaman) {
                $sudahAda = \App\Models\Angsuran::where('pinjaman_id', $pinjaman->id)
                    ->whereYear('tanggal_bayar', $this->tahun)
                    ->whereMonth('tanggal_bayar', $this->bulan)
                    ->exists();

                if (!$sudahAda) {
                    $angsuranKe = $pinjaman->angsurans()->count() + 1;

                    \App\Models\Angsuran::create([
                        'pinjaman_id' => $pinjaman->id,
                        'angsuran_ke' => $angsuranKe,
                        'jumlah_bayar' => $row['jumlah'] ?? 0,
                        'tanggal_bayar' => $tanggalRekam,
                        'status_pembayaran' => 'Lunas'
                    ]);

                    if ($angsuranKe >= $pinjaman->tenor) {
                        $pinjaman->update(['status' => 'lunas']);
                    }
                }

                $row['status'] = 'Disetor';
            }
        }
        unset($row);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div
        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden">

        <div class="border-b border-zinc-100 dark:border-zinc-800 px-5 py-4">
            <h2 class="font-bold text-zinc-900 dark:text-white uppercase tracking-wide text-sm">TARIK DATA SETORAN</h2>
        </div>

        <div class="p-5 flex flex-col md:flex-row items-end gap-4 border-b border-zinc-100 dark:border-zinc-800">
            <div class="w-full md:w-64">
                <label class="block text-xs text-zinc-500 mb-1.5 dark:text-zinc-400">Bulan</label>
                <select wire:model="bulan"
                    class="w-full rounded-lg border-zinc-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 sm:text-sm">
                    @foreach(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $m)
                        <option value="{{ $index + 1 }}">{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full md:w-64">
                <label class="block text-xs text-zinc-500 mb-1.5 dark:text-zinc-400">Tahun</label>
                <select wire:model="tahun"
                    class="w-full rounded-lg border-zinc-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 sm:text-sm">
                    @for($y = date('Y') - 5; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div class="flex gap-3 mt-4 md:mt-0">
                <button wire:click="tarikData" type="button" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#007bff] hover:bg-blue-600 px-4 py-2 text-sm font-semibold text-black shadow-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 w-full sm:w-auto h-10 disabled:opacity-50">
                    <span wire:loading.remove wire:target="tarikData">
                        <flux:icon name="arrow-down-tray" class="size-4" />
                    </span>
                    <span wire:loading wire:target="tarikData">
                        <flux:icon name="arrow-path" class="size-4 animate-spin" />
                    </span>
                    Tarik Data
                </button>

                @if(count($dataTable) > 0)
                    <button wire:click="inputSetoran" type="button" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#28a745] hover:bg-green-600 px-4 py-2 text-sm font-semibold text-black shadow-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 w-full sm:w-auto h-10 disabled:opacity-50">
                        <span wire:loading.remove wire:target="inputSetoran">
                            <flux:icon name="check" class="size-4" />
                        </span>
                        <span wire:loading wire:target="inputSetoran">
                            <flux:icon name="arrow-path" class="size-4 animate-spin" />
                        </span>
                        Input Setoran
                    </button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto min-h-[300px]">
            <table class="w-full text-sm text-left border-b border-zinc-200 dark:border-zinc-800">
                <thead
                    class="bg-white text-zinc-900 border-b border-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider w-16">No</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider">Nama Pegawai</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider">NRP/NIP</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider">Jenis Potongan</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider">Jumlah</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider w-24">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-700 dark:text-zinc-300">
                    @forelse($dataTable as $index => $row)
                        @php
                            $isDisetor = ($row['status'] ?? '') === 'Disetor';
                            $rowBg = $isDisetor
                                ? 'hover:bg-[#c3e6cb] dark:bg-emerald-900/10 dark:hover:bg-emerald-900/20 text-[#155724] dark:text-emerald-200'
                                : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50';
                        @endphp
                        <tr class="{{ $rowBg }} transition-colors" @if($isDisetor) style="background-color: #d1e7dd;"
                        @endif>
                            <td class="px-5 py-3">{{ $index + 1 }}</td>
                            <td class="px-5 py-3 uppercase">
                                {{ $row['anggota']['nmpeg'] ?? $row['nmpeg'] ?? $row['nama_pegawai'] ?? $row['nama'] ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-blue-600 dark:text-blue-400 font-semibold">
                                {{ $row['nrp'] ?? $row['nip'] ?? '-' }}
                            </td>
                            <td class="px-5 py-3">{{ $row['jenis_pot'] ?? $row['jenis_potongan'] ?? '-' }}</td>
                            <td class="px-5 py-3">Rp {{ number_format($row['jumlah'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right">
                                @if($isDisetor)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md bg-[#28a745] px-2 py-1 text-[11px] font-bold text-black shadow-sm ring-1 ring-inset ring-emerald-500/20 tracking-wide uppercase">
                                        <flux:icon name="check-circle" class="size-3" variant="solid" /> Disetor
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center rounded-md bg-slate-300 px-2.5 py-1 text-[11px] font-bold text-black ring-1 ring-inset ring-slate-400/20 shadow-sm tracking-wide uppercase">
                                        Belum
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center">
                                    <flux:icon name="document-magnifying-glass"
                                        class="size-10 mb-3 text-zinc-300 dark:text-zinc-600" />
                                    <p>Tarik data untuk menampilkan setoran</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>