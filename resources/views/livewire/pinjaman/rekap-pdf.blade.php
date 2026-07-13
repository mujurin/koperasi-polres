<?php

use App\Models\Pinjaman;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.print')] class extends Component {
    public int $year;
    public string $filter = 'semua';

    public function mount()
    {
        $this->year = (int) request()->get('year', date('Y'));
        $this->filter = request()->get('filter', 'semua');
    }

    public function with(): array
    {
        $query = Pinjaman::with(['user', 'angsurans' => function ($q) {
            $q->where('status_pembayaran', 'lunas');
        }])->whereIn('status', ['disetujui', 'lunas']);

        $pinjamanList = $query->get();

        // Siapkan matriks data per pinjaman
        $rekapRows = [];
        $grandTotalPokok = 0;
        $grandTotalJasa = 0;
        $grandTotalBulan = array_fill(1, 12, ['pokok' => 0, 'jasa' => 0]);

        foreach ($pinjamanList as $item) {
            if (!$item->user) continue;

            $months = array_fill(1, 12, ['pokok' => 0, 'jasa' => 0, 'status' => '-']);
            $totalPokokRow = 0;
            $totalJasaRow = 0;

            foreach ($item->angsurans as $ang) {
                $tgl = Carbon::parse($ang->tanggal_bayar);
                if ($tgl->year == $this->year) {
                    $ajuan = $item->jumlah_ajuan ?? 0;
                    $jasa = min($ang->jumlah_bayar, $ajuan * 0.01);
                    $pokok = max(0, $ang->jumlah_bayar - $jasa);

                    $months[$tgl->month] = [
                        'pokok' => $pokok,
                        'jasa' => $jasa,
                        'status' => 'Lunas'
                    ];

                    $totalPokokRow += $pokok;
                    $totalJasaRow += $jasa;

                    $grandTotalBulan[$tgl->month]['pokok'] += $pokok;
                    $grandTotalBulan[$tgl->month]['jasa'] += $jasa;
                }
            }

            // Jika filter tahun_ini aktif dan tidak ada transaksi di tahun ini, lewati atau tetap tampilkan
            if ($totalPokokRow > 0 || $totalJasaRow > 0 || $this->filter === 'semua') {
                $rekapRows[] = [
                    'id' => $item->id,
                    'user_name' => $item->user->name,
                    'user_nrp' => $item->user->nrp,
                    'jumlah_ajuan' => $item->jumlah_ajuan,
                    'tenor' => $item->tenor,
                    'angsuran_perbulan' => $item->angsuran_perbulan,
                    'months' => $months,
                    'total_pokok' => $totalPokokRow,
                    'total_jasa' => $totalJasaRow,
                    'total_row' => $totalPokokRow + $totalJasaRow,
                ];

                $grandTotalPokok += $totalPokokRow;
                $grandTotalJasa += $totalJasaRow;
            }
        }

        return compact('rekapRows', 'grandTotalPokok', 'grandTotalJasa', 'grandTotalBulan');
    }
}; ?>

<div class="p-6 bg-white text-black min-h-screen font-sans" x-data="{
    init() {
        setTimeout(() => window.print(), 600);
    }
}">
    <style>
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        body {
            background: white !important;
            color: black !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        @media print {
            .no-print, .print\:hidden {
                display: none !important;
            }
        }
    </style>

    {{-- Kop Resmi Koperasi --}}
    <div class="border-b-2 border-black pb-4 mb-6 text-center">
        <h1 class="text-xl font-black uppercase tracking-wider text-black">KOPERASI POLRES LOMBOK UTARA (PRIMKOPPOL LOTARA)</h1>
        <p class="text-xs text-zinc-700 font-semibold">Jl. Raya Tanjung - Bayan, Kabupaten Lombok Utara, Nusa Tenggara Barat</p>
        <h2 class="text-base font-extrabold uppercase underline mt-2">REKAPITULASI ANGSURAN PINJAMAN ANGGOTA & PENDAPATAN JASA</h2>
        <p class="text-xs font-bold mt-0.5">Tahun Buku: {{ $year }} &bull; Status Filter: {{ strtoupper($filter) }}</p>
    </div>

    {{-- Table Matrix Rekap --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse border border-black text-[10px]">
            <thead>
                <tr class="bg-zinc-200 text-black font-bold text-center">
                    <th class="border border-black px-1.5 py-2 w-8">No</th>
                    <th class="border border-black px-2 py-2 text-left">Nama Anggota & NRP</th>
                    <th class="border border-black px-2 py-2 text-right">Total Pinjaman</th>
                    <th class="border border-black px-1 py-2">Tenor</th>
                    <th class="border border-black px-1 py-2">Jan</th>
                    <th class="border border-black px-1 py-2">Feb</th>
                    <th class="border border-black px-1 py-2">Mar</th>
                    <th class="border border-black px-1 py-2">Apr</th>
                    <th class="border border-black px-1 py-2">Mei</th>
                    <th class="border border-black px-1 py-2">Jun</th>
                    <th class="border border-black px-1 py-2">Jul</th>
                    <th class="border border-black px-1 py-2">Agt</th>
                    <th class="border border-black px-1 py-2">Sep</th>
                    <th class="border border-black px-1 py-2">Okt</th>
                    <th class="border border-black px-1 py-2">Nov</th>
                    <th class="border border-black px-1 py-2">Des</th>
                    <th class="border border-black px-2 py-2 text-right">Pokok Terbayar</th>
                    <th class="border border-black px-2 py-2 text-right">Jasa Terbayar</th>
                    <th class="border border-black px-2 py-2 text-right">Total Masuk</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rekapRows as $row)
                    <tr class="hover:bg-zinc-50 border border-black">
                        <td class="border border-black px-1.5 py-1.5 text-center font-bold">{{ $loop->iteration }}</td>
                        <td class="border border-black px-2 py-1.5">
                            <div class="font-bold text-black">{{ $row['user_name'] }}</div>
                            <div class="text-[9px] text-zinc-600 font-mono">NRP: {{ $row['user_nrp'] }}</div>
                        </td>
                        <td class="border border-black px-2 py-1.5 text-right font-mono font-semibold">
                            Rp {{ number_format($row['jumlah_ajuan'], 0, ',', '.') }}
                        </td>
                        <td class="border border-black px-1 py-1.5 text-center font-bold">{{ $row['tenor'] }}x</td>
                        
                        @for($m = 1; $m <= 12; $m++)
                            <td class="border border-black px-1 py-1.5 text-center {{ $row['months'][$m]['status'] === 'Lunas' ? 'bg-emerald-100/70 font-bold text-emerald-900' : 'text-zinc-300 font-mono' }}">
                                @if($row['months'][$m]['status'] === 'Lunas')
                                    ✓
                                @else
                                    -
                                @endif
                            </td>
                        @endfor

                        <td class="border border-black px-2 py-1.5 text-right font-mono font-semibold">
                            Rp {{ number_format($row['total_pokok'], 0, ',', '.') }}
                        </td>
                        <td class="border border-black px-2 py-1.5 text-right font-mono font-semibold">
                            Rp {{ number_format($row['total_jasa'], 0, ',', '.') }}
                        </td>
                        <td class="border border-black px-2 py-1.5 text-right font-mono font-bold bg-zinc-50">
                            Rp {{ number_format($row['total_row'], 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="19" class="border border-black py-8 text-center text-zinc-500 italic font-semibold">
                            Tidak ada data angsuran pinjaman pada tahun {{ $year }} untuk filter {{ $filter }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-zinc-200 border-2 border-black font-extrabold text-[10px] text-black">
                    <td colspan="4" class="border border-black px-3 py-2 text-right uppercase">Total Bulan:</td>
                    @for($m = 1; $m <= 12; $m++)
                        <td class="border border-black px-1 py-2 text-center font-mono">
                            {{ $grandTotalBulan[$m]['pokok'] + $grandTotalBulan[$m]['jasa'] > 0 ? '✓' : '-' }}
                        </td>
                    @endfor
                    <td class="border border-black px-2 py-2 text-right font-mono">Rp {{ number_format($grandTotalPokok, 0, ',', '.') }}</td>
                    <td class="border border-black px-2 py-2 text-right font-mono">Rp {{ number_format($grandTotalJasa, 0, ',', '.') }}</td>
                    <td class="border border-black px-2 py-2 text-right font-mono bg-zinc-300">Rp {{ number_format($grandTotalPokok + $grandTotalJasa, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Ringkasan Pendapatan & Sign-off Footer --}}
    <div class="mt-6 grid grid-cols-3 gap-6 items-start break-inside-avoid">
        <div class="col-span-1 border border-black p-3 rounded text-[11px] bg-zinc-50">
            <h3 class="font-bold uppercase border-b border-black pb-1 mb-2">Ringkasan Akuntansi {{ $year }}</h3>
            <div class="flex justify-between font-medium"><span>Total Pokok Masuk:</span><span class="font-mono font-bold">Rp {{ number_format($grandTotalPokok, 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-medium mt-1"><span>Pendapatan Jasa (1%):</span><span class="font-mono font-bold text-black">Rp {{ number_format($grandTotalJasa, 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-extrabold mt-1 pt-1 border-t border-black"><span>Total Kas Masuk:</span><span class="font-mono">Rp {{ number_format($grandTotalPokok + $grandTotalJasa, 0, ',', '.') }}</span></div>
        </div>

        <div class="col-span-1 text-center text-xs pt-2">
            <p class="font-semibold">Mengetahui,</p>
            <p class="font-bold">Ketua Primkoppol Lotara</p>
            <div class="h-16"></div>
            <p class="font-bold underline">( ............................................ )</p>
            <p class="text-[10px]">NRP. .................................</p>
        </div>

        <div class="col-span-1 text-center text-xs pt-2">
            <p class="font-semibold">Tanjung, {{ Carbon::now()->translatedFormat('d F Y') }}</p>
            <p class="font-bold">Bendahara Koperasi</p>
            <div class="h-16"></div>
            <p class="font-bold underline">( ............................................ )</p>
            <p class="text-[10px]">NRP. .................................</p>
        </div>
    </div>
</div>
