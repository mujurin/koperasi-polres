<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pinjaman', function (Blueprint $table) {
            $table->id();
            // Since User's primary key is nrp? No. Let's stick with integer. If it errors, we will just use integer
            // Actually, in the last step, I resolved it by making sure `Auth::user()->id` is used, so user_id is the auto-increment ID.
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('jumlah_ajuan', 15, 2);
            $table->integer('tenor');
            $table->decimal('jasa_persen', 5, 2)->default(1.00); // 1%
            $table->decimal('jumlah_diterima', 15, 2);
            $table->decimal('angsuran_perbulan', 15, 2);
            $table->enum('status', ['proses', 'disetujui', 'ditolak'])->default('proses');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinjaman');
    }
};
