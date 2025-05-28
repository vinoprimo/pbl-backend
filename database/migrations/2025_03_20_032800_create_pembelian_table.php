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
        Schema::create('pembelian', function (Blueprint $table) {
            $table->integer('id_pembelian')->autoIncrement();
            $table->unsignedBigInteger('id_pembeli');
            $table->integer('id_alamat');
            $table->string('kode_pembelian')->unique();
            $table->enum('status_pembelian', ['Draft', 'Menunggu Pembayaran', 'Dibayar', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan']);
            $table->text('catatan_pembeli')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key constraints
            $table->foreign('id_pembeli')->references('id_user')->on('users');
            $table->foreign('id_alamat')->references('id_alamat')->on('alamat_user');
            $table->foreign('created_by')->references('id_user')->on('users');
            $table->foreign('updated_by')->references('id_user')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembelian');
    }
};
