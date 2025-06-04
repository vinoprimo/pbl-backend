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
        Schema::table('tagihan', function (Blueprint $table) {
            // Add snap_token and payment_url columns if they don't exist yet
            if (!Schema::hasColumn('tagihan', 'snap_token')) {
                $table->string('snap_token')->nullable()->after('tanggal_pembayaran');
            }
            if (!Schema::hasColumn('tagihan', 'payment_url')) {
                $table->string('payment_url')->nullable()->after('snap_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            // Only drop columns if they exist
            if (Schema::hasColumn('tagihan', 'snap_token')) {
                $table->dropColumn('snap_token');
            }
            if (Schema::hasColumn('tagihan', 'payment_url')) {
                $table->dropColumn('payment_url');
            }
        });
    }
};
