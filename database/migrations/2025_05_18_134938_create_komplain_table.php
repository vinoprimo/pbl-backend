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
        Schema::create('komplain', function (Blueprint $table) {
            $table->integer('id_komplain')->autoIncrement();
            $table->unsignedBigInteger('id_user');
            $table->integer('id_pembelian');
            $table->enum('alasan_komplain', [
                'Barang Tidak Sesuai',
                'Barang Rusak', 
                'Barang Tidak Sampai',
                'Lainnya'
            ]);
            $table->text('isi_komplain');
            $table->string('bukti_komplain');
            $table->enum('status_komplain', ['Menunggu', 'Diproses', 'Selesai']);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('komplain');
    }
};
