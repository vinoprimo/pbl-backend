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
        Schema::table('toko', function (Blueprint $table) {
            $table->string('slug')->after('nama_toko')->unique()->nullable();
        });

        // Generate slugs for existing stores
        $this->generateSlugsForExistingStores();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('toko', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    /**
     * Generate slugs for existing stores
     */
    private function generateSlugsForExistingStores(): void
    {
        $toko = DB::table('toko')->get();
        
        foreach ($toko as $store) {
            // Generate a slug from the store name
            $slug = $this->createSlug($store->nama_toko);
            
            // Update the store with the new slug
            DB::table('toko')
                ->where('id_toko', $store->id_toko)
                ->update(['slug' => $slug]);
        }
    }

    /**
     * Create a unique slug
     */
    private function createSlug($name): string
    {
        // Convert the name to a slug
        $baseSlug = \Str::slug($name);
        $slug = $baseSlug;
        $count = 1;
        
        // Check if the slug already exists and append a number if it does
        while (DB::table('toko')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $count++;
        }
        
        return $slug;
    }
};
