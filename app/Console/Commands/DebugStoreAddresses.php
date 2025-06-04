<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AlamatToko;
use App\Models\Toko;

class DebugStoreAddresses extends Command
{
    protected $signature = 'debug:store-addresses {id_toko?}';
    protected $description = 'Debug store addresses and postal codes';

    public function handle()
    {
        $idToko = $this->argument('id_toko');
        
        if ($idToko) {
            $this->debugSpecificStore($idToko);
        } else {
            $this->debugAllStores();
        }
    }
    
    private function debugSpecificStore($idToko)
    {
        $this->info("🔍 Debugging store ID: {$idToko}");
        
        $store = Toko::find($idToko);
        if (!$store) {
            $this->error("❌ Store not found");
            return;
        }
        
        $this->info("✅ Store found: {$store->nama_toko}");
        
        $addresses = AlamatToko::where('id_toko', $idToko)->get();
        $this->info("📍 Found " . $addresses->count() . " addresses:");
        
        foreach ($addresses as $address) {
            $this->info("   ID: {$address->id_alamat_toko}");
            $this->info("   Primary: " . ($address->is_primary ? 'Yes' : 'No'));
            $this->info("   Postal Code: '{$address->kode_pos}'");
            $this->info("   Address: {$address->alamat_lengkap}");
            $this->info("   Sender: {$address->nama_pengirim}");
            $this->info("   Phone: {$address->no_telepon}");
            $this->info("   ---");
        }
        
        $primaryAddress = $addresses->where('is_primary', true)->first();
        if ($primaryAddress) {
            $this->info("🎯 Primary address postal code: '{$primaryAddress->kode_pos}'");
            if (empty($primaryAddress->kode_pos)) {
                $this->error("❌ Primary address has empty postal code!");
            } else if (strlen($primaryAddress->kode_pos) < 5) {
                $this->error("❌ Primary address postal code is too short: '{$primaryAddress->kode_pos}'");
            } else {
                $this->info("✅ Primary address postal code looks valid");
            }
        } else {
            $this->error("❌ No primary address found!");
        }
    }
    
    private function debugAllStores()
    {
        $this->info("🔍 Debugging all store addresses...");
        
        $stores = Toko::with('alamat_toko')->get();
        $this->info("Found " . $stores->count() . " stores");
        
        $storesWithoutAddress = 0;
        $storesWithoutPrimary = 0;
        $storesWithEmptyPostal = 0;
        
        foreach ($stores as $store) {
            $addresses = $store->alamat_toko;
            
            if ($addresses->isEmpty()) {
                $storesWithoutAddress++;
                $this->warn("Store '{$store->nama_toko}' (ID: {$store->id_toko}) has no addresses");
                continue;
            }
            
            $primaryAddress = $addresses->where('is_primary', true)->first();
            if (!$primaryAddress) {
                $storesWithoutPrimary++;
                $this->warn("Store '{$store->nama_toko}' (ID: {$store->id_toko}) has no primary address");
                continue;
            }
            
            if (empty($primaryAddress->kode_pos)) {
                $storesWithEmptyPostal++;
                $this->error("Store '{$store->nama_toko}' (ID: {$store->id_toko}) has empty postal code");
            }
        }
        
        $this->info("\n📊 Summary:");
        $this->info("Total stores: " . $stores->count());
        $this->error("Stores without addresses: {$storesWithoutAddress}");
        $this->error("Stores without primary address: {$storesWithoutPrimary}");
        $this->error("Stores with empty postal codes: {$storesWithEmptyPostal}");
        
        if ($storesWithoutAddress + $storesWithoutPrimary + $storesWithEmptyPostal === 0) {
            $this->info("✅ All stores have valid addresses with postal codes!");
        }
    }
}
