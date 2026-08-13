<?php

namespace App\Http\Controllers;

use App\Models\Pinjaman;
use App\Models\Angsuran;
use App\Models\SimpananPokok;
use App\Models\SimpananWajib;
use App\Models\Penarikan;
use App\Models\TransaksiOperasional;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    public function cetakPdf($id)
    {
        $pinjaman = Pinjaman::with('user')->findOrFail($id);

        $jumlah = (float) $pinjaman->jumlah_ajuan;
        $tenor = (int) $pinjaman->tenor;
        $isKompensasi = false;

        $sisaPinjaman = 0;
        $pinaltiKompensasi = 0;
        $jasaTunggakan = 0;
        $tunggakanBulan = 0;
        $simulasiDiterima = 0;
        $simulasiAngsuran = 0;
        $simulasiBiaya = 0;
        $simulasiPokok = 0;
        $simulasiJasa = 0;

        if (str_contains(strtolower($pinjaman->keterangan), 'kompensasi')) {
            $isKompensasi = true;
            $pinjamanLama = \App\Models\Pinjaman::where('user_id', $pinjaman->user_id)
                ->where('id', '<', $pinjaman->id)
                ->latest()
                ->first();

            if ($pinjamanLama) {
                $totalTerbayar = \App\Models\Angsuran::where('pinjaman_id', $pinjamanLama->id)
                    ->where('status_pembayaran', 'Lunas')
                    ->where('angsuran_ke', '!=', 999)
                    ->sum('jumlah_bayar');

                $totalKewajiban = $pinjamanLama->angsuran_perbulan * $pinjamanLama->tenor;
                $sisaPinjaman = max(0, $totalKewajiban - $totalTerbayar);
                $pinaltiKompensasi = $pinjamanLama->jumlah_ajuan * 0.01;

                $bulanBerjalan = (\Carbon\Carbon::parse($pinjaman->created_at)->year - $pinjamanLama->updated_at->year) * 12
                    + (\Carbon\Carbon::parse($pinjaman->created_at)->month - $pinjamanLama->updated_at->month);
                $targetLunas = max(0, $bulanBerjalan);

                $bulanTerbayar = \App\Models\Angsuran::where('pinjaman_id', $pinjamanLama->id)
                    ->where('status_pembayaran', 'Lunas')
                    ->where('angsuran_ke', '!=', 999)
                    ->count();

                $tunggakanBulan = max(0, $targetLunas - $bulanTerbayar);

                if ($tunggakanBulan > 0) {
                    $jasaPersenLama = $pinjamanLama->jasa_persen ?? 1;
                    $jasaPerbulanLama = $pinjamanLama->jumlah_ajuan * ($jasaPersenLama / 100);
                    $jasaTunggakan = $tunggakanBulan * $jasaPerbulanLama;
                }

                $simulasiDiterima = $pinjaman->jumlah_diterima;
                $simulasiAngsuran = $pinjaman->angsuran_perbulan;
                $simulasiPokok = $jumlah / $tenor;
                $simulasiBiaya = $jumlah * 0.01;
                $simulasiJasa = $jumlah * 0.01;
            }
        } else {
            $simulasiBiaya = $jumlah * 0.01;
            $simulasiDiterima = $jumlah - $simulasiBiaya;
            $simulasiPokok = $jumlah / $tenor;
            $simulasiJasa = $jumlah * 0.01;
            $simulasiAngsuran = $simulasiPokok + $simulasiJasa;
        }

        $approvalDate = Carbon::parse($pinjaman->updated_at ?? $pinjaman->created_at)->startOfMonth();
        $paidMonths = min($pinjaman->tenor, max(0, $approvalDate->diffInMonths(Carbon::now()) + 1));

        $monthlySchedule = [];
        for ($i = 0; $i < $paidMonths; $i++) {
            $monthlySchedule[] = [
                'label' => $approvalDate->copy()->addMonths($i)->translatedFormat('M Y'),
                'amount' => $pinjaman->angsuran_perbulan,
            ];
        }

        $totalPokokMasuk = round($simulasiPokok * $paidMonths);
        $totalJasaMasuk = round($simulasiJasa * $paidMonths);
        $sisaPokok = max(0, $pinjaman->jumlah_ajuan - $totalPokokMasuk);
        $sisaJasa = max(0, ($simulasiJasa * $pinjaman->tenor) - $totalJasaMasuk);
        $sisaPinjamanBalance = $sisaPokok + $sisaJasa;

        $data = [
            'pinjaman' => $pinjaman,
            'isKompensasi' => $isKompensasi,
            'simulasiDiterima' => $simulasiDiterima,
            'simulasiAngsuran' => $simulasiAngsuran,
            'simulasiBiaya' => $simulasiBiaya,
            'simulasiPokok' => $simulasiPokok,
            'simulasiJasa' => $simulasiJasa,
            'sisaPinjaman' => $sisaPinjaman,
            'pinaltiKompensasi' => $pinaltiKompensasi,
            'jasaTunggakan' => $jasaTunggakan,
            'tunggakanBulan' => $tunggakanBulan,
            'monthlySchedule' => $monthlySchedule,
            'totalPokokMasuk' => $totalPokokMasuk,
            'totalJasaMasuk' => $totalJasaMasuk,
            'sisaPokok' => $sisaPokok,
            'sisaJasa' => $sisaJasa,
            'sisaPinjamanBalance' => $sisaPinjamanBalance,
        ];

        $pdf = Pdf::loadView('pdf.cetak_pinjaman', $data);
        return $pdf->download("Surat-Riwayat-Pinjaman-{$pinjaman->id}.pdf");
    }

    public function rekapPdf(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

        $year = $request->query('year', date('Y'));
        $filter = $request->query('filter', 'semua');
        $type = $request->query('type', 'reguler');

        $query = Pinjaman::whereIn('status', ['disetujui', 'lunas'])->with([
            'user',
            'angsurans' => function ($q) {
                $q->where('status_pembayaran', 'lunas');
            }
        ]);

        if ($type === 'primer') {
            $query->whereIn('jenis_permohonan', ['Handphone', 'Motor', 'Barang Lain']);
        } else {
            $query->whereNotIn('jenis_permohonan', ['Handphone', 'Motor', 'Barang Lain']);
        }

        $activePinjamans = $query->get();

        $totalPinjamanCair = 0;
        $totalPokok = 0;
        $totalJasa = 0;
        $totalTunggakanPokok = 0;
        $totalTunggakanJasa = 0;
        $totalPastPokok = 0;
        $totalPastJasa = 0;
        $matrixTotalPokok = 0;
        $matrixTotalJasa = 0;

        $rekapBulan = [];
        $totalPerBulan = array_fill(1, 12, ['pokok' => 0, 'jasa' => 0]);
        $now = Carbon::now();
        $selectedYear = (int) $year;

        if ($selectedYear < $now->year) {
            $effectiveCurrentMonth = 12;
        } elseif ($selectedYear == $now->year) {
            $effectiveCurrentMonth = $now->month;
        } else {
            $effectiveCurrentMonth = 0;
        }

        foreach ($activePinjamans as $pinjaman) {
            $user = $pinjaman->user;
            if (!$user)
                continue;

            $userId = $user->id;

            if (!isset($rekapBulan[$userId])) {
                $rekapBulan[$userId] = [
                    'user' => $user,
                    'months' => array_fill(1, 12, ['pokok' => 0, 'jasa' => 0]),
                    'total_pokok' => 0,
                    'total_jasa' => 0,
                    'past_pokok' => 0,
                    'past_jasa' => 0,
                    'tunggakan_pokok' => 0,
                    'tunggakan_jasa' => 0,
                    'tunggakan_bulan' => 0,
                    'is_kompensasi' => false,
                    'kompensasi_month' => null,
                    'acc_month' => null,
                    'max_valid_month' => 12,
                    'current_month' => $effectiveCurrentMonth
                ];
            }

            if (str_contains(strtolower($pinjaman->keterangan ?? ''), 'kompensasi')) {
                $rekapBulan[$userId]['is_kompensasi'] = true;
                $rekapBulan[$userId]['kompensasi_month'] = Carbon::parse($pinjaman->updated_at)->month;
            }

            if ($pinjaman->status === 'disetujui' && $pinjaman->updated_at) {
                $pencairanDate = Carbon::parse($pinjaman->updated_at)->startOfMonth();
                $currentDate = $now->copy()->startOfMonth();

                if ($pencairanDate->year < $year) {
                    $rekapBulan[$userId]['acc_month'] = 0;
                } elseif ($pencairanDate->year == $year) {
                    $rekapBulan[$userId]['acc_month'] = $pencairanDate->month;
                } else {
                    $rekapBulan[$userId]['acc_month'] = 12; // future loan in selected year
                }

                $endDate = $pencairanDate->copy()->addMonths((int) $pinjaman->tenor);
                if ($endDate->year < $year) {
                    $rekapBulan[$userId]['max_valid_month'] = 0;
                } elseif ($endDate->year == $year) {
                    $rekapBulan[$userId]['max_valid_month'] = $endDate->month;
                } else {
                    $rekapBulan[$userId]['max_valid_month'] = 12;
                }

                $expectedMonths = $pencairanDate->diffInMonths($currentDate);
                $expectedMonths = min($expectedMonths, $pinjaman->tenor);
                $lunasCount = $pinjaman->angsurans->count();

                if ($expectedMonths > $lunasCount) {
                    $missedMonths = $expectedMonths - $lunasCount;
                    $pokokBulan = $pinjaman->jumlah_ajuan / $pinjaman->tenor;
                    $jasaBulan = $pinjaman->jumlah_ajuan * 0.01;

                    $rekapBulan[$userId]['tunggakan_bulan'] += $missedMonths;
                    $rekapBulan[$userId]['tunggakan_pokok'] += ($missedMonths * $pokokBulan);
                    $rekapBulan[$userId]['tunggakan_jasa'] += ($missedMonths * $jasaBulan);

                    $totalTunggakanPokok += ($missedMonths * $pokokBulan);
                    $totalTunggakanJasa += ($missedMonths * $jasaBulan);
                }
            }

            $includePinjaman = false;
            if ($filter === 'semua') {
                $includePinjaman = true;
            } elseif ($filter === 'tahun' && Carbon::parse($pinjaman->updated_at)->year == $year) {
                $includePinjaman = true;
            } elseif ($filter === 'bulan' && Carbon::parse($pinjaman->updated_at)->isCurrentMonth()) {
                $includePinjaman = true;
            } elseif ($filter === 'minggu' && Carbon::parse($pinjaman->updated_at)->isCurrentWeek()) {
                $includePinjaman = true;
            }

            if ($includePinjaman) {
                $totalPinjamanCair += $pinjaman->jumlah_ajuan;
            }

            foreach ($pinjaman->angsurans as $angsuran) {
                $tglBayar = Carbon::parse($angsuran->tanggal_bayar);

                $includeAngsuranCard = false;
                if ($filter === 'semua') {
                    $includeAngsuranCard = true;
                } elseif ($filter === 'tahun' && $tglBayar->year == $year) {
                    $includeAngsuranCard = true;
                } elseif ($filter === 'bulan' && $tglBayar->isCurrentMonth()) {
                    $includeAngsuranCard = true;
                } elseif ($filter === 'minggu' && $tglBayar->isCurrentWeek()) {
                    $includeAngsuranCard = true;
                }

                $ajuan = $pinjaman->jumlah_ajuan ?? 0;
                $jasa = min($angsuran->jumlah_bayar, $ajuan * 0.01);
                $pokok = max(0, $angsuran->jumlah_bayar - $jasa);

                if ($includeAngsuranCard) {
                    $totalPokok += $pokok;
                    $totalJasa += $jasa;
                }

                if ($tglBayar->year == $year) {
                    $month = $tglBayar->month;

                    $rekapBulan[$userId]['months'][$month]['pokok'] += $pokok;
                    $rekapBulan[$userId]['months'][$month]['jasa'] += $jasa;
                    $rekapBulan[$userId]['total_pokok'] += $pokok;
                    $rekapBulan[$userId]['total_jasa'] += $jasa;

                    $totalPerBulan[$month]['pokok'] += $pokok;
                    $totalPerBulan[$month]['jasa'] += $jasa;
                    
                    $matrixTotalPokok += $pokok;
                    $matrixTotalJasa += $jasa;
                } elseif ($tglBayar->year < $year) {
                    $rekapBulan[$userId]['past_pokok'] += $pokok;
                    $rekapBulan[$userId]['past_jasa'] += $jasa;
                    $rekapBulan[$userId]['total_pokok'] += $pokok;
                    $rekapBulan[$userId]['total_jasa'] += $jasa;

                    $totalPastPokok += $pokok;
                    $totalPastJasa += $jasa;
                    
                    $matrixTotalPokok += $pokok;
                    $matrixTotalJasa += $jasa;
                }
            }
        }

        $rekapBulan = array_filter($rekapBulan, function ($row) {
            return $row['total_pokok'] > 0 || $row['total_jasa'] > 0 || $row['tunggakan_bulan'] > 0;
        });

        usort($rekapBulan, function ($a, $b) {
            return strcmp($a['user']->name, $b['user']->name);
        });

        $data = compact('year', 'filter', 'type', 'totalPinjamanCair', 'totalPokok', 'totalJasa', 'rekapBulan', 'totalPerBulan', 'totalTunggakanPokok', 'totalTunggakanJasa', 'totalPastPokok', 'totalPastJasa', 'matrixTotalPokok', 'matrixTotalJasa');

        $pdf = Pdf::loadView('pdf.rekap_pinjaman', $data)->setPaper('a4', 'landscape');

        $filename = "Rekap_Pinjaman_" . ucfirst($type) . "_{$year}";
        if ($filter !== 'tahun' && $filter !== 'semua') {
            $filename .= "_" . ucfirst($filter);
        }
        $filename .= ".pdf";

        return $pdf->download($filename);
    }

    public function bukuKasPdf(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

        $year = (int) $request->query('year', date('Y'));
        $month = $request->query('month', 'semua');
        $monthNumber = $month !== 'semua' ? (int) $month : null;

        $entries = [];
        $makeEntry = function (string $tanggal, string $uraian, float $pemasukan, float $pengeluaran, int $priority, int $period = 0, string $detail = '') {
            return [
                'tanggal' => $tanggal,
                'uraian' => $uraian,
                'pemasukan' => $pemasukan,
                'pengeluaran' => $pengeluaran,
                'priority' => $priority,
                'period' => $period,
                'detail' => $detail,
            ];
        };
        $queryYear = fn ($query, $column) => $query->when($year, fn ($q) => $q->whereYear($column, $year));
        $queryMonth = fn ($query, $column) => $query->when($monthNumber, fn ($q) => $q->whereMonth($column, $monthNumber));

        $simpananPokok = $queryYear(SimpananPokok::query(), 'tanggal');
        if ($monthNumber) {
            $simpananPokok = $queryMonth($simpananPokok, 'tanggal');
        }
        foreach ($simpananPokok->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Simpanan Pokok dari ' . optional($item->user)->name,
                (float) $item->jumlah,
                0,
                2,
                0,
                optional($item->user)->name
            );
        }

        $simpananWajib = SimpananWajib::query();
        if ($year) {
            $simpananWajib->whereYear('created_at', $year);
        }
        if ($monthNumber) {
            $simpananWajib->whereMonth('created_at', $monthNumber);
        }
        foreach ($simpananWajib->orderBy('created_at')->get() as $item) {
            $date = $item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d') : date('Y-m-d');
            $period = ($item->tahun * 100) + $item->bulan;
            $entries[] = $makeEntry(
                $date,
                'Simpanan Wajib ' . $item->bulan . '/' . $item->tahun . ' oleh ' . optional($item->user)->name,
                (float) $item->jumlah,
                0,
                3,
                $period,
                optional($item->user)->name
            );
        }

        $angsurans = Angsuran::with('pinjaman.user')->where('status_pembayaran', 'lunas');
        if ($year) {
            $angsurans->whereYear('tanggal_bayar', $year);
        }
        if ($monthNumber) {
            $angsurans->whereMonth('tanggal_bayar', $monthNumber);
        }
        foreach ($angsurans->orderBy('tanggal_bayar')->get() as $item) {
            $entries[] = $makeEntry(
                Carbon::parse($item->tanggal_bayar)->format('Y-m-d'),
                'Pembayaran Angsuran oleh ' . optional($item->pinjaman->user)->name,
                (float) $item->jumlah_bayar,
                0,
                1,
                Carbon::parse($item->tanggal_bayar)->format('Ymd'),
                optional($item->pinjaman->user)->name
            );
        }

        $pendapatanLain = TransaksiOperasional::where('jenis', 'pendapatan_lain');
        if ($year) {
            $pendapatanLain->whereYear('tanggal', $year);
        }
        if ($monthNumber) {
            $pendapatanLain->whereMonth('tanggal', $monthNumber);
        }
        foreach ($pendapatanLain->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Pendapatan: ' . $item->kategori,
                (float) $item->nominal,
                0,
                4,
                Carbon::parse($item->tanggal)->format('Ymd'),
                $item->kategori
            );
        }

        $pinjamanDisetujui = Pinjaman::whereIn('status', ['disetujui', 'lunas']);
        if ($year) {
            $pinjamanDisetujui->whereYear('updated_at', $year);
        }
        if ($monthNumber) {
            $pinjamanDisetujui->whereMonth('updated_at', $monthNumber);
        }
        foreach ($pinjamanDisetujui->orderBy('updated_at')->get() as $item) {
            $entries[] = $makeEntry(
                Carbon::parse($item->updated_at)->format('Y-m-d'),
                'Pencairan Pinjaman untuk ' . optional($item->user)->name,
                0,
                (float) $item->jumlah_diterima,
                5,
                Carbon::parse($item->updated_at)->format('Ymd'),
                optional($item->user)->name
            );
        }

        $penarikan = Penarikan::where('status', 'disetujui');
        if ($year) {
            $penarikan->whereYear('tanggal', $year);
        }
        if ($monthNumber) {
            $penarikan->whereMonth('tanggal', $monthNumber);
        }
        foreach ($penarikan->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Penarikan oleh ' . optional($item->user)->name,
                0,
                (float) $item->jumlah,
                6,
                Carbon::parse($item->tanggal)->format('Ymd'),
                optional($item->user)->name
            );
        }

        $bebanOperasional = TransaksiOperasional::where('jenis', 'beban');
        if ($year) {
            $bebanOperasional->whereYear('tanggal', $year);
        }
        if ($monthNumber) {
            $bebanOperasional->whereMonth('tanggal', $monthNumber);
        }
        foreach ($bebanOperasional->orderBy('tanggal')->get() as $item) {
            $entries[] = $makeEntry(
                $item->tanggal->format('Y-m-d'),
                'Beban: ' . $item->kategori,
                0,
                (float) $item->nominal,
                7,
                Carbon::parse($item->tanggal)->format('Ymd'),
                $item->kategori
            );
        }

        usort($entries, function ($a, $b) {
            if ($a['tanggal'] !== $b['tanggal']) {
                return $a['tanggal'] < $b['tanggal'] ? -1 : 1;
            }
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] < $b['priority'] ? -1 : 1;
            }
            if ($a['period'] !== $b['period']) {
                return $a['period'] < $b['period'] ? -1 : 1;
            }
            return strcmp($a['uraian'] . ' ' . $a['detail'], $b['uraian'] . ' ' . $b['detail']);
        });

        $runningBalance = 0;
        foreach ($entries as $index => $entry) {
            $runningBalance += $entry['pemasukan'] - $entry['pengeluaran'];
            $entries[$index]['saldo'] = $runningBalance;
        }

        $totalPemasukan = array_sum(array_column($entries, 'pemasukan'));
        $totalPengeluaran = array_sum(array_column($entries, 'pengeluaran'));
        $saldoAkhir = $runningBalance;

        $bulanLabels = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $monthLabel = $monthNumber ? ($bulanLabels[$monthNumber] ?? 'Unknown') : 'Semua Bulan';
        $filename = 'Buku_Kas_' . $year;
        if ($monthNumber) {
            $filename .= '_' . $monthLabel;
        }
        $filename .= '.pdf';

        $pdf = Pdf::loadView('pdf.buku_kas', compact(
            'entries',
            'year',
            'monthLabel',
            'totalPemasukan',
            'totalPengeluaran',
            'saldoAkhir'
        ))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function rekapSimpanan()
    {
        $user = auth()->user();
        $user->load(['simpananPokok', 'simpananWajib', 'penarikan']);

        $totalPokok = $user->simpananPokok?->jumlah ?? 0;
        $totalWajib = $user->simpananWajib->sum('jumlah');
        $totalPenarikan = $user->penarikan()->where('status', 'disetujui')->sum('jumlah');
        $saldo = $user->totalSimpanan() - $totalPenarikan;

        $riwayatWajib = $user->simpananWajib()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();

        $penarikan = $user->penarikan()
            ->where('status', 'disetujui')
            ->orderByDesc('tanggal')
            ->get();

        $filename = 'Rekap_Simpanan_' . $user->nrp . '_' . now()->format('Ymd') . '.pdf';

        $pdf = Pdf::loadView('pdf.rekap_simpanan', compact(
            'user',
            'totalPokok',
            'totalWajib',
            'totalPenarikan',
            'saldo',
            'riwayatWajib',
            'penarikan'
        ));

        return $pdf->download($filename);
    }
}
