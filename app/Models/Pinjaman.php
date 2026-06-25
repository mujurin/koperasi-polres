<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pinjaman extends Model
{
    use HasFactory;

    protected $table = 'pinjaman';

    protected $fillable = [
        'user_id',
        'jumlah_ajuan',
        'tenor',
        'jasa_persen',
        'jumlah_diterima',
        'angsuran_perbulan',
        'status',
        'keterangan',
        'jenis_permohonan',
    ];

    protected $casts = [
        'jumlah_ajuan' => 'decimal:2',
        'jasa_persen' => 'decimal:2',
        'jumlah_diterima' => 'decimal:2',
        'angsuran_perbulan' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function angsurans()
    {
        return $this->hasMany(Angsuran::class, 'pinjaman_id');
    }
}
