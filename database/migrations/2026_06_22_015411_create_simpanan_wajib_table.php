<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simpanan_wajib', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('jumlah', 15, 2);
            $table->tinyInteger('bulan'); // 1-12
            $table->year('tahun');
            $table->string('keterangan')->nullable();
            $table->timestamps();

            // 1 setoran per bulan per anggota
            $table->unique(['user_id', 'bulan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpanan_wajib');
    }
};
