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
        Schema::table('anggotas', function (Blueprint $table) {
            if (!Schema::hasColumn('anggotas', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('pangkat');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anggotas', function (Blueprint $table) {
            if (Schema::hasColumn('anggotas', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
