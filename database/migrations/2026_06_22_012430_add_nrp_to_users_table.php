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
        Schema::table('users', function (Blueprint $table) {
            // Cek apakah kolom 'nrp' BELUM ada sebelum menambahkannya
            if (!Schema::hasColumn('users', 'nrp')) {
                $table->string('nrp')->unique()->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Pastikan kolom ada sebelum mencoba menghapus index dan kolomnya
            if (Schema::hasColumn('users', 'nrp')) {
                // Drop index unique terlebih dahulu
                $table->dropUnique(['nrp']);
                // Lalu drop kolomnya
                $table->dropColumn('nrp');
            }
        });
    }
};