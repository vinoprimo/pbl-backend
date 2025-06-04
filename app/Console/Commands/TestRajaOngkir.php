<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RajaOngkirService;
use Illuminate\Support\Facades\Http;

class TestRajaOngkir extends Command
{
    protected $signature = 'test:rajaongkir';
    protected $description = 'Test RajaOngkir API connection and configuration';

    public function handle()
    {
        $this->info('🔍 Testing RajaOngkir Configuration...');
        
        // Check environment variables
        $apiKey = env('RAJAONGKIR_API_KEY');
        $this->info('📋 API Key from env: ' . ($apiKey ? '✅ Configured (' . strlen($apiKey) . ' chars)' : '❌ NOT CONFIGURED'));
        
        // Check config
        $configApiKey = config('services.rajaongkir.api_key');
        $this->info('⚙️  API Key from config: ' . ($configApiKey ? '✅ Configured (' . strlen($configApiKey) . ' chars)' : '❌ NOT CONFIGURED'));
        
        $baseUrl = config('services.rajaongkir.base_url');
        $this->info('🌐 Base URL: ' . $baseUrl);
        
        if (!$configApiKey) {
            $this->error('❌ RajaOngkir API key is not configured properly!');
            $this->info('💡 Make sure RAJAONGKIR_API_KEY is set in your .env file');
            $this->info('💡 Also run: php artisan config:clear');
            return 1;
        }
        
        // Show API key preview
        $this->info('🔑 API Key preview: ' . substr($configApiKey, 0, 8) . '...');
        
        // Test dengan format yang berhasil di Postman
        $this->info('🧪 Testing with working Postman format...');
        
        try {
            // Form data sesuai curl yang berhasil
            $formData = [
                'origin' => '31555',     // Sesuai curl example
                'destination' => '68423', // Sesuai curl example  
                'weight' => 1000,        // Weight langsung dalam gram (1000g = 1kg)
                'courier' => 'jne:jnt:sicepat'  // Multiple couriers untuk testing
            ];
            
            $this->info('📤 Request data: ' . json_encode($formData));
            $this->info('⚖️  Weight explanation: ' . $formData['weight'] . ' grams (direct from database, no conversion)');
            $this->info('🚚 Couriers: JNE, J&T, SiCepat (multiple options)');
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'key' => $configApiKey,  // Header 'key' bukan 'Authorization'
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])
                ->asForm()  // Format as form-encoded
                ->post($baseUrl . '/calculate/domestic-cost', $formData);
            
            $this->info('📡 Response Status: ' . $response->status());
            $this->info('📦 Response Body: ' . $response->body());
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['meta']['status']) && $data['meta']['status'] === 'success') {
                    $this->info('✅ Direct API call successful!');
                    $this->info('📊 Found ' . count($data['data']) . ' shipping options');
                    
                    // Show sample data
                    foreach (array_slice($data['data'], 0, 3) as $i => $option) {
                        $this->info('  ' . ($i + 1) . '. ' . ($option['name'] ?? 'Unknown') . ' - ' . ($option['service'] ?? 'Unknown') . ' - Rp ' . number_format($option['cost'] ?? 0));
                    }
                } else {
                    $this->error('❌ API returned error: ' . ($data['meta']['message'] ?? 'Unknown error'));
                    return 1;
                }
            } else {
                $errorData = $response->json();
                $this->error('❌ HTTP Error: ' . $response->status());
                $this->error('📝 Error message: ' . ($errorData['meta']['message'] ?? 'Unknown error'));
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Direct API test failed: ' . $e->getMessage());
            return 1;
        }
        
        // Test dengan service class
        $this->info('🔧 Testing via RajaOngkir Service...');
        $service = new RajaOngkirService();
        
        // Test with same data as working curl dengan multiple couriers
        $result = $service->calculateDomesticCost('31555', '68423', 1000, 'jne:jnt:sicepat');
        
        if ($result['success']) {
            $this->info('✅ RajaOngkir Service test successful!');
            $this->info('📊 Found ' . count($result['data']) . ' shipping options');
            
            // Group by courier for better display
            $groupedOptions = [];
            foreach ($result['data'] as $option) {
                $courierName = $option['name'] ?? 'Unknown';
                if (!isset($groupedOptions[$courierName])) {
                    $groupedOptions[$courierName] = [];
                }
                $groupedOptions[$courierName][] = $option;
            }
            
            // Display options grouped by courier
            foreach ($groupedOptions as $courier => $options) {
                $this->info("  📦 {$courier}:");
                foreach (array_slice($options, 0, 3) as $option) {
                    $this->info('    - ' . ($option['service'] ?? 'Unknown') . ' - Rp ' . number_format($option['cost'] ?? 0) . ' (' . ($option['etd'] ?? 'Unknown') . ')');
                }
                if (count($options) > 3) {
                    $this->info('    ... and ' . (count($options) - 3) . ' more options');
                }
            }
        } else {
            $this->error('❌ RajaOngkir Service test failed: ' . $result['message']);
            return 1;
        }
        
        $this->info('🎉 All tests passed! RajaOngkir is working correctly.');
        return 0;
    }
}
