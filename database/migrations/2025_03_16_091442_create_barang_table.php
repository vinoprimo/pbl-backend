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
        Schema::create('barang', function (Blueprint $table) {
            $table->id('id_barang');
            $table->unsignedBigInteger('id_kategori');
            $table->unsignedBigInteger('id_toko');
            $table->string('nama_barang');
            $table->string('slug')->unique();
            $table->text('deskripsi_barang');
            $table->double('harga');
            $table->string('grade')->comment('Grading barang: Seperti Baru, Bekas Layak Pakai, Rusak Ringan, Rusak Berat');
            $table->enum('status_barang', ['Tersedia', 'Terjual', 'Habis'])->default('Tersedia');
            $table->integer('stok')->default(1)->comment('Jumlah item yang tersedia');
            $table->text('kondisi_detail');
            $table->decimal('berat_barang');
            $table->string('dimensi');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key relationships
            $table->foreign('id_toko')->references('id_toko')->on('toko');
            $table->foreign('id_kategori')->references('id_kategori')->on('kategori');
            $table->foreign('created_by')->references('id_user')->on('users');
            $table->foreign('updated_by')->references('id_user')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
