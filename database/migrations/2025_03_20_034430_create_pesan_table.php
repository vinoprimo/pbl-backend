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
        Schema::create('pesan', function (Blueprint $table) {
            $table->integer('id_pesan')->autoIncrement();
            $table->integer('id_ruang_chat');
            $table->unsignedBigInteger('id_user');
            $table->enum('tipe_pesan', ['Text', 'Penawaran', 'Gambar', 'System']);
            $table->text('isi_pesan')->nullable();
            $table->double('harga_tawar')->nullable();
            $table->enum('status_penawaran', ['Menunggu', 'Diterima', 'Ditolak'])->nullable();
            $table->unsignedBigInteger('id_barang')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('id_ruang_chat')->references('id_ruang_chat')->on('ruang_chat');
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->foreign('id_barang')->references('id_barang')->on('barang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pesan');
    }
};
