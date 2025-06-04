<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Barang;

class DebugProductWeights extends Command
{
    protected $signature = 'debug:product-weights {id_barang?}';
    protected $description = 'Debug product weights in database';

    public function handle()
    {
        $idBarang = $this->argument('id_barang');
        
        if ($idBarang) {
            $this->debugSpecificProduct($idBarang);
        } else {
            $this->debugAllProducts();
        }
    }
    
    private function debugSpecificProduct($idBarang)
    {
        $this->info("🔍 Debugging product ID: {$idBarang}");
        
        $product = Barang::find($idBarang);
        if (!$product) {
            $this->error("❌ Product not found");
            return;
        }
        
        $this->info("✅ Product found: {$product->nama_barang}");
        $this->info("⚖️  Weight in database: '{$product->berat_barang}' grams");
        
        if (empty($product->berat_barang)) {
            $this->error("❌ Product has no weight data - will use default 500g");
        } else {
            $this->info("✅ Weight data is valid");
            
            // Show example calculations
            $quantities = [1, 2, 5, 10];
            $this->info("\n📊 Weight calculations for different quantities:");
            foreach ($quantities as $qty) {
                $totalWeight = $product->berat_barang * $qty;
                $this->info("   {$qty} items = {$totalWeight} grams");
            }
        }
    }
    
    private function debugAllProducts()
    {
        $this->info("🔍 Debugging all product weights...");
        
        $products = Barang::select('id_barang', 'nama_barang', 'berat_barang')
            ->where('is_deleted', false)
            ->get();
            
        $this->info("Found " . $products->count() . " products");
        
        $productsWithoutWeight = 0;
        $productsWithWeight = 0;
        $totalWeight = 0;
        
        foreach ($products as $product) {
            if (empty($product->berat_barang) || $product->berat_barang <= 0) {
                $productsWithoutWeight++;
                $this->warn("Product '{$product->nama_barang}' (ID: {$product->id_barang}) has no weight data");
            } else {
                $productsWithWeight++;
                $totalWeight += $product->berat_barang;
            }
        }
        
        $this->info("\n📊 Summary:");
        $this->info("Total products: " . $products->count());
        $this->info("✅ Products with weight data: {$productsWithWeight}");
        $this->error("❌ Products without weight data: {$productsWithoutWeight}");
        
        if ($productsWithWeight > 0) {
            $avgWeight = round($totalWeight / $productsWithWeight, 2);
            $this->info("📈 Average weight: {$avgWeight} grams");
        }
        
        $this->info("\n💡 Weight format explanation:");
        $this->info("   - Database stores weight in grams");
        $this->info("   - 1000 grams = 1 kilogram");
        $this->info("   - Products without weight use default 500 grams");
        $this->info("   - No conversion needed for RajaOngkir API");
    }
}
