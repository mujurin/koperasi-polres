<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;

    protected $fillable = [
        'nip',
        'nmpeg',
        'pangkat',
        'total_bulan_terpenuhi',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'nip', 'nrp');
    }
}
