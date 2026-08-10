<!DOCTYPE html>
<html lang="id" translate="no">

<head>
    <meta name="google" content="notranslate">
    <meta charset="UTF-8">
    <title>Rekap Simpanan Anggota</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
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

        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin: 20px 0 8px 0;
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
            text-align: left;
            background: #f3f4f6;
        }

        .text-right {
            text-align: right;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .summary-card {
            border: 1px solid #d1d5db;
            padding: 10px;
            border-radius: 8px;
            background: #fafafa;
        }

        .summary-card strong {
            display: block;
            margin-top: 6px;
            font-size: 15px;
        }

        .small {
            font-size: 11px;
            color: #6b7280;
        }

        .page-footer {
            margin-top: 12px;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Rekap Simpanan Anggota</h1>
        <p>Tanggal: {{ now()->format('d F Y') }}</p>
    </div>

    <div class="section-title">Data Anggota</div>
    <table>
        <tbody>
            <tr>
                <td style="width: 25%; font-weight: bold;">Nama</td>
                <td>{{ $user->name }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">NRP</td>
                <td>{{ $user->nrp }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Ringkasan Simpanan</div>
    <div class="summary-grid">
        <div class="summary-card">
            <span class="small">Total Simpanan Pokok</span>
            <strong>Rp {{ number_format($totalPokok, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-card">
            <span class="small">Total Simpanan Wajib</span>
            <strong>Rp {{ number_format($totalWajib, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-card">
            <span class="small">Total Penarikan Disetujui</span>
            <strong>Rp {{ number_format($totalPenarikan, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-card" style="grid-column: span 3;">
            <span class="small">Saldo Akhir</span>
            <strong>Rp {{ number_format($saldo, 0, ',', '.') }}</strong>
        </div>
    </div>

    <div class="section-title">Rincian Simpanan Wajib</div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">No</th>
                <th>Bulan</th>
                <th>Tahun</th>
                <th class="text-right">Jumlah</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($riwayatWajib as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ \App\Models\SimpananWajib::namaBulan($item->bulan) }}</td>
                    <td>{{ $item->tahun }}</td>
                    <td class="text-right">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                    <td>{{ $item->keterangan ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-right">Belum ada simpanan wajib.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Riwayat Penarikan</div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">No</th>
                <th>Tanggal</th>
                <th class="text-right">Jumlah</th>
                <th>Status</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($penarikan as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ optional($item->tanggal)->format('d/m/Y') }}</td>
                    <td class="text-right">Rp {{ number_format($item->jumlah, 0, ',', '.') }}</td>
                    <td>{{ ucfirst($item->status) }}</td>
                    <td>{{ $item->keterangan ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-right">Belum ada penarikan disetujui.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-footer">Dokumen otomatis dibuat oleh PRIMKOPPOL LOTARA.</div>
</body>

</html>
