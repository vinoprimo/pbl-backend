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
        Schema::create('review', function (Blueprint $table) {
            $table->integer('id_review')->autoIncrement();
            $table->unsignedBigInteger('id_user');
            $table->integer('id_pembelian');
            $table->integer('rating');
            $table->text('komentar');
            $table->string('image_review')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review');
    }
};
