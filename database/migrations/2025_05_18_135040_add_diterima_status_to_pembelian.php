<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelian', function (Blueprint $table) {
            // Drop and recreate the enum column with new status
            DB::statement("ALTER TABLE pembelian MODIFY COLUMN status_pembelian ENUM('Draft', 'Menunggu Pembayaran', 'Dibayar', 'Diproses', 'Dikirim', 'Diterima', 'Selesai', 'Dibatalkan')");
        });
    }

    public function down(): void
    {
        Schema::table('pembelian', function (Blueprint $table) {
            // Revert back to original enum values
            DB::statement("ALTER TABLE pembelian MODIFY COLUMN status_pembelian ENUM('Draft', 'Menunggu Pembayaran', 'Dibayar', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan')");
        });
    }
};
