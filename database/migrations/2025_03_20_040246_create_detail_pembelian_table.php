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
        Schema::create('detail_pembelian', function (Blueprint $table) {
            $table->integer('id_detail')->autoIncrement();
            $table->integer('id_pembelian');
            $table->unsignedBigInteger('id_barang');
            $table->unsignedBigInteger('id_toko');
            $table->integer('id_keranjang')->nullable();
            $table->integer('id_pesan')->nullable();
            $table->double('harga_satuan');
            $table->integer('jumlah')->default(1);
            $table->double('subtotal');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian');
            $table->foreign('id_barang')->references('id_barang')->on('barang');
            $table->foreign('id_toko')->references('id_toko')->on('toko');
            $table->foreign('id_keranjang')->references('id_keranjang')->on('keranjang');
            $table->foreign('id_pesan')->references('id_pesan')->on('pesan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_pembelian');
    }
};
