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
        Schema::create('pengajuan_pencairan', function (Blueprint $table) {
            $table->integer('id_pencairan')->autoIncrement();
            $table->unsignedBigInteger('id_user');
            $table->integer('id_saldo_penjual');
            $table->double('jumlah_dana');
            $table->text('keterangan')->nullable();
            $table->string('nomor_rekening');
            $table->string('nama_bank');
            $table->string('nama_pemilik_rekening');
            $table->date('tanggal_pengajuan');
            $table->enum('status_pencairan', ['Menunggu', 'Diproses', 'Selesai', 'Ditolak']);
            $table->timestamp('tanggal_pencairan')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key constraints
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->foreign('id_saldo_penjual')->references('id_saldo_penjual')->on('saldo_penjual');
            $table->foreign('created_by')->references('id_user')->on('users');
            $table->foreign('updated_by')->references('id_user')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_pencairan');
    }
};
