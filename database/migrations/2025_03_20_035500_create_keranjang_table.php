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
        Schema::create('keranjang', function (Blueprint $table) {
            $table->integer('id_keranjang')->autoIncrement();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_barang');
            $table->integer('jumlah')->default(1)->comment('Jumlah/quantity barang');
            $table->boolean('is_selected')->default(false)->comment('Untuk proses checkout');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->foreign('id_barang')->references('id_barang')->on('barang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keranjang');
    }
};
