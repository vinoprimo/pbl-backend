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
        Schema::create('retur_barang', function (Blueprint $table) {
            // Primary key
            $table->integer('id_retur')->autoIncrement();
            
            // Foreign keys
            $table->unsignedBigInteger('id_user');
            $table->integer('id_pembelian');
            $table->integer('id_detail_pembelian');
            $table->integer('id_komplain');
            
            // Retur details
            $table->enum('alasan_retur', [
                'Barang Rusak',
                'Tidak Sesuai Deskripsi',
                'Salah Kirim',
                'Lainnya'
            ]);
            $table->text('deskripsi_retur');
            $table->string('foto_bukti');
            $table->enum('status_retur', [
                'Menunggu Persetujuan',
                'Disetujui',
                'Ditolak',
                'Diproses',
                'Selesai'
            ])->default('Menunggu Persetujuan');
            
            // Admin notes
            $table->text('admin_notes')->nullable();
            
            // Timestamps
            $table->timestamp('tanggal_pengajuan')->useCurrent();
            $table->timestamp('tanggal_disetujui')->nullable();
            $table->timestamp('tanggal_selesai')->nullable();
            $table->timestamps(); // For created_at and updated_at
            
            // Created and updated by
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key constraints
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian');
            $table->foreign('id_detail_pembelian')->references('id_detail')->on('detail_pembelian');
            $table->foreign('id_komplain')->references('id_komplain')->on('komplain');
            $table->foreign('created_by')->references('id_user')->on('users');
            $table->foreign('updated_by')->references('id_user')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retur_barang');
    }
};
