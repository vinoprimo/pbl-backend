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
        Schema::create('tagihan', function (Blueprint $table) {
            $table->integer('id_tagihan')->autoIncrement();
            $table->integer('id_pembelian');
            $table->string('kode_tagihan')->unique();
            $table->double('total_harga');
            $table->double('biaya_kirim')->comment('Biaya ongkir yang dipilih pengguna');
            $table->string('opsi_pengiriman')->comment('Opsi pengiriman yang dipilih (misalnya, cepat, kargo)');
            $table->double('biaya_admin');
            $table->double('total_tagihan');
            $table->string('metode_pembayaran');
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('midtrans_payment_type')->nullable();
            $table->string('midtrans_status')->nullable();
            $table->enum('status_pembayaran', ['Menunggu', 'Dibayar', 'Expired', 'Gagal']);
            $table->timestamp('deadline_pembayaran')->nullable();
            $table->timestamp('tanggal_pembayaran')->nullable();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
