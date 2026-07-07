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
    }
}; ?>

<div class="h-full w-full bg-white text-zinc-900" x-data="{
    init() {
        setTimeout(() => window.print(), 500);
    }
}">
    <!-- Header -->
    <div class="text-center pb-8 border-b-2 border-zinc-900 mb-8">
        <h1 class="text-3xl font-black uppercase tracking-wider mb-2">Surat Persetujuan Pinjaman</h1>
        <h2 class="text-lg font-bold">Koperasi Polres Lombok Utara</h2>
        <p class="text-sm">Tanggal Berlaku: {{ now()->format('d F Y') }}</p>
    </div>

    <!-- Identitas Pemohon -->
    <div class="mb-10">
        <h3 class="font-bold text-lg border-b border-zinc-300 pb-2 mb-4 uppercase tracking-wide">Data Pemohon</h3>
        <table class="w-full text-sm">
            <tbody>
                <tr>
                    <td class="py-2 w-1/3 font-semibold">Nama Lengkap</td>
                    <td class="py-2 w-2/3">: <span class="font-bold">{{ $pinjaman->user->name }}</span></td>
                </tr>
                <tr>
                    <td class="py-2 font-semibold">NRP</td>
                    <td class="py-2">: {{ $pinjaman->user->nrp }}</td>
                </tr>
                <tr>
                    <td class="py-2 font-semibold">No. Pinjaman</td>
                    <td class="py-2">: PNK-{{ str_pad($pinjaman->id, 5, '0', STR_PAD_LEFT) }}</td>
                </tr>
                <tr>
                    <td class="py-2 font-semibold">Tipe Pengajuan</td>
                    <td class="py-2">: <span
                            class="font-extrabold uppercase {{ $isKompensasi ? 'text-orange-600' : 'text-indigo-600' }}">{{ $isKompensasi ? 'Kompensasi' : 'Pengajuan Baru' }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="py-2 font-semibold">Tanggal Persetujuan</td>
                    <td class="py-2">: {{ $pinjaman->updated_at->format('d/m/Y H:i') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Rincian Pinjaman -->
    <div class="mb-10">
        <h3 class="font-bold text-lg border-b border-zinc-300 pb-2 mb-4 uppercase tracking-wide">Rincian Persetujuan
        </h3>

        <div class="bg-zinc-50 p-6 rounded-lg border border-zinc-200">
            <h4 class="font-extrabold text-xl text-center mb-6">Total Pinjaman: Rp
                {{ number_format($pinjaman->jumlah_ajuan, 0, ',', '.') }}
            </h4>

            <table class="w-full text-sm">
                <tbody>
                    @if($isKompensasi)
                        <tr>
                            <td class="py-3 font-semibold">Sisa Pokok Hutang</td>
                            <td class="py-3 text-right text-rose-600 font-semibold">- Rp
                                {{ number_format($sisaPinjaman, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 font-semibold">Jasa Pinalti (1x)</td>
                            <td class="py-3 text-right text-rose-600 font-semibold">- Rp
                                {{ number_format($pinaltiKompensasi, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 font-semibold">Potongan Administrasi (1%)</td>
                            <td class="py-3 text-right text-rose-600 font-semibold">- Rp
                                {{ number_format($simulasiBiaya, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 font-semibold">Jasa Tunggakan ({{ $tunggakanBulan }} Bulan)</td>
                            <td class="py-3 text-right text-rose-600 font-semibold">- Rp
                                {{ number_format($jasaTunggakan, 0, ',', '.') }}
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td class="py-3 font-semibold">Potongan Administrasi (1%)</td>
                            <td class="py-3 text-right text-rose-600 font-semibold">- Rp
                                {{ number_format($simulasiBiaya, 0, ',', '.') }}
                            </td>
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

    <!-- Skema Angsuran -->
    <div class="mb-12">
        <h3 class="font-bold text-lg border-b border-zinc-300 pb-2 mb-4 uppercase tracking-wide">Skema Kewajiban Cicilan
        </h3>
        <table class="w-full text-sm">
            <tbody>
                <tr>
                    <td class="py-2 w-1/3 font-semibold">Tenor Cicilan</td>
                    <td class="py-2 w-2/3">: <span class="font-bold">{{ $pinjaman->tenor }} Bulan</span></td>
                </tr>
                <tr>
                    <td class="py-2 font-semibold">Jasa Pinjaman (1%) / Bln</td>
                    <td class="py-2">: Rp {{ number_format($simulasiJasa, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="py-2 font-semibold">Pokok Angsuran / Bln</td>
                    <td class="py-2">: Rp {{ number_format($simulasiPokok, 0, ',', '.') }}</td>
                </tr>
                <tr class="bg-zinc-100 font-bold text-base">
                    <td class="py-4 px-2 font-bold uppercase tracking-wider">Angsuran Bulanan</td>
                    <td class="py-4 px-2">: Rp {{ number_format($pinjaman->angsuran_perbulan, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Tanda Tangan -->
    <div class="mt-16 pt-8 break-inside-avoid">
        <div class="flex justify-between items-end">
            <div class="text-center">
                <p class="font-semibold">Tanda Tangan Pemohon,</p>
                <div class="h-28"></div>
                <p class="font-bold border-b border-zinc-900 pb-1">({{ $pinjaman->user->name }})</p>
                <p class="text-xs mt-1">NRP: {{ $pinjaman->user->nrp }}</p>
            </div>
            <div class="text-center">
                <p class="font-semibold">Disetujui Oleh,</p>
                <div class="h-28"></div>
                <p class="font-bold border-b border-zinc-900 pb-1">(Pengurus Koperasi Polres)</p>
                <p class="text-xs mt-1">Cap & Tanda Tangan</p>
            </div>
        </div>
    </div>

    <div class="mt-16 text-center text-xs text-zinc-500 italic print:bottom-0 print:absolute print:w-full">
        * Surat ini dicetak secara otomatis dan sah sebagai bukti mutasi persetujuan pinjaman Koperasi Polres Lombok
        Utara.
    </div>

    <div class="mt-8 text-center print:hidden">
        <button onclick="window.print()"
            class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg mr-2 hover:bg-indigo-700">Cetak PDF</button>
        <a wire:navigate href="{{ route('pinjaman.antrian') }}"
            class="px-6 py-2 bg-zinc-200 text-zinc-800 font-bold rounded-lg hover:bg-zinc-300">Kembali</a>
    </div>
</div>