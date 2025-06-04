<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestShippingIntegration extends Command
{
    protected $signature = 'test:shipping-integration';
    protected $description = 'Test complete shipping integration flow';

    public function handle()
    {
        $this->info('🚚 Testing Complete Shipping Integration...');
        
        // Test dengan endpoint yang actual akan digunakan frontend
        $apiKey = config('services.rajaongkir.api_key');
        $baseUrl = config('services.rajaongkir.base_url');
        
        if (!$apiKey) {
            $this->error('❌ API key not configured');
            return 1;
        }
        
        $this->info('🔑 API Key: ' . substr($apiKey, 0, 8) . '...');
        $this->info('🌐 URL: ' . $baseUrl . '/calculate/domestic-cost');
        
        // Test with different postal codes (realistic scenarios)
        $testCases = [
            ['origin' => '10110', 'destination' => '40111', 'description' => 'Jakarta to Bandung'],
            ['origin' => '60119', 'destination' => '55161', 'description' => 'Surabaya to Yogyakarta'],
            ['origin' => '20112', 'destination' => '80113', 'description' => 'Medan to Denpasar'],
        ];
        
        foreach ($testCases as $test) {
            $this->info("\n📍 Testing: {$test['description']}");
            
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'key' => $apiKey,
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ])
                    ->asForm()
                    ->post($baseUrl . '/calculate/domestic-cost', [
                        'origin' => $test['origin'],
                        'destination' => $test['destination'],
                        'weight' => 1000,
                        'courier' => 'jne'
                    ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['data']) && is_array($data['data'])) {
                        $this->info('   ✅ Success: ' . count($data['data']) . ' options');
                        
                        // Show cheapest option
                        $cheapest = collect($data['data'])->sortBy('cost')->first();
                        $this->info('   💰 Cheapest: ' . $cheapest['service'] . ' - Rp ' . number_format($cheapest['cost']));
                    } else {
                        $this->error('   ❌ No shipping data returned');
                    }
                } else {
                    $this->error('   ❌ HTTP ' . $response->status() . ': ' . $response->body());
                }
                
            } catch (\Exception $e) {
                $this->error('   ❌ Exception: ' . $e->getMessage());
            }
        }
        
        $this->info("\n🎯 Integration test completed!");
        $this->info("💡 Next steps:");
        $this->info("   1. Test shipping calculation in frontend checkout");
        $this->info("   2. Verify service codes are properly formatted");
        $this->info("   3. Test with real product checkout flow");
        
        return 0;
    }
}
