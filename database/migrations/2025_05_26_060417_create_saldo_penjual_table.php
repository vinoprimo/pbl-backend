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
        Schema::create('saldo_penjual', function (Blueprint $table) {
            $table->integer('id_saldo_penjual')->autoIncrement();
            $table->unsignedBigInteger('id_user');
            $table->double('saldo_tersedia')->default(0);
            $table->double('saldo_tertahan')->default(0);
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('id_user')->references('id_user')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saldo_penjual');
    }
};
