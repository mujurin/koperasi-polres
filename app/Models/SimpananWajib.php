<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpananWajib extends Model
{
    use HasFactory;

    protected $table = 'simpanan_wajib';

    protected $fillable = [
        'user_id',
        'jumlah',
        'bulan',
        'tahun',
        'keterangan',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function namaBulan(int $bulan): string
    {
        $bulanArr = [
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
        return $bulanArr[$bulan] ?? '-';
    }
}
