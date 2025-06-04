<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugRajaOngkir extends Command
{
    protected $signature = 'debug:rajaongkir {apikey?}';
    protected $description = 'Debug RajaOngkir API with different endpoints and formats';

    public function handle()
    {
        $apiKey = $this->argument('apikey') ?: config('services.rajaongkir.api_key');
        
        if (!$apiKey) {
            $this->error('❌ No API key provided. Use: php artisan debug:rajaongkir YOUR_API_KEY');
            return 1;
        }
        
        $this->info('🔍 Debugging RajaOngkir API...');
        $this->info('🔑 Using API Key: ' . substr($apiKey, 0, 8) . '...');
        
        // Different base URLs to try
        $endpoints = [
            'Komerce ID v1' => 'https://rajaongkir.komerce.id/api/v1',
            'Official Starter' => 'https://api.rajaongkir.com/starter',
            'Official Basic' => 'https://api.rajaongkir.com/basic',
            'Official Pro' => 'https://pro.rajaongkir.com/api',
        ];
        
        // Different header formats
        $headerFormats = [
            'key header' => ['key' => $apiKey],
            'Bearer token' => ['Authorization' => 'Bearer ' . $apiKey],
            'Api-Key' => ['Authorization' => 'Api-Key ' . $apiKey],
            'x-api-key' => ['x-api-key' => $apiKey],
            'Direct auth' => ['Authorization' => $apiKey],
        ];
        
        $testData = [
            'origin' => '501', // Jakarta
            'destination' => '114', // Bandung
            'weight' => 1700,
            'courier' => 'jne'
        ];
        
        foreach ($endpoints as $endpointName => $baseUrl) {
            $this->info("\n🌐 Testing endpoint: {$endpointName}");
            $this->info("   URL: {$baseUrl}");
            
            foreach ($headerFormats as $formatName => $headers) {
                $this->info("   🧪 Header format: {$formatName}");
                
                try {
                    $fullUrl = $baseUrl . '/cost';
                    
                    $response = Http::timeout(30)
                        ->withHeaders(array_merge([
                            'Content-Type' => 'application/x-www-form-urlencoded', // Try form data
                        ], $headers))
                        ->asForm() // Use form data instead of JSON
                        ->post($fullUrl, $testData);
                    
                    $this->info("      📡 Status: {$response->status()}");
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        
                        if (isset($data['rajaongkir']['status']['code']) && $data['rajaongkir']['status']['code'] == 200) {
                            $this->info("      ✅ SUCCESS! Found working configuration:");
                            $this->info("         Endpoint: {$baseUrl}");
                            $this->info("         Headers: " . json_encode($headers));
                            $this->info("         Results: " . count($data['rajaongkir']['results'][0]['costs']) . " shipping options");
                            
                            // Show sample results
                            foreach (array_slice($data['rajaongkir']['results'][0]['costs'], 0, 2) as $cost) {
                                $this->info("         - {$cost['service']}: Rp " . number_format($cost['cost'][0]['value']));
                            }
                            
                            return 0;
                        } else {
                            $this->error("      ❌ API Error: " . ($data['rajaongkir']['status']['description'] ?? 'Unknown'));
                        }
                    } else {
                        $errorData = $response->json();
                        $this->error("      ❌ HTTP {$response->status()}: " . ($errorData['message'] ?? $response->body()));
                    }
                    
                } catch (\Exception $e) {
                    $this->error("      ❌ Exception: {$e->getMessage()}");
                }
            }
        }
        
        $this->error("\n❌ No working configuration found.");
        $this->info("💡 Please check:");
        $this->info("   1. API key is valid and active");
        $this->info("   2. Account has sufficient credits");
        $this->info("   3. Account type matches the endpoint (starter/basic/pro)");
        
        return 1;
    }
}
