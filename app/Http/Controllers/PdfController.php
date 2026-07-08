<?php

namespace App\Http\Controllers;

use App\Models\Pinjaman;
use App\Models\Angsuran;
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
        ];

        $pdf = Pdf::loadView('pdf.cetak_pinjaman', $data);
        return $pdf->download("Surat-Persetujuan-Pinjaman-{$pinjaman->id}.pdf");
    }

    public function rekapPdf(Request $request)
    {
        $year = $request->query('year', date('Y'));
        $filter = $request->query('filter', 'semua');

        $activePinjamans = Pinjaman::whereIn('status', ['disetujui', 'lunas'])->with([
            'user',
            'angsurans' => function ($q) {
                $q->where('status_pembayaran', 'lunas');
            }
        ])->get();

        $totalPinjamanCair = 0;
        $totalPokok = 0;
        $totalJasa = 0;
        $totalTunggakanPokok = 0;
        $totalTunggakanJasa = 0;

        $rekapBulan = [];
        $totalPerBulan = array_fill(1, 12, ['pokok' => 0, 'jasa' => 0]);
        $now = Carbon::now();

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
                    'tunggakan_pokok' => 0,
                    'tunggakan_jasa' => 0,
                    'tunggakan_bulan' => 0,
                    'is_kompensasi' => false,
                    'kompensasi_month' => null,
                    'acc_month' => null,
                    'current_month' => Carbon::now()->month
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
                }
            }
        }

        $rekapBulan = array_filter($rekapBulan, function ($row) {
            return $row['total_pokok'] > 0 || $row['total_jasa'] > 0 || $row['tunggakan_bulan'] > 0;
        });

        usort($rekapBulan, function ($a, $b) {
            return strcmp($a['user']->name, $b['user']->name);
        });

        $data = compact('year', 'filter', 'totalPinjamanCair', 'totalPokok', 'totalJasa', 'rekapBulan', 'totalPerBulan', 'totalTunggakanPokok', 'totalTunggakanJasa');

        $pdf = Pdf::loadView('pdf.rekap_pinjaman', $data)->setPaper('a4', 'landscape');

        $filename = "Rekap_Pinjaman_{$year}";
        if ($filter !== 'tahun' && $filter !== 'semua') {
            $filename .= "_" . ucfirst($filter);
        }
        $filename .= ".pdf";

        return $pdf->download($filename);
    }
}
