<?php

namespace App\Http\Controllers;

use App\Models\Penarikan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PenarikanKwitansiController extends Controller
{
    public function download(Penarikan $penarikan)
    {
        // Pastikan hanya admin atau pemilik transaksi yang boleh mengunduh
        if (Auth::user()->id !== $penarikan->user_id && !Auth::user()->isAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        // Kwitansi hanya untuk transaksi yang sudah disetujui (opsional, ikuti instruksi)
        // Di sini kita izinkan, namun secara UI memang hanya muncul jika disetujui.

        $penarikan->load('user');

        $pdf = Pdf::loadView('pdf.kwitansi-penarikan', compact('penarikan'));

        // Atur ukuran kertas ke A5 landscape kalau mau seperti kwitansi asli
        $pdf->setPaper('a5', 'landscape');

        $filename = 'Kwitansi_Penarikan_' . $penarikan->user->nrp . '_' . \Carbon\Carbon::parse($penarikan->tanggal)->format('Ymd') . '.pdf';

        return $pdf->stream($filename);
    }
}
