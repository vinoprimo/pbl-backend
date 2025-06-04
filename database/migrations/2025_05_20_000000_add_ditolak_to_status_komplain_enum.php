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
        // Modify the enum values of status_komplain column
        DB::statement("ALTER TABLE komplain MODIFY COLUMN status_komplain ENUM('Menunggu', 'Diproses', 'Selesai', 'Ditolak')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE komplain MODIFY COLUMN status_komplain ENUM('Menunggu', 'Diproses', 'Selesai')");
    }
};
