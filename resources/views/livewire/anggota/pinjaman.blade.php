<?php

use App\Models\Pinjaman;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.anggota')] class extends Component {
    #[Url]
    public string $type = 'baru';

    public string $jumlah_ajuan = '';
    public string $tenor = '';
    public string $keterangan = '';
    public string $jenis_permohonan = 'Biasa';
    public bool $saved = false;
    public bool $hasActiveRequest = false;
    
    // WA Setup verification
    public bool $needsWaSetup = false;
    public string $no_wa_input = '';

    // Simulation properties
    public float $biaya_administrasi = 0;
    public float $jumlah_diterima = 0;
    public float $angsuran_perbulan = 0;
    public float $pokok_angsuran = 0;
    public float $jasa_perbulan = 0;
    public float $sisaPinjaman = 0;
    public float $pinaltiKompensasi = 0;
    public float $pinjamanLamaAjuan = 0;
    public float $rekomendasiPinjaman = 0;
    public int $tunggakanBulan = 0;
    public float $jasaTunggakan = 0;
    public bool $isKompensasi = false;
    public int $nomorAntrianSaya = 0;
    public int $totalAntrian = 0;
    public string $statusRequestSaya = '';
    public bool $invalidType = false;
    public string $invalidMessage = '';

    public function mount()
    {
        if (empty(Auth::user()->no_wa)) {
            $this->needsWaSetup = true;
        }

        $pendingLoan = Auth::user()->pinjaman()->whereIn('status', ['proses', 'ditunda'])->first();
        if ($pendingLoan) {
            $this->hasActiveRequest = true;
            $this->statusRequestSaya = $pendingLoan->status;
            $this->nomorAntrianSaya = Pinjaman::whereIn('status', ['proses', 'ditunda'])
                ->where(function ($query) use ($pendingLoan) {
                    $query->where('created_at', '<', $pendingLoan->created_at)
                          ->orWhere(function ($q) use ($pendingLoan) {
                              $q->where('created_at', '=', $pendingLoan->created_at)
                                ->where('id', '<=', $pendingLoan->id);
                          });
                })
                ->count();
            $this->totalAntrian = Pinjaman::whereIn('status', ['proses', 'ditunda'])->count();
        } else {
            $this->hasActiveRequest = false;
        }
        
        // Cek Pijaman berjalan (Sistem Kompensasi)
        $pinjamanAktif = Auth::user()->pinjaman()->where('status', 'disetujui')->latest()->first();
        $this->sisaPinjaman = 0;
        
        if ($pinjamanAktif) {
            $this->pinjamanLamaAjuan = (float) $pinjamanAktif->jumlah_ajuan;
            $totalTerbayar = \App\Models\Angsuran::where('pinjaman_id', $pinjamanAktif->id)
                ->where('status_pembayaran', 'Lunas')
                ->sum('jumlah_bayar');

            $totalKewajiban = $pinjamanAktif->angsuran_perbulan * $pinjamanAktif->tenor;
            
            $this->sisaPinjaman = max(0, $totalKewajiban - $totalTerbayar);
            $this->pinaltiKompensasi = $pinjamanAktif->jumlah_ajuan * 0.01;

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
            
            $tanggunganTotal = $this->sisaPinjaman + $this->pinaltiKompensasi + $this->jasaTunggakan;
            $maxAjuan = ($this->pinjamanLamaAjuan + $tanggunganTotal) / 0.99;
            $rekomendasi = floor($maxAjuan / 1000) * 1000;
            $this->rekomendasiPinjaman = min($rekomendasi, 60000000);
        }

        if ($this->type === 'baru' && $this->sisaPinjaman > 0) {
            $this->invalidType = true;
            $this->invalidMessage = 'Anda masih memiliki tanggungan pinjaman aktif. Silakan gunakan fitur Kompensasi (Top-up) jika ingin mengajukan pinjaman baru.';
        } elseif ($this->type === 'kompensasi' && $this->sisaPinjaman <= 0) {
            $this->invalidType = true;
            $this->invalidMessage = 'Anda tidak memiliki pinjaman aktif yang bisa di-kompensasikan. Silakan gunakan fitur Pengajuan Baru.';
        }

        $this->hitungSimulasi();
    }

    public function saveWa()
    {
        $this->validate([
            'no_wa_input' => 'required|numeric|min_digits:10|max_digits:15',
        ], [
            'no_wa_input.required' => 'Nomor WhatsApp wajib diisi.',
            'no_wa_input.numeric' => 'Hanya masukkan angka (contoh: 0812...).',
            'no_wa_input.min_digits' => 'Nomor tidak valid (minimal 10 digit).',
            'no_wa_input.max_digits' => 'Nomor kepanjangan (maksimal 15 digit).',
        ]);

        $user = Auth::user();
        $user->no_wa = $this->no_wa_input;
        $user->save();

        $this->needsWaSetup = false;
    }

    public function updated($property)
    {
        if (in_array($property, ['jumlah_ajuan', 'tenor'])) {
            $this->hitungSimulasi();
        }
        if ($property === 'jenis_permohonan') {
            if ($this->jenis_permohonan === 'Urgent') {
                $this->keterangan = 'Keluarga Meninggal';
            } else {
                $this->keterangan = '';
            }
        }
    }

    public function hitungSimulasi(): void
    {
        $jumlah = (float) ($this->jumlah_ajuan ?: 0);
        $tenorBulan = (int) $this->tenor;
        $sisaLama = (float) $this->sisaPinjaman;

        if ($jumlah > 0 && $tenorBulan > 0) {
            if ($sisaLama > 0) {
                // Skema KOMPENSASI
                $this->isKompensasi = true;
                $nilaiKompensasi = $jumlah - $sisaLama - $this->pinaltiKompensasi - $this->jasaTunggakan;
                if ($nilaiKompensasi <= 0) {
                    $this->biaya_administrasi = 0;
                    $this->jumlah_diterima = 0;
                    $this->angsuran_perbulan = 0;
                    $this->pokok_angsuran = 0;
                    $this->jasa_perbulan = 0;
                } else {
                    $this->biaya_administrasi = $jumlah * 0.01;
                    $this->jumlah_diterima = $nilaiKompensasi - $this->biaya_administrasi;
                    
                    // Unified Debt: Angsuran applies to the TOTAL combined debt (jumlah ajuan)
                    $this->pokok_angsuran = $jumlah / $tenorBulan;
                    $this->jasa_perbulan = $jumlah * 0.01;
                    $this->angsuran_perbulan = $this->pokok_angsuran + $this->jasa_perbulan;
                }
            } else {
                // Skema BIASA
                $this->isKompensasi = false;
                $this->biaya_administrasi = $jumlah * 0.01;
                $this->jumlah_diterima = $jumlah - $this->biaya_administrasi;
                
                $this->pokok_angsuran = $jumlah / $tenorBulan;
                $this->jasa_perbulan = $jumlah * 0.01;
                $this->angsuran_perbulan = $this->pokok_angsuran + $this->jasa_perbulan;
            }
        } else {
            $this->biaya_administrasi = 0;
            $this->jumlah_diterima = 0;
            $this->angsuran_perbulan = 0;
            $this->pokok_angsuran = 0;
            $this->jasa_perbulan = 0;
        }
    }

    public function getRiwayatPinjamanProperty()
    {
        return Auth::user()->pinjaman()->latest('created_at')->get();
    }

    public function simpan(): void
    {
        if (Auth::user()->pinjaman()->where('status', 'proses')->exists()) {
            return;
        }

        $this->validate([
            'jumlah_ajuan' => 'required|numeric|min:1',
            'tenor' => 'required|integer|min:1|max:120',
            'jenis_permohonan' => 'required|in:Biasa,Urgent',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $this->hitungSimulasi();

        if ($this->sisaPinjaman > 0) {
            if ((float) $this->jumlah_ajuan > 60000000) {
                $this->addError('jumlah_ajuan', 'Batas maksimal permohonan untuk pinjaman Kompensasi adalah Rp 60.000.000.');
                return;
            }
            if ((float) $this->jumlah_ajuan <= ($this->sisaPinjaman + $this->pinaltiKompensasi + $this->jasaTunggakan)) {
                $this->addError('jumlah_ajuan', 'Untuk layanan Kompensasi, pengajuan baru harus lebih besar dari tanggungan berjalan (Sisa pokok + Pinalti Jasa + Jasa Tunggakan).');
                return;
            }
            if ($this->jumlah_diterima > $this->pinjamanLamaAjuan) {
                $this->addError('jumlah_ajuan', 'Untuk layanan Kompensasi, bersih yang diterima (Rp ' . number_format($this->jumlah_diterima, 0, ',', '.') . ') tidak boleh melebihi jumlah pinjaman sebelumnya (Rp ' . number_format($this->pinjamanLamaAjuan, 0, ',', '.') . ').');
                return;
            }
        }

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
        $this->hasActiveRequest = true;
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
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white leading-tight">Pinjaman {{ $type === 'kompensasi' ? '(Kompensasi)' : 'Baru' }}</h1>
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
    @if($needsWaSetup)
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 mt-2 shadow-sm dark:bg-blue-950/40 dark:border-blue-800/50 flex flex-col gap-4">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-blue-900 dark:text-blue-100">Setup Nomor WhatsApp</h3>
                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">Sebelum melanjutkan permohonan pinjaman, kami perlu mencatat nomor WhatsApp Anda agar admin bisa menghubungi Anda nanti.</p>
                </div>
            </div>
            <form wire:submit.prevent="saveWa" class="flex flex-col gap-3 mt-1">
                <div>
                    <input wire:model="no_wa_input" type="tel" x-mask="08999999999999" placeholder="Contoh: 081234567890"
                        class="w-full rounded-xl border-zinc-200 bg-white px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors">
                    @error('no_wa_input') <span class="text-[10px] text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>
                <button type="submit"
                    class="w-full rounded-xl bg-zinc-900 dark:bg-white px-4 py-3 text-sm font-bold text-white dark:text-zinc-900 shadow hover:bg-zinc-800 dark:hover:bg-zinc-100 active:scale-[0.98] transition-all">
                    Simpan dan Lanjutkan
                </button>
            </form>
        </div>
    @elseif($hasActiveRequest && !$saved)
        <div class="rounded-2xl border {{ $statusRequestSaya === 'ditunda' ? 'border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/40' : 'border-orange-200 bg-orange-50 dark:border-orange-800/50 dark:bg-orange-950/40' }} p-5 mt-2 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <svg class="size-6 {{ $statusRequestSaya === 'ditunda' ? 'text-amber-500' : 'text-orange-500' }} mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        @if($statusRequestSaya === 'ditunda')
                            <h3 class="text-sm font-bold text-amber-800 dark:text-amber-200">Permohonan Pinjaman Ditunda Sementara</h3>
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Permohonan pinjaman Anda saat ini sedang ditunda oleh admin. Posisi urutan antrian Anda tetap dipertahankan (#{{ $nomorAntrianSaya }}).</p>
                        @else
                            <h3 class="text-sm font-bold text-orange-800 dark:text-orange-200">Permohonan Sedang Diproses</h3>
                            <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">Anda tidak dapat mengajukan pinjaman baru karena masih ada permohonan yang sedang menunggu persetujuan admin.</p>
                        @endif
                        @if($nomorAntrianSaya > 0)
                            <div class="mt-2.5 inline-flex items-center gap-2 rounded-lg {{ $statusRequestSaya === 'ditunda' ? 'bg-amber-100 dark:bg-amber-900/50 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200' : 'bg-orange-100 dark:bg-orange-900/50 border-orange-200 dark:border-orange-800 text-orange-800 dark:text-orange-200' }} px-3 py-1.5 border">
                                <flux:icon name="clock" class="size-4 {{ $statusRequestSaya === 'ditunda' ? 'text-amber-600 dark:text-amber-400' : 'text-orange-600 dark:text-orange-400' }}" />
                                <span class="text-xs font-semibold">
                                    Nomor Antrian Anda: <strong class="{{ $statusRequestSaya === 'ditunda' ? 'text-amber-600 dark:text-amber-400' : 'text-orange-600 dark:text-orange-400' }}">#{{ $nomorAntrianSaya }}</strong> dari {{ $totalAntrian }} pengajuan
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
                @if($statusRequestSaya === 'ditunda')
                    <span class="inline-flex shrink-0 rounded-full bg-amber-100 dark:bg-amber-900/50 px-3 py-1 text-xs font-bold text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        Tertunda (#{{ $nomorAntrianSaya }})
                    </span>
                @elseif($nomorAntrianSaya === 1)
                    <span class="inline-flex shrink-0 rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300 animate-pulse border border-emerald-200 dark:border-emerald-800">
                        Giliran Berikutnya
                    </span>
                @endif
            </div>
        </div>
    @elseif(!$saved)
    @if($invalidType)
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 mt-2 shadow-sm dark:bg-rose-950/40 dark:border-rose-800/50">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-rose-900 dark:text-rose-100">Perhatian</h3>
                    <p class="text-xs text-rose-700 dark:text-rose-300 mt-1">{{ $invalidMessage }}</p>
                    <a href="{{ route('anggota.dashboard') }}" wire:navigate class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-rose-800 hover:text-rose-600 dark:text-rose-200 dark:hover:text-rose-100 bg-rose-100 dark:bg-rose-900/50 px-3 py-1.5 rounded-lg border border-rose-200 dark:border-rose-800">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    @else
    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900 mt-2">
        <div class="p-5 flex flex-col gap-6">
            
            <form wire:submit="simpan" class="flex flex-col gap-5">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah Pengajuan Pinjaman (Rp)</label>
                    <div x-data="{
                        display: '',
                        timeout: null,
                        init() {
                            this.display = this.format($wire.jumlah_ajuan);
                            this.$watch('$wire.jumlah_ajuan', val => {
                                if(!val) this.display = '';
                                else this.display = this.format(String(val));
                            });
                        },
                        format(v) {
                            let str = String(v||'');
                            // If there is a decimal like .00 from backend, we might want to split it by dot, 
                            // but since it's IDR formatted as 1.000 from input, it uses dots as separators.
                            // However, Livewire model returns plain numbers without decimals like '6000'.
                            let num = str.replace(/[^0-9]/g, '');
                            return num ? new Intl.NumberFormat('id-ID').format(num) : '';
                        },
                        updateVal(e) {
                            this.display = this.format(e.target.value);
                            clearTimeout(this.timeout);
                            this.timeout = setTimeout(() => {
                                $wire.set('jumlah_ajuan', this.display.replace(/[^0-9]/g, ''));
                            }, 500);
                        }
                    }">
                        <input x-model="display" @input="updateVal" type="text" inputmode="numeric"
                            class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                            placeholder="Contoh: 10.000.000">
                    </div>
                    <div class="flex flex-col items-start gap-1.5 mt-1.5">
                        @if($sisaPinjaman > 0)
                            <p class="text-[10px] font-bold text-orange-600 dark:text-orange-400">Tanggungan saat ini: Rp {{ number_format($sisaPinjaman + $pinaltiKompensasi + $jasaTunggakan, 0, ',', '.') }} </p>
                            @if($rekomendasiPinjaman > 0)
                                <button type="button" wire:click="$set('jumlah_ajuan', '{{ $rekomendasiPinjaman }}')" class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline text-left mt-0.5">
                                    Rekomendasi kompensasi: Rp {{ number_format($rekomendasiPinjaman, 0, ',', '.') }} agar bersih diterima: Rp {{ number_format($pinjamanLamaAjuan, 0, ',', '.') }}
                                </button>
                            @endif
                        @endif
                    </div>
                    @error('jumlah_ajuan') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    @if($sisaPinjaman > 0 && (float)($jumlah_ajuan ?: 0) > 0 && (float)$jumlah_ajuan <= ($sisaPinjaman + $pinaltiKompensasi + $jasaTunggakan))
                        <span class="text-[10px] text-red-500 mt-1 block">Untuk layanan Kompensasi, pengajuan baru harus lebih besar dari tanggungan berjalan (Sisa pokok + Pinalti Jasa + Jasa Tunggakan).</span>
                    @elseif($sisaPinjaman > 0 && (float)($jumlah_ajuan ?: 0) > 60000000)
                        <span class="text-[10px] text-red-500 mt-1 block">Batas maksimal permohonan untuk pinjaman Kompensasi adalah Rp 60.000.000.</span>
                    @elseif($sisaPinjaman > 0 && (float)($jumlah_ajuan ?: 0) > 0 && $jumlah_diterima > $pinjamanLamaAjuan)
                        <span class="text-[10px] text-red-500 mt-1 block">Untuk layanan Kompensasi, bersih yang diterima (Rp {{ number_format($jumlah_diterima, 0, ',', '.') }}) tidak boleh melebihi jumlah pinjaman sebelumnya (Rp {{ number_format($pinjamanLamaAjuan, 0, ',', '.') }}).</span>
                    @endif
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
                        <select wire:model.live="jenis_permohonan" class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors">
                            <option value="Biasa">Biasa</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                        @error('jenis_permohonan') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Keterangan / Tujuan Pinjaman</label>
                    @if($jenis_permohonan === 'Urgent')
                        <select wire:model="keterangan" class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors">
                            <option value="Keluarga Meninggal">Keluarga Meninggal</option>
                            <option value="Keluarga Opname">Keluarga Opname</option>
                            <option value="Pendidikan Polri">Pendidikan Polri</option>
                        </select>
                    @else
                        <input wire:model="keterangan" type="text"
                            class="w-full rounded-xl border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                            placeholder="Contoh: Renovasi rumah">
                    @endif
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
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Sisa Pokok Hutang</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp {{ number_format($isKompensasi ? $sisaPinjaman : 0, 0, ',', '.') }}</span>
                        </div>

                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Jasa Pinalti (1x)</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp {{ number_format($isKompensasi ? $pinaltiKompensasi : 0, 0, ',', '.') }}</span>
                        </div>

                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <div class="flex items-center gap-1">
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Potongan Administrasi (1%)</span>
                            </div>
                            <span class="text-sm font-semibold text-rose-600 dark:text-rose-400">- Rp {{ number_format($biaya_administrasi, 0, ',', '.') }}</span>
                        </div>

                        @if($isKompensasi)
                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Jasa Tunggakan ({{ $tunggakanBulan }} Bulan)</span>
                            <span class="text-sm font-semibold text-rose-600 dark:text-rose-400">- Rp {{ number_format($jasaTunggakan, 0, ',', '.') }}</span>
                        </div>
                        @endif


                        <div class="my-1 border-t border-dashed border-indigo-200 dark:border-indigo-800/60"></div>

                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border {{ ($isKompensasi && $jumlah_diterima > $pinjamanLamaAjuan) ? 'border-rose-300 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/20' : 'border-emerald-100 dark:border-emerald-900/30' }}">
                            <span class="text-xs font-semibold {{ ($isKompensasi && $jumlah_diterima > $pinjamanLamaAjuan) ? 'text-rose-800 dark:text-rose-400' : 'text-emerald-800 dark:text-emerald-400' }}">Diterima</span>
                            <span class="text-base font-bold {{ ($isKompensasi && $jumlah_diterima > $pinjamanLamaAjuan) ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">Rp {{ number_format($jumlah_diterima, 0, ',', '.') }}</span>
                        </div>

                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Tenor</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">{{ (int)($tenor ?: 0) }} Bulan</span>
                        </div>

                        <div class="flex justify-between items-center bg-white dark:bg-zinc-900/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-800">
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Jasa Pinjaman (1%)</span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">Rp {{ number_format($jasa_perbulan, 0, ',', '.') }}</span>
                        </div>

                        <div class="mt-2 text-center p-3 rounded-lg bg-indigo-600 text-white shadow-sm ring-1 ring-indigo-500/50">
                            <span class="block text-[10px] text-indigo-200 mb-1">Setoran Angsuran Per Bulan (Pokok + Jasa 1%)</span>
                            <span class="text-xl font-bold tracking-tight">Rp {{ number_format($angsuran_perbulan, 0, ',', '.') }}</span>
                            <span class="text-[10px] text-indigo-200 ml-1">/ bulan</span>
                        </div>
                    </div>
                </div>

                <button type="submit" @if(!(float)$jumlah_ajuan || ($sisaPinjaman > 0 && ((float)$jumlah_ajuan <= ($sisaPinjaman + $pinaltiKompensasi + $jasaTunggakan) || (float)$jumlah_ajuan > 60000000 || $jumlah_diterima > $pinjamanLamaAjuan))) disabled @endif
                    class="w-full mt-2 rounded-xl bg-zinc-900 dark:bg-white px-4 py-3.5 text-sm font-semibold text-white dark:text-zinc-900 shadow-xl hover:bg-zinc-800 dark:hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed">
                    Kirim Permohonan
                </button>
            </form>
        </div>
    </div>
    @endif
    @endif

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
                                @if($item->status === 'disetujui' || $item->status === 'lunas') bg-emerald-100 dark:bg-emerald-900/40
                                @elseif($item->status === 'ditolak') bg-rose-100 dark:bg-rose-900/40
                                @else bg-orange-100 dark:bg-orange-900/40 @endif">
                                
                                @if($item->status === 'disetujui')
                                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
                                @elseif($item->status === 'lunas')
                                    <svg class="size-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
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
                            @elseif($item->status === 'lunas')
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-300 dark:border-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Lunas</span>
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
