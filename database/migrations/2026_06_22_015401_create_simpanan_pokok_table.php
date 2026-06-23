<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simpanan_pokok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal');
            $table->string('keterangan')->nullable();
            $table->timestamps();

            // Simpanan pokok hanya boleh 1x per anggota
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpanan_pokok');
    }
};
