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
        Schema::create('ruang_chat', function (Blueprint $table) {
            $table->integer('id_ruang_chat')->autoIncrement();
            $table->unsignedBigInteger('id_pembeli');
            $table->unsignedBigInteger('id_penjual');
            $table->unsignedBigInteger('id_barang')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Archived'])->default('Active');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('id_pembeli')->references('id_user')->on('users');
            $table->foreign('id_penjual')->references('id_user')->on('users');
            $table->foreign('id_barang')->references('id_barang')->on('barang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ruang_chat');
    }
};
