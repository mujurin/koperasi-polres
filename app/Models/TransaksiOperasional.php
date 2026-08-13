<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class TransaksiOperasional extends Model
{
    use HasFactory;

    protected $table = 'transaksi_operasional';

    protected $fillable = [
        'tanggal',
        'jenis', // 'beban', 'pendapatan_lain'
        'kategori',
        'nominal',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
    ];

    /**
     * Memastikan tabel transaksi_operasional tersedia secara otomatis
     */
    public static function ensureTableExists()
    {
        if (!Schema::hasTable('transaksi_operasional')) {
            Schema::create('transaksi_operasional', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal');
                $table->string('jenis')->default('beban'); // beban | pendapatan_lain
                $table->string('kategori');
                $table->decimal('nominal', 15, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }
    }

    public static function kategoriBebanList(): array
    {
        return [
            'Beban Administrasi & ATK',
            'Honor & Tunjangan Pengurus',
            'Biaya Rapat (RAT / RAO)',
            'Biaya Pemeliharaan Aset & Fasilitas',
            'Biaya Bank & Administrasi Keuangan',
            'Beban Operasional Lainnya',
        ];
    }

    public static function kategoriPendapatanLainList(): array
    {
        return [
            'Pendapatan Administrasi Bank / Jasa Giro',
            'Pendapatan Denda & Keterlambatan',
            'Pendapatan Usaha Lainnya',
            'Hibah / Sponsorship',
        ];
    }
}
