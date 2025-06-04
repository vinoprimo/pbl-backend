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
        Schema::create('pengiriman_pembelian', function (Blueprint $table) {
            $table->id('id_pengiriman');
            // Changed from unsignedBigInteger to integer to match the id_detail column type in detail_pembelian
            $table->integer('id_detail_pembelian');
            $table->string('nomor_resi', 100);
            $table->timestamp('tanggal_pengiriman');
            $table->string('bukti_pengiriman')->nullable();
            $table->text('catatan_pengiriman')->nullable();
            $table->timestamps();
            
            // The foreign key constraint remains the same
            $table->foreign('id_detail_pembelian')
                  ->references('id_detail')
                  ->on('detail_pembelian')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengiriman_pembelian');
    }
};
