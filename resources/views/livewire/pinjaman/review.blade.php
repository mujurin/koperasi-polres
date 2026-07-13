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
    public $pinjamanLamaAjuan = 0;
    public $jasaTunggakan = 0;
    public $tunggakanBulan = 0;
    public $isKompensasi = false;
    
    public $sisaPinjamanSaatPengajuan = 0;
    public $simulasiDiterimaSaatPengajuan = 0;
    public $selisihBulan = 0;
    public $opsiPencairan = 'saat_ini'; // 'saat_ini' atau 'saat_pengajuan'
    public $abaikanAturanLimit = false;

    public $riwayatGaji = [];

    public $showFormTolak = false;
    public $alasanPenolakan = '';
    public $posisiAntrian = 1;
    public $totalAntrian = 1;
    public $firstProsesId = null;
    public $isFirstProses = false;

    public function mount(Pinjaman $pinjaman)
    {
        // Fail if not in proses or ditunda
        if (!in_array($pinjaman->status, ['proses', 'ditunda'])) {
            return redirect()->route('pinjaman.antrian');
        }

        $this->pinjaman = $pinjaman->load('user');

        $this->posisiAntrian = Pinjaman::whereIn('status', ['proses', 'ditunda'])
            ->where(function ($query) use ($pinjaman) {
                $query->where('created_at', '<', $pinjaman->created_at)
                    ->orWhere(function ($q) use ($pinjaman) {
                        $q->where('created_at', '=', $pinjaman->created_at)
                            ->where('id', '<=', $pinjaman->id);
                    });
            })
            ->count();
        $this->totalAntrian = Pinjaman::whereIn('status', ['proses', 'ditunda'])->count();

        $this->firstProsesId = Pinjaman::where('status', 'proses')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->value('id');

        $this->isFirstProses = ($this->pinjaman->id === $this->firstProsesId);

        $this->formJumlahAjuan = (int) $this->pinjaman->jumlah_ajuan;
        $this->formTenor = (int) $this->pinjaman->tenor;
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
            
            $totalTerbayarSaatPengajuan = \App\Models\Angsuran::where('pinjaman_id', $pinjamanAktif->id)
                ->where('status_pembayaran', 'Lunas')
                ->where('tanggal_bayar', '<=', $this->pinjaman->created_at)
                ->sum('jumlah_bayar');
                
            $this->sisaPinjamanSaatPengajuan = max(0, $totalKewajiban - $totalTerbayarSaatPengajuan);

            $this->selisihBulan = max(0, (\Carbon\Carbon::now()->year - $this->pinjaman->created_at->year) * 12
                + (\Carbon\Carbon::now()->month - $this->pinjaman->created_at->month));
            $this->pinaltiKompensasi = $pinjamanAktif->jumlah_ajuan * 0.01;
            $this->pinjamanLamaAjuan = (float) $pinjamanAktif->jumlah_ajuan;

            $bulanBerjalan = (\Carbon\Carbon::now()->year - $pinjamanAktif->updated_at->year) * 12
                + (\Carbon\Carbon::now()->month - $pinjamanAktif->updated_at->month);
            $targetLunas = max(0, $bulanBerjalan);

            $bulanTerbayar = \App\Models\Angsuran::where('pinjaman_id', $pinjamanAktif->id)
                ->where('status_pembayaran', 'Lunas')
                ->count();

            $this->tunggakanBulan = max(0, $targetLunas - $bulanTerbayar);

            if ($this->tunggakanBulan > 0) {
                $jasaPersenLama = $pinjamanAktif->jasa_persen ?? 1;
                $jasaPerbulanLama = $pinjamanAktif->jumlah_ajuan * ($jasaPersenLama / 100);
                $this->jasaTunggakan = $this->tunggakanBulan * $jasaPerbulanLama;
            }
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
    
    public function setOpsiPencairan($opsi)
    {
        $this->opsiPencairan = $opsi;
        $this->hitungSimulasi();
    }

    public function hitungSimulasi()
    {
        $jumlah = (float) ($this->formJumlahAjuan ?: 0);
        $tenor = (int) ($this->formTenor ?: 0);
        $sisaLama = (float) $this->sisaPinjaman;

        if ($jumlah > 0 && $tenor > 0) {
            if ($sisaLama > 0) {
                $this->isKompensasi = true;
                $nilaiKompensasi = $jumlah - $sisaLama - $this->pinaltiKompensasi - $this->jasaTunggakan;

                if ($nilaiKompensasi <= 0) {
                    $this->simulasiBiaya = 0;
                    $this->simulasiDiterima = 0;
                    $this->simulasiAngsuran = 0;
                    $this->simulasiPokok = 0;
                    $this->simulasiJasa = 0;
                    $this->simulasiDiterimaSaatPengajuan = 0;
                } else {
                    $this->simulasiBiaya = $jumlah * 0.01;
                    $this->simulasiPokok = $jumlah / $tenor;
                    $this->simulasiJasa = $jumlah * 0.01;
                    $this->simulasiAngsuran = $this->simulasiPokok + $this->simulasiJasa;
                    
                    $nilaiKompensasiSaatPengajuan = $jumlah - $this->sisaPinjamanSaatPengajuan - $this->pinaltiKompensasi - $this->jasaTunggakan;
                    $this->simulasiDiterimaSaatPengajuan = $nilaiKompensasiSaatPengajuan > 0 ? $nilaiKompensasiSaatPengajuan - $this->simulasiBiaya : 0;
                    $this->simulasiDiterima = $this->opsiPencairan === 'saat_pengajuan' ? $this->simulasiDiterimaSaatPengajuan : ($nilaiKompensasi - $this->simulasiBiaya);
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

        $sisaDipakai = $this->opsiPencairan === 'saat_pengajuan' ? $this->sisaPinjamanSaatPengajuan : $this->sisaPinjaman;

        if ($this->isKompensasi && $this->formJumlahAjuan <= ($sisaDipakai + $this->pinaltiKompensasi + $this->jasaTunggakan)) {
            $this->addError('formJumlahAjuan', 'Untuk Kompensasi, ajuan harus lebih besar dari sisa hutang, pinalti, & tunggakan (Rp ' . number_format($sisaDipakai + $this->pinaltiKompensasi + $this->jasaTunggakan, 0, ',', '.') . ').');
            return;
        }

        $this->hitungSimulasi();

        if (!$this->abaikanAturanLimit && $this->isKompensasi && $this->simulasiDiterima > $this->pinjamanLamaAjuan) {
            $this->addError('formJumlahAjuan', 'Untuk Kompensasi, bersih yang diterima tidak boleh melebihi jumlah pinjaman sebelumnya (Rp ' . number_format($this->pinjamanLamaAjuan, 0, ',', '.') . '). Beri centang persetujuan di bawah form jika Anda bertindak di luar aturan tetap.');
            return;
        }

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

            $sisaDipakai = $this->opsiPencairan === 'saat_pengajuan' ? $this->sisaPinjamanSaatPengajuan : $this->sisaPinjaman;

            if ($pinjamanAktif && $sisaDipakai > 0) {
                \App\Models\Angsuran::create([
                    'pinjaman_id' => $pinjamanAktif->id,
                    'angsuran_ke' => 999, // Special identifier for Kompensasi early payoff
                    'jumlah_bayar' => $sisaDipakai + $this->pinaltiKompensasi + $this->jasaTunggakan,
                    'tanggal_bayar' => now(),
                    'status_pembayaran' => 'Lunas'
                ]);

                $pinjamanAktif->update([
                    'status' => 'lunas',
                    'keterangan' => $pinjamanAktif->keterangan . ' (Lunas via Kompensasi Pinjaman Baru)'
                ]);
            }
        }

        // Pass download URL via session so the next page can trigger it seamlessly
        // without race conditions from slow server PDF generation
        session()->flash('download_pdf', route('pinjaman.cetak.download', $this->pinjaman->id));
        
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

    public function tundaReview()
    {
        $this->pinjaman->update([
            'status' => 'ditunda',
        ]);
        return redirect()->route('pinjaman.antrian');
    }

    public function aktifkanReview()
    {
        $this->pinjaman->update([
            'status' => 'proses',
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

    @if($pinjaman->status === 'ditunda')
        <div
            class="rounded-2xl border border-amber-300 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/40 p-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/60">
                    <flux:icon name="clock" class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-amber-800 dark:text-amber-200">Pengajuan Tertunda (Antrian
                        #{{ $posisiAntrian }})</h3>
                    <p class="text-xs text-amber-600 dark:text-amber-400">Pengajuan ini sebelumnya ditunda. Anda dapat
                        menyetujui, menolak, atau mengaktifkannya kembali ke antrian aktif.</p>
                </div>
            </div>
            <button type="button" wire:click="aktifkanReview"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-amber-400 px-3.5 py-2 text-xs font-extrabold text-black hover:bg-amber-500 transition-colors shadow-sm">
                <flux:icon name="arrow-path" class="size-3.5" />
                Aktifkan Kembali
            </button>
        </div>
    @elseif($isFirstProses)
        <div
            class="rounded-2xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800/50 dark:bg-emerald-950/40 p-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/60">
                    <flux:icon name="check-circle" class="size-6 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-200">Antrian #{{ $posisiAntrian }}
                        (Prioritas Utama Siap Diproses)</h3>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">Pengajuan ini adalah giliran aktif saat ini
                        dari total {{ $totalAntrian }} antrian.</p>
                </div>
            </div>
            <span class="inline-flex rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white shadow-sm">Siap
                Diproses</span>
        </div>
    @else
        <div
            class="rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/40 p-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/60">
                    <flux:icon name="exclamation-triangle" class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-amber-800 dark:text-amber-200">Peringatan Urutan Antrian
                        (#{{ $posisiAntrian }} dari {{ $totalAntrian }})</h3>
                    <p class="text-xs text-amber-600 dark:text-amber-400">Terdapat pengajuan lain yang masuk lebih dulu atau
                        menunggu diproses. Disarankan memproses antrian prioritas terlebih dahulu.</p>
                </div>
            </div>
            <a wire:navigate href="{{ route('pinjaman.antrian') }}"
                class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-500 transition-colors shadow-sm">
                Lihat Antrian Prioritas
            </a>
        </div>
    @endif

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
                            
                            @if($selisihBulan > 0)
                                <div class="mt-4 bg-white/60 dark:bg-zinc-900/50 rounded-xl p-4 border border-orange-200 dark:border-orange-800/40">
                                    <p class="text-xs font-bold text-orange-800 dark:text-orange-300 mb-3 flex items-center justify-between">
                                        <span>Perbandingan Estimasi Pengajuan vs Realisasi</span>
                                        <span class="bg-orange-100 text-orange-700 dark:bg-orange-950/60 dark:text-orange-400 px-2 py-0.5 rounded shadow-sm">Masa Tunggu: {{ $selisihBulan }} Bulan</span>
                                    </p>
                                    <div class="grid grid-cols-2 gap-4 text-xs">
                                        <div class="space-y-2 p-3 rounded-lg border transition-colors cursor-pointer {!! $opsiPencairan === 'saat_pengajuan' ? 'bg-orange-100 border-orange-300 dark:bg-orange-900/50 dark:border-orange-700/60 shadow-inner' : 'bg-white/40 border-transparent dark:bg-zinc-800/20 hover:bg-orange-50/50' !!}"
                                            wire:click="setOpsiPencairan('saat_pengajuan')">
                                            <div class="flex items-center justify-between">
                                                <div class="text-zinc-600 dark:text-zinc-400 font-medium whitespace-nowrap">Saat Pengajuan</div>
                                                @if($opsiPencairan === 'saat_pengajuan')
                                                    <flux:icon name="check-circle" variant="solid" class="size-4 text-orange-600 dark:text-orange-400" />
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-zinc-500 mb-2">({{ $pinjaman->created_at->translatedFormat('F Y') }})</div>
                                            
                                            <div class="flex flex-col pt-1">
                                                <span class="text-[10px] text-zinc-500">Sisa Hutang:</span>
                                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">Rp {{ number_format($sisaPinjamanSaatPengajuan, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex flex-col pt-1 mt-1 border-t border-orange-200/60 dark:border-orange-800/30">
                                                <span class="text-[10px] text-emerald-600/80 dark:text-emerald-500/80">Estimasi Bersih:</span>
                                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($simulasiDiterimaSaatPengajuan, 0, ',', '.') }}</span>
                                            </div>
                                            @if($opsiPencairan !== 'saat_pengajuan')
                                                <div class="mt-2 text-center">
                                                    <button type="button" class="text-[10px] bg-orange-200 text-orange-800 px-2 py-1 rounded w-full hover:bg-orange-300 transition-colors dark:bg-orange-800 dark:text-orange-200 uppercase font-bold tracking-tight">Pilih Ini</button>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="space-y-2 p-3 rounded-lg border transition-colors cursor-pointer {!! $opsiPencairan === 'saat_ini' ? 'bg-emerald-50 border-emerald-300 shadow-inner dark:bg-emerald-900/40 dark:border-emerald-700/50' : 'bg-white border-emerald-100 shadow-sm dark:border-emerald-900/30 dark:bg-zinc-800 hover:bg-emerald-50/30' !!}"
                                            wire:click="setOpsiPencairan('saat_ini')">
                                            <div class="flex items-center justify-between">
                                                <div class="text-emerald-700 dark:text-emerald-400 font-medium">Saat Ini</div>
                                                @if($opsiPencairan === 'saat_ini')
                                                    <flux:icon name="check-circle" variant="solid" class="size-4 text-emerald-600 dark:text-emerald-400" />
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-zinc-500 mb-2">({{ now()->translatedFormat('F Y') }})</div>
                                            
                                            <div class="flex flex-col pt-1">
                                                <span class="text-[10px] text-zinc-500">Sisa Hutang:</span>
                                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">Rp {{ number_format($sisaPinjaman, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex flex-col pt-1 mt-1 border-t border-emerald-200/60 dark:border-emerald-900/40">
                                                <span class="text-[10px] text-emerald-600/80 dark:text-emerald-500/80">Estimasi Bersih:</span>
                                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($simulasiDiterima, 0, ',', '.') }}</span>
                                            </div>
                                            @if($opsiPencairan !== 'saat_ini')
                                                <div class="mt-2 text-center">
                                                    <button type="button" class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-1 rounded w-full hover:bg-emerald-200 transition-colors uppercase font-bold tracking-tight">Pilih Ini</button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[10px] text-orange-600/90 dark:text-orange-500/90 italic leading-relaxed">
                                        * Estimasi penerimaan realisasi saat ini lebih besar karena anggota tetap membayarkan angsuran selama masa tunggu dari {{ $pinjaman->created_at->translatedFormat('F') }} hingga {{ now()->translatedFormat('F') }}, sehingga sisa hutang telah menyusut.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
            
            @if(count($riwayatGaji) > 0)
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
                            <div wire:ignore x-data="{
                                display: '',
                                timeout: null,
                                init() {
                                    this.display = this.format($wire.formJumlahAjuan);
                                },
                                format(v) {
                                    let num = String(v||'').replace(/[^0-9]/g, '');
                                    return num ? new Intl.NumberFormat('id-ID').format(num) : '';
                                },
                                updateVal(e) {
                                    this.display = this.format(e.target.value);
                                    clearTimeout(this.timeout);
                                    this.timeout = setTimeout(() => {
                                        $wire.set('formJumlahAjuan', this.display.replace(/[^0-9]/g, ''));
                                    }, 600);
                                }
                            }">
                                <input x-model="display" @input="updateVal" type="text" inputmode="numeric" required
                                    class="w-full rounded-xl border-zinc-300 bg-white px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors shadow-sm"
                                    placeholder="Contoh: 10.000.000">
                            </div>
                            @error('formJumlahAjuan') <span
                            class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            @if($isKompensasi && $simulasiDiterima > $pinjamanLamaAjuan)
                                <div class="mt-2 p-3 rounded-xl bg-red-50 border border-red-200 dark:bg-red-950/20 dark:border-red-900/50">
                                    <span class="text-[11px] text-red-600 dark:text-red-400 block mb-2 font-medium">Batas Maksimal Terlampaui: Bersih yang diterima (Rp {{ number_format($simulasiDiterima, 0, ',', '.') }}) melebihi jumlah pinjaman sebelumnya (Rp {{ number_format($pinjamanLamaAjuan, 0, ',', '.') }}). Aturan koperasi menolak ini namun admin sistem bisa mengabaikannya.</span>
                                    
                                    <label class="flex items-start gap-2 cursor-pointer mt-1.5 p-1">
                                        <input type="checkbox" wire:model="abaikanAturanLimit" class="mt-0.5 rounded text-red-600 focus:ring-red-500 border-red-300 shadow-sm dark:border-red-900/60 dark:bg-zinc-950 bg-white">
                                        <span class="text-[10px] text-red-800 dark:text-red-300 font-semibold leading-snug">Ya, abaikan peringatan ini. Lanjutkan pencairan dengan nominal tersebut.</span>
                                    </label>
                                </div>
                            @endif
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
                                    <span class="text-[11px] font-semibold text-orange-800 dark:text-orange-400">Sisa Pokok
                                        Hutang @if($selisihBulan > 0)({{ $opsiPencairan === 'saat_pengajuan' ? 'Saat Pgjukan' : 'Saat Ini' }})@endif</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-200">- Rp
                                        {{ number_format($opsiPencairan === 'saat_pengajuan' ? $sisaPinjamanSaatPengajuan : $sisaPinjaman, 0, ',', '.') }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center bg-orange-50/50 dark:bg-orange-950/20 p-2 rounded-lg border border-orange-100 dark:border-orange-900/50 mb-2">
                                    <span class="text-[11px] font-semibold text-orange-800 dark:text-orange-400">Pinalti
                                        Jasa (1x)</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-200">- Rp
                                        {{ number_format($pinaltiKompensasi, 0, ',', '.') }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center bg-orange-50/50 dark:bg-orange-950/20 p-2 rounded-lg border border-orange-100 dark:border-orange-900/50 mb-2">
                                    <span class="text-[11px] font-semibold text-orange-800 dark:text-orange-400">Potongan
                                        Administrasi
                                        (1%)</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-200">- Rp
                                        {{ number_format($simulasiBiaya ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center bg-orange-50/50 dark:bg-orange-950/20 p-2 rounded-lg border border-orange-100 dark:border-orange-900/50 mb-2">
                                    <span class="text-[11px] font-semibold text-orange-800 dark:text-orange-400">Jasa
                                        Tunggakan ({{ $tunggakanBulan }} Bulan)</span>
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-200">- Rp
                                        {{ number_format($jasaTunggakan, 0, ',', '.') }}</span>
                                </div>
                            @else
                                <div class="flex justify-between items-center">
                                    <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Total Pengajuan</span>
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp
                                        {{ number_format((float) ($formJumlahAjuan ?: 0), 0, ',', '.') }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between items-center mt-1">
                                <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Pokok Angsuran</span>
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp
                                    {{ number_format($simulasiPokok ?? 0, 0, ',', '.') }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-[11px] text-zinc-600 dark:text-zinc-400">Jasa Pinjaman (1%) /
                                    Bulan</span>
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp
                                    {{ number_format($simulasiJasa ?? 0, 0, ',', '.') }}</span>
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
                                <flux:icon name="printer" class="size-5" />
                                Setujui & Cetak
                            </button>
                            <div class="grid grid-cols-2 gap-3">
                                @if($pinjaman->status !== 'ditunda')
                                    <button type="button" wire:click="tundaReview"
                                        class="w-full rounded-xl bg-amber-500 py-3 text-sm font-bold text-white hover:bg-amber-600 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all shadow-sm flex justify-center items-center gap-1.5">
                                        <flux:icon name="clock" class="size-4" />
                                        Tunda
                                    </button>
                                @else
                                    <button type="button" wire:click="aktifkanReview"
                                        class="w-full rounded-xl bg-amber-400 py-3 text-sm font-extrabold text-black hover:bg-amber-500 focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 transition-all shadow-sm flex justify-center items-center gap-1.5">
                                        <flux:icon name="arrow-path" class="size-4" />
                                        Aktifkan
                                    </button>
                                @endif
                                <button type="button" wire:click="konfirmasiTolak"
                                    class="w-full rounded-xl bg-white dark:bg-zinc-800 py-3 text-sm font-bold text-rose-600 border border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all shadow-sm flex justify-center items-center gap-1.5">
                                    <flux:icon name="x-circle" class="size-4" />
                                    Tolak
                                </button>
                            </div>
                        </div>
                    @endif
                </form>
            </div>
        </div>

    </div>
</div>