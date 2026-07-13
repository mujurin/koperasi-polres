<!DOCTYPE html>
<html lang="id" translate="no">

<head>
    <meta name="google" content="notranslate">
    <meta charset="UTF-8">
    <title>Surat Persetujuan Pinjaman</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 24px;
            text-transform: uppercase;
            margin: 0 0 5px 0;
            letter-spacing: 1px;
        }

        .header h2 {
            font-size: 18px;
            margin: 0 0 5px 0;
        }

        .header p {
            font-size: 12px;
            margin: 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        td {
            padding: 8px 5px;
            vertical-align: top;
        }

        .label {
            width: 35%;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .amount-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }

        .amount-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .text-rose {
            color: #e11d48;
        }

        .text-orange {
            color: #ea580c;
        }

        .text-indigo {
            color: #4f46e5;
        }

        .total-row td {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 16px;
            padding-top: 15px;
        }

        .bg-gray td {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .signature-table {
            width: 100%;
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 50%;
            text-align: center;
        }

        .signature-space {
            height: 80px;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2px;
        }

        .signature-nrp {
            font-size: 12px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            font-style: italic;
            position: absolute;
            bottom: 30px;
            width: 100%;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Surat Persetujuan Pinjaman</h1>
        <h2>Koperasi Polres Lombok Utara</h2>
        <p>Tanggal Berlaku: {{ now()->format('d F Y') }}</p>
    </div>

    <div class="section-title">Data Pemohon</div>
    <table>
        <tr>
            <td class="label">Nama Lengkap</td>
            <td>: <strong>{{ $pinjaman->user->name }}</strong></td>
        </tr>
        <tr>
            <td class="label">NRP</td>
            <td>: {{ $pinjaman->user->nrp }}</td>
        </tr>
        <tr>
            <td class="label">No. Pinjaman</td>
            <td>: PNK-{{ str_pad($pinjaman->id, 5, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td class="label">Tipe Pengajuan</td>
            <td style="font-weight: bold; text-transform: uppercase;"
                class="{{ $isKompensasi ? 'text-orange' : 'text-indigo' }}">
                : {{ $isKompensasi ? 'Kompensasi' : 'Pengajuan Baru' }}
            </td>
        </tr>
        <tr>
            <td class="label">Tanggal Persetujuan</td>
            <td>: {{ $pinjaman->updated_at->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="section-title">Rincian Persetujuan</div>
    <div class="amount-box">
        <div class="amount-title">Total Pinjaman: Rp {{ number_format($pinjaman->jumlah_ajuan, 0, ',', '.') }}</div>

        <table>
            @if($isKompensasi)
                <tr>
                    <td class="label">Sisa Pokok Hutang</td>
                    <td class="text-right text-rose font-bold">- Rp {{ number_format($sisaPinjaman, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="label">Jasa Pinalti (1x)</td>
                    <td class="text-right text-rose font-bold">- Rp {{ number_format($pinaltiKompensasi, 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Potongan Administrasi (1%)</td>
                    <td class="text-right text-rose font-bold">- Rp {{ number_format($simulasiBiaya, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="label">Jasa Tunggakan ({{ $tunggakanBulan }} Bulan)</td>
                    <td class="text-right text-rose font-bold">- Rp {{ number_format($jasaTunggakan, 0, ',', '.') }}</td>
                </tr>
            @else
                <tr>
                    <td class="label">Potongan Administrasi (1%)</td>
                    <td class="text-right text-rose font-bold">- Rp {{ number_format($simulasiBiaya, 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label">Total Bersih Diterima</td>
                <td class="text-right">Rp {{ number_format($simulasiDiterima, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Skema Kewajiban Cicilan</div>
    <table>
        <tr>
            <td class="label">Tenor Cicilan</td>
            <td>: <strong>{{ $pinjaman->tenor }} Bulan</strong></td>
        </tr>
        <tr>
            <td class="label">Jasa Pinjaman (1%) / Bln</td>
            <td>: Rp {{ number_format($simulasiJasa, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Pokok Angsuran / Bln</td>
            <td>: Rp {{ number_format($simulasiPokok, 0, ',', '.') }}</td>
        </tr>
        <tr class="bg-gray">
            <td class="label" style="text-transform: uppercase;">Angsuran Bulanan</td>
            <td>: Rp {{ number_format($simulasiAngsuran, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <p>Tanda Tangan Pemohon,</p>
                <div class="signature-space"></div>
                <p class="signature-name">({{ $pinjaman->user->name }})</p>
                <p class="signature-nrp">NRP: {{ $pinjaman->user->nrp }}</p>
            </td>
            <td>
                <p>Disetujui Oleh,</p>
                <div class="signature-space"></div>
                <p class="signature-name">(Pengurus Koperasi Polres)</p>
                <p class="signature-nrp">Cap & Tanda Tangan</p>
            </td>
        </tr>
    </table>

    <div class="footer">
        * Surat ini dicetak secara otomatis dan sah sebagai bukti mutasi persetujuan pinjaman Koperasi Polres Lombok
        Utara.
    </div>

</body>

</html>