<?php

use App\Models\Pinjaman;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.print')] class extends Component {
    public Pinjaman $pinjaman;

    public $simulasiDiterima = 0;
    public $simulasiAngsuran = 0;
    public $simulasiBiaya = 0;
    public $simulasiPokok = 0;
    public $simulasiJasa = 0;
    public $sisaPinjaman = 0;
    public $pinaltiKompensasi = 0;
    public $jasaTunggakan = 0;
    public $tunggakanBulan = 0;
    public $monthlySchedule = [];
    public $totalPokokMasuk = 0;
    public $totalJasaMasuk = 0;
    public $sisaPinjamanBalance = 0;
    public $sisaPokok = 0;
    public $sisaJasa = 0;
    public $paidMonths = 0;
    public $isKompensasi = false;

    public function mount(Pinjaman $pinjaman)
    {
        $this->pinjaman = $pinjaman->load('user');

        $jumlah = (float) $this->pinjaman->jumlah_ajuan;
        $tenor = (int) $this->pinjaman->tenor;

        if (str_contains(strtolower($this->pinjaman->keterangan), 'kompensasi')) {
            $this->isKompensasi = true;
            // Lookup old loan details from angsurans close-out
            $pinjamanLama = \App\Models\Pinjaman::where('user_id', $this->pinjaman->user_id)
                ->where('id', '<', $this->pinjaman->id)
                ->latest()
                ->first();

            if ($pinjamanLama) {
                // Determine values based on current Pinjaman recorded parameters
                $totalTerbayar = \App\Models\Angsuran::where('pinjaman_id', $pinjamanLama->id)
                    ->where('status_pembayaran', 'Lunas')
                    ->where('angsuran_ke', '!=', 999)
                    ->sum('jumlah_bayar');

                $totalKewajiban = $pinjamanLama->angsuran_perbulan * $pinjamanLama->tenor;
                $this->sisaPinjaman = max(0, $totalKewajiban - $totalTerbayar);
                $this->pinaltiKompensasi = $pinjamanLama->jumlah_ajuan * 0.01;

                $bulanBerjalan = (\Carbon\Carbon::parse($this->pinjaman->created_at)->year - $pinjamanLama->updated_at->year) * 12
                    + (\Carbon\Carbon::parse($this->pinjaman->created_at)->month - $pinjamanLama->updated_at->month);
                $targetLunas = max(0, $bulanBerjalan);

                $bulanTerbayar = \App\Models\Angsuran::where('pinjaman_id', $pinjamanLama->id)
                    ->where('status_pembayaran', 'Lunas')
                    ->where('angsuran_ke', '!=', 999)
                    ->count();

                $this->tunggakanBulan = max(0, $targetLunas - $bulanTerbayar);

                if ($this->tunggakanBulan > 0) {
                    $jasaPersenLama = $pinjamanLama->jasa_persen ?? 1;
                    $jasaPerbulanLama = $pinjamanLama->jumlah_ajuan * ($jasaPersenLama / 100);
                    $this->jasaTunggakan = $this->tunggakanBulan * $jasaPerbulanLama;
                }

                $this->simulasiDiterima = $this->pinjaman->jumlah_diterima;
                $this->simulasiAngsuran = $this->pinjaman->angsuran_perbulan;
                $this->simulasiPokok = $jumlah / $tenor;
                $this->simulasiBiaya = $jumlah * 0.01;
                $this->simulasiJasa = $jumlah * 0.01;
            }

        } else {
            $this->simulasiBiaya = $jumlah * 0.01;
            $this->simulasiDiterima = $jumlah - $this->simulasiBiaya;
            $this->simulasiPokok = $jumlah / $tenor;
            $this->simulasiJasa = $jumlah * 0.01;
            $this->simulasiAngsuran = $this->simulasiPokok + $this->simulasiJasa;
        }

        $approvalDate = \Carbon\Carbon::parse($this->pinjaman->updated_at ?? $this->pinjaman->created_at)->startOfMonth();
        $this->paidMonths = min($tenor, max(0, $approvalDate->diffInMonths(\Carbon\Carbon::now()) + 1));

        $this->monthlySchedule = collect(range(0, max(0, $this->paidMonths - 1)))->map(fn($index) => [
            'label' => $approvalDate->copy()->addMonths($index)->translatedFormat('M Y'),
            'amount' => $this->pinjaman->angsuran_perbulan,
        ])->toArray();

        $this->totalPokokMasuk = round($this->simulasiPokok * $this->paidMonths);
        $this->totalJasaMasuk = round($this->simulasiJasa * $this->paidMonths);
        $this->sisaPokok = max(0, $this->pinjaman->jumlah_ajuan - $this->totalPokokMasuk);
        $this->sisaJasa = max(0, ($this->simulasiJasa * $tenor) - $this->totalJasaMasuk);
        $this->sisaPinjamanBalance = $this->sisaPokok + $this->sisaJasa;
    }
}; ?>

<div class="h-full w-full bg-white text-zinc-900" x-data="{
    init() {
        setTimeout(() => window.print(), 500);
    }
}">
    <!-- Header -->
    <div class="text-center pb-8 border-b-2 border-zinc-900 mb-8">
        <h1 class="text-3xl font-black uppercase tracking-wider mb-2">DATA PINJAMAN</h1>
    </div>

    <!-- Identitas Pemohon -->
    <div class="mb-10">
        <h3 class="font-bold text-lg border-b border-zinc-300 pb-2 mb-4 uppercase tracking-wide">DATA ANGGOTA</h3>
        <table class="w-full text-sm">
            <tbody>
                <tr>
                    <td class="py-2 w-1/3 font-semibold">Nama</td>
                    <td class="py-2 w-2/3">: <span class="font-bold">{{ $pinjaman->user->name }}</span></td>
                </tr>
                <tr>
                    <td class="py-2 font-semibold">NRP</td>
                    <td class="py-2">: {{ $pinjaman->user->nrp }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Rincian Pinjaman -->
    <div class="mb-10">
        <h3 class="font-bold text-lg border-b border-zinc-300 pb-2 mb-4 uppercase tracking-wide">PINJAMAN</h3>

        <div class="bg-zinc-50 p-6 rounded-lg border border-zinc-200">
            <h4 class="font-extrabold text-xl text-center mb-6">Total pengajuan: Rp
                {{ number_format($pinjaman->jumlah_ajuan, 0, ',', '.') }}
            </h4>

            <table class="w-full text-sm">
                <tbody>
                    <tr>
                        <td class="py-3 font-semibold">Jangka waktu</td>
                        <td class="py-3 text-right">: {{ $pinjaman->tenor }} bulan</td>
                    </tr>
                    <tr>
                        <td class="py-3 font-semibold">Setoran per bulan</td>
                        <td class="py-3 text-right">: Rp {{ number_format($pinjaman->angsuran_perbulan, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 font-semibold">Pokok pinjaman</td>
                        <td class="py-3 text-right">: Rp {{ number_format($simulasiPokok, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 font-semibold">Jasa Pinjaman</td>
                        <td class="py-3 text-right">: Rp {{ number_format($simulasiJasa, 0, ',', '.') }}</td>
                    </tr>
                    @if($isKompensasi)
                        <tr>
                            <td class="py-3 font-semibold">Potongan Administrasi</td>
                            <td class="py-3 text-right">: Rp {{ number_format($simulasiBiaya, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="py-3 font-semibold">Jasa Tunggakan</td>
                            <td class="py-3 text-right">: Rp {{ number_format($jasaTunggakan, 0, ',', '.') }}</td>
                        </tr>
                    @endif
                    <tr class="border-t-2 border-zinc-900">
                        <td class="py-4 font-bold text-lg">Total Bersih Diterima</td>
                        <td class="py-4 text-right font-bold text-lg">Rp
                            {{ number_format($pinjaman->jumlah_diterima ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Setoran Perbulan -->
    <div>
        <h3 class="font-bold text-lg border-b border-zinc-300 pb-2 mb-4 uppercase tracking-wide">SETORAN PERBULAN</h3>
        <table class="w-full text-sm">
            <tbody>
                @forelse($monthlySchedule as $row)
                    <tr>
                        <td class="py-2 font-semibold">{{ $row['label'] }}</td>
                        <td class="py-2 text-right">: Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="py-2">Belum ada periode setoran.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-6 text-sm">
            <table class="w-full text-sm">
                <tbody>
                    <tr>
                        <td class="py-2 font-semibold">TOTAL POKOK MASUK</td>
                        <td class="py-2 text-right">: Rp {{ number_format($totalPokokMasuk, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold">TOTAL JASA MASUK</td>
                        <td class="py-2 text-right">: Rp {{ number_format($totalJasaMasuk, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-8 text-center text-xl font-bold">
            Sisa pinjaman Rp {{ number_format($sisaPinjamanBalance, 0, ',', '.') }}
        </div>
        <div class="mt-2 text-sm">
            <table class="w-full text-sm">
                <tbody>
                    <tr>
                        <td class="py-2 font-semibold">POKOK</td>
                        <td class="py-2 text-right">: Rp {{ number_format($sisaPokok, 0, ',', '.') }} (AKUMULASI TOTAL SISA POKOK PINJAMAN)</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold">JASA</td>
                        <td class="py-2 text-right">: Rp {{ number_format($sisaJasa, 0, ',', '.') }} (AKUMULASI TOTAL SISA JASA PINJAMAN)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tanda Tangan -->
    <div class="mt-12 pt-4 break-inside-avoid text-sm">
        <!-- Header Jabatan & Tanggal -->
        <div class="flex justify-between items-baseline">
            <div class="text-left">
                <p class="font-semibold uppercase tracking-wide">Bendahara Primkoppol</p>
            </div>
            <div class="text-right">
                <p class="font-semibold uppercase tracking-wide">Gangga, {{ now()->translatedFormat('j F Y') }}</p>
                <p class="mt-1">Yang mengajukan</p>
            </div>
        </div>

        <!-- Spacer / Ruang Kosong Tanda Tangan (24 = 6rem / ~96px) -->
        <div class="h-24"></div>

        <!-- Nama Pemohon & Bendahara -->
        <div class="grid grid-cols-2 gap-4">
            <div class="text-left">
                <p class="font-bold uppercase text-lg">Pande Nyoman Suastika</p>
                <p class="text-xs mt-1">NRP 86071388</p>
            </div>
            <div class="text-right">
                <p class="font-bold uppercase text-lg">{{ $pinjaman->user->name }}</p>
                <p class="text-xs mt-1">NRP {{ $pinjaman->user->nrp }}</p>
            </div>
        </div>

        <!-- Mengetahui Ketua Primkoppol -->
        <div class="mt-12 text-center">
            <p class="font-semibold">Mengetahui;</p>
            <p class="font-bold uppercase">Ketua Primkoppol</p>
            
            <!-- Spacer / Ruang Kosong Tanda Tangan Ketua -->
            <div class="h-24"></div>

            <p class="font-bold text-lg uppercase">I Made Sukadana</p>
            <p class="text-xs mt-1">NRP 79060072</p>
        </div>
    </div>
    <div class="h-24"></div>
    <div class="mt-14 text-center text-xs text-zinc-500 italic print:bottom-0 print:absolute print:w-full">
        * Surat ini dicetak secara otomatis dan sah sebagai bukti mutasi persetujuan pinjaman Koperasi.
    </div>
</div>