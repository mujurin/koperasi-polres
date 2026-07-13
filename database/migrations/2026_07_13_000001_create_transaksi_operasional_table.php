<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi_operasional');
    }
};
