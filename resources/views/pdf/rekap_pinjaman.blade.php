<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta name="google" content="notranslate">
    <meta charset="UTF-8">
    <title>Rekap Pinjaman Koperasi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            text-transform: uppercase;
            margin: 0 0 5px 0;
        }
        .header h2 {
            font-size: 14px;
            margin: 0 0 5px 0;
            font-weight: normal;
        }
        .summary-boxes {
            width: 100%;
            margin-bottom: 20px;
        }
        .summary-boxes td {
            width: 33.33%;
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
            background: #f9fafb;
        }
        .summary-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #4b5563;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
        }
        table.matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 20px;
        }
        table.matrix th, table.matrix td {
            border: 1px solid #999;
            padding: 4px;
            vertical-align: middle;
        }
        table.matrix th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
            border-bottom: 2px solid #666;
        }
        table.matrix td.name-col {
            font-weight: bold;
            text-align: left;
            width: 140px;
        }
        table.matrix td.total-col {
            background-color: #f3f4f6;
            text-align: right;
            width: 80px;
        }
        .name-detail {
            font-weight: normal;
            font-size: 8px;
            color: #666;
            margin-top: 2px;
        }
        .cell-content {
            text-align: right;
        }
        .val-row {
            display: block;
            margin-bottom: 1px;
        }
        .lbl-p { color: #166534; font-weight: bold; float: left;}
        .lbl-j { color: #1d4ed8; font-weight: bold; float: left;}
        .lbl-tgk { color: #b91c1c; font-weight: bold; float: left;}
        
        .val-p { color: #111827; }
        .val-j { color: #111827; }
        
        .badge-kompensasi {
            background: #fef3c7;
            color: #d97706;
            font-size: 7px;
            text-align: center;
            display: block;
            margin-top: 2px;
            font-weight: bold;
            padding: 1px;
            border: 1px solid #d97706;
        }
        .badge-nunggak {
            background: #fee2e2;
            color: #b91c1c;
            font-size: 7px;
            text-align: center;
            display: inline-block;
            font-weight: bold;
            padding: 1px 3px;
            border: 1px solid #b91c1c;
            margin-top: 2px;
        }
        .footer {
            font-size: 9px;
            text-align: center;
            color: #666;
            margin-top: 30px;
        }
        .text-center { text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Rekapitulasi Pinjaman Koperasi Polres Lombok Utara</h1>
        <h2>Tahun: <strong>{{ $year }}</strong> | Filter: <strong>{{ ucfirst($filter) }}</strong></h2>
    </div>

    <table class="summary-boxes">
        <tr>
            <td>
                <div class="summary-title">Total Terealisasi</div>
                <div class="summary-value">Rp {{ number_format($totalPinjamanCair, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="summary-title">Pokok Setoran Masuk</div>
                <div class="summary-value">Rp {{ number_format($totalPokok, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="summary-title">Pendapatan Jasa (1%)</div>
                <div class="summary-value">Rp {{ number_format($totalJasa, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <table class="matrix">
        <thead>
            <tr>
                <th style="text-align: left;">Nama / NRP</th>
                @foreach(['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'] as $m)
                    <th style="width: 55px;">{{ $m }}</th>
                @endforeach
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rekapBulan as $row)
                <tr>
                    <td class="name-col">
                        {{ $row['user']->name }}
                        <div class="name-detail">{{ $row['user']->nrp }}</div>
                        @if($row['tunggakan_bulan'] > 0)
                            <div class="badge-nunggak">Nunggak {{ $row['tunggakan_bulan'] }} Bln</div>
                            <div style="font-size:7px; margin-top:2px;">
                                <div class="val-row"><span class="lbl-tgk">Tgk P:</span> {{ number_format($row['tunggakan_pokok'], 0, ',', '.') }}</div>
                                <div class="val-row"><span style="color:#c2410c; font-weight:bold; float:left;">Tgk J:</span> {{ number_format($row['tunggakan_jasa'], 0, ',', '.') }}</div>
                            </div>
                        @endif
                    </td>
                    @for($i = 1; $i <= 12; $i++)
                        <td>
                            @if($row['months'][$i]['pokok'] > 0 || $row['months'][$i]['jasa'] > 0)
                                <div class="cell-content">
                                    @if($row['months'][$i]['pokok'] > 0)
                                        <div class="val-row"><span class="lbl-p">P:</span> <span class="val-p">{{ number_format($row['months'][$i]['pokok'], 0, ',', '.') }}</span></div>
                                    @endif
                                    @if($row['months'][$i]['jasa'] > 0)
                                        <div class="val-row"><span class="lbl-j">J:</span> <span class="val-j">{{ number_format($row['months'][$i]['jasa'], 0, ',', '.') }}</span></div>
                                    @endif
                                </div>
                                @if(isset($row['is_kompensasi']) && $row['is_kompensasi'] && $i == $row['kompensasi_month'])
                                    <div class="badge-kompensasi">Kompensasi</div>
                                @endif
                            @else
                                @if(isset($row['is_kompensasi']) && $row['is_kompensasi'] && $i <= $row['kompensasi_month'])
                                    <div class="text-center" style="font-size:7px; color:#c2410c; font-weight:bold;">KOMP</div>
                                @elseif(isset($row['acc_month']) && $i > $row['acc_month'] && $i <= $row['current_month'])
                                    <div class="text-center"><span class="badge-nunggak">X</span></div>
                                @else
                                    <div class="text-center" style="color:#999">-</div>
                                @endif
                            @endif
                        </td>
                    @endfor
                    <td class="total-col cell-content">
                        <div class="val-row"><span class="lbl-p">Tot P:</span> {{ number_format($row['total_pokok'], 0, ',', '.') }}</div>
                        <div class="val-row"><span class="lbl-j">Tot J:</span> {{ number_format($row['total_jasa'], 0, ',', '.') }}</div>
                    </td>
                </tr>
            @endforeach
        </tbody>
        @if(count($rekapBulan) > 0)
            <tfoot>
                <tr>
                    <td class="name-col" style="background-color: #e5e7eb;">
                        TOTAL KESELURUHAN
                        @if($totalTunggakanPokok > 0 || $totalTunggakanJasa > 0)
                            <div style="font-size:8px; margin-top:4px;">
                                <div class="val-row"><span class="lbl-tgk">Tot Tgk P:</span> {{ number_format($totalTunggakanPokok, 0, ',', '.') }}</div>
                                <div class="val-row"><span style="color:#1d4ed8; font-weight:bold; float:left;">Tot Tgk J:</span> {{ number_format($totalTunggakanJasa, 0, ',', '.') }}</div>
                            </div>
                        @endif
                    </td>
                    @for($i = 1; $i <= 12; $i++)
                        <td style="background-color: #f3f4f6;">
                            @if($totalPerBulan[$i]['pokok'] > 0 || $totalPerBulan[$i]['jasa'] > 0)
                                <div class="cell-content">
                                    @if($totalPerBulan[$i]['pokok'] > 0)
                                        <div class="val-row"><span class="lbl-p">P:</span> {{ number_format($totalPerBulan[$i]['pokok'], 0, ',', '.') }}</div>
                                    @endif
                                    @if($totalPerBulan[$i]['jasa'] > 0)
                                        <div class="val-row"><span class="lbl-j">J:</span> {{ number_format($totalPerBulan[$i]['jasa'], 0, ',', '.') }}</div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center" style="color:#999">-</div>
                            @endif
                        </td>
                    @endfor
                    <td class="total-col cell-content" style="background-color: #e5e7eb;">
                        <div class="val-row"><span class="lbl-p">Tot P:</span> {{ number_format($totalPokok, 0, ',', '.') }}</div>
                        <div class="val-row"><span class="lbl-j">Tot J:</span> {{ number_format($totalJasa, 0, ',', '.') }}</div>
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        Dicetak pada: {{ now()->format('d/m/Y H:i') }} | Sistem Koperasi Polres Lombok Utara
    </div>
</body>
</html>
