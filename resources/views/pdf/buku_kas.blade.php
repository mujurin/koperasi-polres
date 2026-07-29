<!DOCTYPE html>
<html lang="id" translate="no">

<head>
    <meta name="google" content="notranslate">
    <meta charset="UTF-8">
    <title>Buku Kas Koperasi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 18px;
            margin: 0;
            text-transform: uppercase;
        }

        .header p {
            margin: 4px 0 0;
            font-size: 11px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }

        .summary-card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fafafa;
            padding: 10px;
        }

        .summary-card strong {
            display: block;
            margin-top: 6px;
            font-size: 14px;
        }

        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin: 18px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .small {
            font-size: 11px;
            color: #6b7280;
        }

        .page-footer {
            margin-top: 10px;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Buku Kas Koperasi</h1>
        <p>Periode: {{ $monthLabel }} {{ $year }}</p>
        <p>Dicetak: {{ now()->format('d F Y') }}</p>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <span class="small">Total Pemasukan</span>
            <strong>Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-card">
            <span class="small">Total Pengeluaran</span>
            <strong>Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-card">
            <span class="small">Saldo Akhir</span>
            <strong>Rp {{ number_format($saldoAkhir, 0, ',', '.') }}</strong>
        </div>
    </div>

    <div class="section-title">Detail Buku Kas</div>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Tanggal</th>
                <th>Uraian</th>
                <th class="text-right" style="width: 18%;">Pemasukan</th>
                <th class="text-right" style="width: 18%;">Pengeluaran</th>
                <th class="text-right" style="width: 18%;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $index => $entry)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $entry['tanggal'] }}</td>
                    <td>{{ $entry['uraian'] }}</td>
                    <td class="text-right">{{ $entry['pemasukan'] > 0 ? 'Rp ' . number_format($entry['pemasukan'], 0, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ $entry['pengeluaran'] > 0 ? 'Rp ' . number_format($entry['pengeluaran'], 0, ',', '.') : '-' }}</td>
                    <td class="text-right">Rp {{ number_format($entry['saldo'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data untuk periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-footer">Dokumen otomatis dibuat oleh PRIMKOPPOL LOTARA.</div>
</body>

</html>
