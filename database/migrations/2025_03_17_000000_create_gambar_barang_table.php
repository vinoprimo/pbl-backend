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
        Schema::create('gambar_barang', function (Blueprint $table) {
            $table->id('id_gambar');
            $table->unsignedBigInteger('id_barang');
            $table->string('url_gambar');
            $table->integer('urutan');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();
            
            // Foreign key relationship
            $table->foreign('id_barang')->references('id_barang')->on('barang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gambar_barang');
    }
};
