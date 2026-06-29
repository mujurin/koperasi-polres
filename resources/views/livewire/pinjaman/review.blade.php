<?php

use App\Models\Pinjaman;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Pinjaman $pinjaman;

    public $formJumlahAjuan = 0;
    public $formTenor = 0;
    public $selectedUser = '';
    public $selectedJenis = '';

    public $simulasiDiterima = 0;
    public $simulasiAngsuran = 0;
    public $simulasiBiaya = 0;
    public $simulasiPokok = 0;
    public $simulasiJasa = 0;
    public $sisaPinjaman = 0;
    public $pinaltiKompensasi = 0;
    public $isKompensasi = false;

    public $riwayatGaji = [];

    public $showFormTolak = false;
    public $alasanPenolakan = '';

    public function mount(Pinjaman $pinjaman)
    {
        // Fail if not in proses
        if ($pinjaman->status !== 'proses') {
            return redirect()->route('pinjaman.antrian');
        }

        $this->pinjaman = $pinjaman->load('user');

        $this->formJumlahAjuan = $this->pinjaman->jumlah_ajuan;
        $this->formTenor = $this->pinjaman->tenor;
        $this->selectedUser = $this->pinjaman->user?->name ?? 'User Dihapus';
        $this->sisaPinjaman = 0;
        $pinjamanAktif = $this->pinjaman->user?->pinjaman()
            ->where('status', 'disetujui')
            ->where('id', '!=', $this->pinjaman->id)
            ->latest()
            ->first();

        if ($pinjamanAktif) {
            $totalTerbayar = \App\Models\Angsuran::where('pinjaman_id', $pinjamanAktif->id)
                ->where('status_pembayaran', 'Lunas')
                ->sum('jumlah_bayar');

            $totalKewajiban = $pinjamanAktif->angsuran_perbulan * $pinjamanAktif->tenor;

            $this->sisaPinjaman = max(0, $totalKewajiban - $totalTerbayar);
            $this->pinaltiKompensasi = $pinjamanAktif->jumlah_ajuan * 0.01;
        }

        $this->hitungSimulasi();

        $this->riwayatGaji = [];
        $nrp = $this->pinjaman->user?->nrp;
        if ($nrp) {
            try {
                $res = Http::timeout(10)->post('https://siapklu.com/api/gaji_tunkin_3bulan', ['nrp' => $nrp]);
                if ($res->successful()) {
                    $json = $res->json();
                    if (($json['status'] ?? false) && isset($json['data'])) {
                        $this->riwayatGaji = $json['data'];
                    }
                }
            } catch (\Exception $e) {
            }
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['formJumlahAjuan', 'formTenor'])) {
            $this->hitungSimulasi();
        }
    }

    public function hitungSimulasi()
    {
        $jumlah = (float) ($this->formJumlahAjuan ?: 0);
        $tenor = (int) ($this->formTenor ?: 0);
        $sisaLama = (float) $this->sisaPinjaman;

        if ($jumlah > 0 && $tenor > 0) {
            if ($sisaLama > 0) {
                $this->isKompensasi = true;
                $nilaiKompensasi = $jumlah - $sisaLama - $this->pinaltiKompensasi;

                if ($nilaiKompensasi <= 0) {
                    $this->simulasiBiaya = 0;
                    $this->simulasiDiterima = 0;
                    $this->simulasiAngsuran = 0;
                    $this->simulasiPokok = 0;
                    $this->simulasiJasa = 0;
                } else {
                    $this->simulasiBiaya = $jumlah * 0.01;
                    $this->simulasiDiterima = $nilaiKompensasi - $this->simulasiBiaya;
                    $this->simulasiPokok = $jumlah / $tenor;
                    $this->simulasiJasa = $jumlah * 0.01;
                    $this->simulasiAngsuran = $this->simulasiPokok + $this->simulasiJasa;
                }
            } else {
                $this->isKompensasi = false;
                $this->simulasiBiaya = $jumlah * 0.01;
                $this->simulasiDiterima = $jumlah - $this->simulasiBiaya;
                $this->simulasiPokok = $jumlah / $tenor;
                $this->simulasiJasa = $jumlah * 0.01;
                $this->simulasiAngsuran = $this->simulasiPokok + $this->simulasiJasa;
            }
        } else {
            $this->simulasiBiaya = 0;
            $this->simulasiDiterima = 0;
            $this->simulasiAngsuran = 0;
            $this->simulasiPokok = 0;
            $this->simulasiJasa = 0;
        }
    }

    public function simpanReview()
    {
        $this->validate([
            'formJumlahAjuan' => 'required|numeric|min:1',
            'formTenor' => 'required|integer|min:1|max:120',
        ]);

        if ($this->isKompensasi && $this->formJumlahAjuan <= ($this->sisaPinjaman + $this->pinaltiKompensasi)) {
            $this->addError('formJumlahAjuan', 'Untuk Kompensasi, ajuan harus lebih besar dari sisa hutang & pinalti (Rp ' . number_format($this->sisaPinjaman + $this->pinaltiKompensasi, 0, ',', '.') . ').');
            return;
        }

        $this->hitungSimulasi();

        $keteranganBaru = $this->pinjaman->keterangan;
        if ($this->isKompensasi && !str_contains(strtolower($keteranganBaru), 'kompensasi')) {
            $keteranganBaru = '[Kompensasi] ' . $keteranganBaru;
        }

        $this->pinjaman->update([
            'jumlah_ajuan' => $this->formJumlahAjuan,
            'tenor' => $this->formTenor,
            'jumlah_diterima' => $this->simulasiDiterima,
            'angsuran_perbulan' => $this->simulasiAngsuran,
            'status' => 'disetujui',
            'keterangan' => $keteranganBaru
        ]);

        if ($this->isKompensasi) {
            $pinjamanAktif = $this->pinjaman->user?->pinjaman()
                ->where('status', 'disetujui')
                ->where('id', '!=', $this->pinjaman->id)
                ->latest()
                ->first();

            if ($pinjamanAktif && $this->sisaPinjaman > 0) {
                // Insert a closure angsuran to mathematically settle the old Pinjaman
                \App\Models\Angsuran::create([
                    'pinjaman_id' => $pinjamanAktif->id,
                    'angsuran_ke' => 999, // Special identifier for Kompensasi early payoff
                    'jumlah_bayar' => $this->sisaPinjaman + $this->pinaltiKompensasi,
                    'tanggal_bayar' => now(),
                    'status_pembayaran' => 'Lunas'
                ]);

                $pinjamanAktif->update([
                    'status' => 'lunas',
                    'keterangan' => $pinjamanAktif->keterangan . ' (Lunas via Kompensasi Pinjaman Baru)'
                ]);
            }
        }

        return redirect()->route('pinjaman.antrian');
    }

    public function konfirmasiTolak()
    {
        $this->showFormTolak = true;
    }

    public function batalTolak()
    {
        $this->showFormTolak = false;
        $this->alasanPenolakan = '';
        $this->resetValidation('alasanPenolakan');
    }

    public function tolakReview()
    {
        $this->validate([
            'alasanPenolakan' => 'required|string|min:5|max:225',
        ], [
            'alasanPenolakan.required' => 'Alasan penolakan wajib diisi.',
            'alasanPenolakan.min' => 'Alasan penolakan minimal 5 karakter.',
            'alasanPenolakan.max' => 'Alasan penolakan maksimal 225 karakter.',
        ]);

        $this->pinjaman->update([
            'status' => 'ditolak',
            'keterangan' => 'Ditolak karena: ' . $this->alasanPenolakan
        ]);
        return redirect()->route('pinjaman.antrian');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a wire:navigate href="{{ route('pinjaman.antrian') }}"
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:hover:text-white transition-colors">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Review Pengajuan Pinjaman</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Verifikasi, sesuaikan, dan setujui permohonan anggota.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Kiri: Informasi Pemohon --}}
        <div>
            <div
                class="mb-5 rounded-2xl bg-white shadow-sm border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 overflow-hidden">
                <div
                    class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800/60 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Informasi Pemohon</h4>
                    <span
                        class="inline-flex rounded-md bg-indigo-100 dark:bg-indigo-800 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300">{{ $selectedJenis }}</span>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-4 mb-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 font-bold text-xl dark:bg-zinc-800">
                            {{ strtoupper(substr($selectedUser, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-bold text-zinc-900 dark:text-white text-lg leading-tight">{{ $selectedUser }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 font-mono mt-0.5">NRP:
                                {{ $pinjaman->user->nrp }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Riwayat Gaji & Tunkin atau Info Kompensasi --}}
            @if($isKompensasi)
                <div
                    class="mb-5 rounded-2xl border border-orange-200 bg-orange-50 dark:bg-orange-900/20 dark:border-orange-800/50 p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-900/50 dark:text-orange-400">
                            <flux:icon name="arrow-path" class="size-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-orange-800 dark:text-orange-400">Pengajuan Kompensasi</h4>
                            <p class="mt-1 text-xs text-orange-600 dark:text-orange-500 leading-relaxed">
                                Anggota masih memiliki permohonan pinjaman aktif. Sisa pinjaman sebelumnya akan otomatis
                                dilunasi dari pinjaman baru ini.
                            </p>
                        </div>
                    </div>
                </div>
            @elseif(count($riwayatGaji) > 0)
                <div
                    class="mb-5 rounded-2xl bg-white shadow-sm border border-zinc-200 dark:bg-zinc-900 dark:border-zinc-800 overflow-hidden">
                    <div
                        class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800/60 bg-orange-50/30 dark:bg-orange-900/10">
                        <h4 class="text-xs font-bold text-orange-800 dark:text-orange-400 flex items-center gap-1.5">
                            <flux:icon name="banknotes" class="size-4" />
                            Gaji & Tunkin 3 Bulan Terakhir
                        </h4>
                    </div>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                        @foreach(array_slice($riwayatGaji, 0, 3) as $gaji)
                            <div class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-200">
                                        {{ isset($gaji['bulan']) ? App\Models\SimpananWajib::namaBulan($gaji['bulan']) : '-' }}
                                        {{ $gaji['tahun'] ?? '-' }}
                                    </span>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-zinc-500 dark:text-zinc-400">Gaji Bersih</span>
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-300">Rp
                                            {{ number_format($gaji['gaji_pokok_bersih'] ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-zinc-500 dark:text-zinc-400">Tunkin Bersih</span>
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-300">Rp
                                            {{ number_format($gaji['tunkin_bersih'] ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-xs pt-2 border-t border-dashed border-zinc-200 dark:border-zinc-700">
                                        <span class="font-bold text-orange-700 dark:text-orange-500">Total THP</span>
                                        <span class="font-bold text-orange-700 dark:text-orange-400">Rp
                                            {{ number_format(($gaji['gaji_pokok_bersih'] ?? 0) + ($gaji['tunkin_bersih'] ?? 0), 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Kanan: Form --}}
        <div>
            <div
                class="rounded-2xl bg-white shadow-sm border border-zinc-200 p-6 dark:bg-zinc-900 dark:border-zinc-800">
                <form wire:submit="simpanReview">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah
                                Persetujuan (Rp) <span class="text-rose-500">*</span></label>
                            <div x-data="{
                                display: '',
                                timeout: null,
                                init() {
                                    this.display = this.format($wire.formJumlahAjuan);
                                    this.$watch('$wire.formJumlahAjuan', val => {
                                        if(!val) this.display = '';
                                    });
                                },
                                format(v) {
                                    let val = String(v||'').split('.')[0];
                                    let num = val.replace(/[^0-9]/g, '');
                                    return num ? new Intl.NumberFormat('id-ID').format(num) : '';
                                },
                                updateVal(e) {
                                    this.display = this.format(e.target.value);
                                    clearTimeout(this.timeout);
                                    this.timeout = setTimeout(() => {
                                        $wire.set('formJumlahAjuan', this.display.replace(/[^0-9]/g, ''));
                                    }, 500);
                                }
                            }">
                                <input x-model="display" @input="updateVal" type="text" inputmode="numeric" required
                                    class="w-full rounded-xl border-zinc-300 bg-white px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors shadow-sm"
                                    placeholder="Contoh: 10.000.000">
                            </div>
                            @error('formJumlahAjuan') <span
                            class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tenor
                                (Bulan) <span class="text-rose-500">*</span></label>
                            <input wire:model.live.debounce.500ms="formTenor" type="number" min="1" max="120"
                                class="w-full rounded-xl border-zinc-300 bg-white px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors shadow-sm"
                                required>
                            @error('formTenor') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    {{-- Simulasi Box --}}
                    <div
                        class="mt-6 rounded-xl bg-indigo-50/50 border border-indigo-100 p-5 dark:bg-indigo-950/20 dark:border-indigo-900/50">
                        <h4
                            class="text-xs font-bold text-indigo-800 dark:text-indigo-300 mb-4 flex items-center gap-1.5">
                            <flux:icon name="calculator" class="size-4" />
                            Simulasi Setelah Perubahan
                        </h4>

                        <div class="flex flex-col gap-3">
                            @if($isKompensasi)
                                <div
                                    class="flex justify-between items-center bg-orange-50/50 dark:bg-orange-950/20 p-2 rounded-lg border border-orange-100 dark:border-orange-900/50 mb-1">
                                    <span class="text-[11px] font-semibold text-orange-800 dark:text-orange-400">Sisa
                                        Pinjaman Berjalan</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-200">- Rp
                                        {{ number_format($sisaPinjaman, 0, ',', '.') }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center bg-orange-50/50 dark:bg-orange-950/20 p-2 rounded-lg border border-orange-100 dark:border-orange-900/50 mb-2">
                                    <span class="text-[11px] font-semibold text-orange-800 dark:text-orange-400">Pinalti
                                        Jasa (1x)</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-200">- Rp
                                        {{ number_format($pinaltiKompensasi, 0, ',', '.') }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center mb-2 border-b border-orange-100 dark:border-orange-900/50 pb-2">
                                    <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Nilai Kompensasi
                                        Bersih</span>
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp
                                        {{ number_format((float) ($formJumlahAjuan ?: 0) - $sisaPinjaman - $pinaltiKompensasi, 0, ',', '.') }}</span>
                                </div>
                            @else
                                <div class="flex justify-between items-center">
                                    <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Total Pengajuan</span>
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp
                                        {{ number_format((float) ($formJumlahAjuan ?: 0), 0, ',', '.') }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between items-center">
                                <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Pokok Angsuran</span>
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp
                                    {{ number_format($simulasiPokok ?? 0, 0, ',', '.') }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Jasa Pinjaman (1%)</span>
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp
                                    {{ number_format($simulasiJasa ?? 0, 0, ',', '.') }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Potongan Administrasi
                                    (1%)</span>
                                <span class="text-sm font-semibold text-rose-600 dark:text-rose-400">- Rp
                                    {{ number_format($simulasiBiaya ?? 0, 0, ',', '.') }}</span>
                            </div>

                            <div
                                class="flex justify-between items-center pt-2 border-t border-indigo-100 dark:border-indigo-900/50">
                                <span class="text-xs font-semibold text-emerald-800 dark:text-emerald-400">Jumlah Bersih
                                    Diterima</span>
                                <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp
                                    {{ number_format($simulasiDiterima ?? 0, 0, ',', '.') }}</span>
                            </div>

                            <div
                                class="mt-3 text-center p-4 rounded-xl bg-indigo-600 text-zinc-50 shadow-sm ring-1 ring-indigo-500/50">
                                <span
                                    class="block text-[10px] text-indigo-200 mb-1 uppercase tracking-wider font-semibold">Setoran
                                    Bulanan Pokok + 1%</span>
                                <span class="text-2xl font-bold tracking-tight pb-0.5">Rp
                                    {{ number_format($simulasiAngsuran ?? 0, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    @if($showFormTolak)
                        <div
                            class="mt-8 rounded-xl border border-rose-200 bg-rose-50/50 p-5 dark:bg-rose-950/20 dark:border-rose-900/50 shadow-sm transition-all">
                            <label class="block text-sm font-bold text-rose-800 dark:text-rose-400 mb-2">Alasan Penolakan
                                <span class="text-rose-500">*</span></label>
                            <textarea wire:model="alasanPenolakan" rows="3"
                                class="w-full rounded-xl border-rose-200 shadow-sm focus:border-rose-500 focus:ring-rose-500 dark:bg-zinc-950 dark:border-rose-800/80 dark:text-rose-100 text-sm placeholder:text-rose-300 dark:placeholder:text-rose-800/60 transition-colors"
                                placeholder="Jelaskan alasan penolakan secara singkat..."></textarea>
                            @error('alasanPenolakan') <span
                            class="text-[10px] font-medium text-red-500 mt-1 block">{{ $message }}</span> @enderror

                            <div class="mt-4 flex gap-3">
                                <button type="button" wire:click="batalTolak"
                                    class="flex-1 rounded-xl bg-white border border-rose-200 py-2.5 text-sm font-semibold text-zinc-600 hover:bg-zinc-50 dark:bg-zinc-900 dark:border-rose-900/50 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors">Batal</button>
                                <button type="button" wire:click="tolakReview"
                                    class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-bold text-zinc-50 hover:bg-rose-700 focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition-all shadow-sm">Konfirmasi
                                    Tolak</button>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 flex flex-col gap-3">
                            <button type="submit"
                                class="w-full rounded-xl bg-emerald-600 py-3 text-sm font-bold text-zinc-50 hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all shadow-sm flex justify-center items-center gap-2">
                                <flux:icon name="check-circle" class="size-5" />
                                Simpan & Setujui
                            </button>
                            <button type="button" wire:click="konfirmasiTolak"
                                class="w-full rounded-xl bg-white dark:bg-zinc-800 py-3 text-sm font-bold text-rose-600 border border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all shadow-sm">
                                Tolak Pengajuan
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>

    </div>
</div>