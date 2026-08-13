<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE pinjaman MODIFY COLUMN jenis_permohonan VARCHAR(255) DEFAULT 'Biasa'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE pinjaman MODIFY COLUMN jenis_permohonan ENUM('Biasa', 'Urgent') DEFAULT 'Biasa'");
    }
};
