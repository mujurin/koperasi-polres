<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asets', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_perolehan');
            $table->string('nama_barang');
            $table->string('foto_path')->nullable();
            $table->integer('jumlah_barang');
            $table->string('no_register')->unique();
            $table->bigInteger('harga');
            $table->string('keadaan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asets');
    }
};
