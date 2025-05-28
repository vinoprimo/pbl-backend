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
        Schema::create('alamat_toko', function (Blueprint $table) {
            $table->integer('id_alamat_toko')->autoIncrement();
            $table->unsignedBigInteger('id_toko');
            $table->string('nama_pengirim');
            $table->string('no_telepon');
            $table->text('alamat_lengkap');
            $table->string('provinsi');
            $table->string('kota');
            $table->string('kecamatan');
            $table->string('kode_pos');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('id_toko')->references('id_toko')->on('toko');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alamat_toko');
    }
};
