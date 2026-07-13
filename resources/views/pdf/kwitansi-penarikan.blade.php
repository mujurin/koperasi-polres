<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Kwitansi Penarikan Saldo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 15px;
            margin-bottom: 20px;
            position: relative;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
            text-transform: uppercase;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #555;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 25px;
            text-decoration: underline;
        }

        .content-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .content-table td {
            padding: 8px 5px;
            vertical-align: top;
        }

        .label-col {
            width: 30%;
            font-weight: bold;
        }

        .separator-col {
            width: 2%;
            text-align: center;
        }

        .value-col {
            width: 68%;
            border-bottom: 1px dotted #ccc;
        }

        .amount-box {
            display: inline-block;
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            padding: 10px 20px;
            font-size: 18px;
            font-weight: bold;
            margin-top: 25px;
        }

        .footer {
            margin-top: 40px;
            width: 100%;
        }

        .signature-table {
            width: 100%;
            text-align: center;
            font-size: 14px;
        }

        .signature-box {
            width: 50%;
            padding-top: 60px;
        }

        .info-small {
            font-size: 11px;
            color: #888;
            margin-top: 30px;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>PRIMKOPPOL LOTARA</h1>
        <p>Primer Koperasi Kepolisian Resort Lombok Utara</p>
    </div>

    <div class="title">
        BUKTI PENARIKAN SIMPANAN
    </div>

    @php
        function terbilang($angka)
        {
            $angka = abs($angka);
            $baca = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
            $terbilang = "";
            if ($angka < 12) {
                $terbilang = " " . $baca[$angka];
            } else if ($angka < 20) {
                $terbilang = terbilang($angka - 10) . " Belas";
            } else if ($angka < 100) {
                $terbilang = terbilang($angka / 10) . " Puluh" . terbilang($angka % 10);
            } else if ($angka < 200) {
                $terbilang = " Seratus" . terbilang($angka - 100);
            } else if ($angka < 1000) {
                $terbilang = terbilang($angka / 100) . " Ratus" . terbilang($angka % 100);
            } else if ($angka < 2000) {
                $terbilang = " Seribu" . terbilang($angka - 1000);
            } else if ($angka < 1000000) {
                $terbilang = terbilang($angka / 1000) . " Ribu" . terbilang($angka % 1000);
            } else if ($angka < 1000000000) {
                $terbilang = terbilang($angka / 1000000) . " Juta" . terbilang($angka % 1000000);
            }
            return $terbilang;
        }
    @endphp

    <table class="content-table">
        <tr>
            <td class="label-col">Telah terima dari</td>
            <td class="separator-col">:</td>
            <td class="value-col">Pengurus PRIMKOPPOL LOTARA</td>
        </tr>
        <tr>
            <td class="label-col">Uang Sejumlah</td>
            <td class="separator-col">:</td>
            <td class="value-col font-bold italic" style="background-color: #fafafa">
                {{ trim(terbilang($penarikan->jumlah)) }} Rupiah
            </td>
        </tr>
        <tr>
            <td class="label-col">Untuk Pembayaran</td>
            <td class="separator-col">:</td>
            <td class="value-col">
                Penarikan Saldo Simpanan Koperasi
                @if($penarikan->keterangan)
                    <br><span style="font-size:12px; color:#555;">(Catatan: {{ $penarikan->keterangan }})</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label-col">Nama Anggota</td>
            <td class="separator-col">:</td>
            <td class="value-col">{{ $penarikan->user->name }}</td>
        </tr>
        <tr>
            <td class="label-col">NRP</td>
            <td class="separator-col">:</td>
            <td class="value-col">{{ $penarikan->user->nrp }}</td>
        </tr>
    </table>

    <div class="amount-box">
        Terbilang: Rp {{ number_format($penarikan->jumlah, 0, ',', '.') }},-
    </div>

    <div class="footer">
        <table class="signature-table">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">Lombok Utara,
                    {{ \Carbon\Carbon::parse($penarikan->tanggal)->translatedFormat('d F Y') }}</td>
            </tr>
            <tr>
                <td class="signature-box">
                    ( _________________________ )<br>
                    <span style="font-size:12px; color:#555;">Admin / Bendahara</span>
                </td>
                <td class="signature-box">
                    ( <b style="text-decoration: underline;">{{ $penarikan->user->name }}</b> )<br>
                    <span style="font-size:12px; color:#555;">Anggota / Penerima</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-small">
        <p>Dokumen ini adalah bukti pembayaran yang sah dan dicetak secara otomatis oleh sistem Koperasi PRIMKOPPOL
            LOTARA. Transaksi dicetak pada {{ now()->format('d-m-Y H:i:s') }}</p>
    </div>

</body>

</html>