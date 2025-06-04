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
        Schema::create('saldo_perusahaan', function (Blueprint $table) {
            $table->integer('id_saldo_perusahaan')->autoIncrement();
            $table->integer('id_pembelian');
            $table->unsignedBigInteger('id_penjual');
            $table->double('jumlah_saldo');
            $table->enum('status', ['Menunggu Penyelesaian', 'Siap Dicairkan', 'Dicairkan']);
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian');
            $table->foreign('id_penjual')->references('id_user')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saldo_perusahaan');
    }
};
