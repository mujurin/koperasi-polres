<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aset extends Model
{
    use HasFactory;

    protected $fillable = [
        'tanggal_perolehan',
        'nama_barang',
        'foto_path',
        'jumlah_barang',
        'no_register',
        'harga',
        'keadaan',
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date',
        'jumlah_barang' => 'integer',
        'harga' => 'integer',
    ];

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_path ? url($this->foto_path) : null;
    }
}
