<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestRajaOngkirCurl extends Command
{
    protected $signature = 'test:rajaongkir-curl';
    protected $description = 'Test RajaOngkir API using exact curl format from Postman';

    public function handle()
    {
        $this->info('🔍 Testing RajaOngkir with exact curl format...');
        
        $apiKey = config('services.rajaongkir.api_key');
        $baseUrl = config('services.rajaongkir.base_url');
        
        if (!$apiKey) {
            $this->error('❌ API key not configured');
            return 1;
        }
        
        $this->info('🔑 API Key: ' . substr($apiKey, 0, 8) . '...');
        $this->info('🌐 URL: ' . $baseUrl . '/calculate/domestic-cost');
        
        try {
            // Exact format from your working curl
            $response = Http::timeout(30)
                ->withHeaders([
                    'key' => $apiKey,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])
                ->asForm()
                ->post($baseUrl . '/calculate/domestic-cost', [
                    'origin' => '31555',
                    'destination' => '68423', 
                    'weight' => 1000,
                    'courier' => 'jne'
                ]);
            
            $this->info('📡 Status: ' . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info('✅ Success! Response:');
                $this->info(json_encode($data, JSON_PRETTY_PRINT));
                
                if (isset($data['data']) && is_array($data['data'])) {
                    $this->info('📊 Found ' . count($data['data']) . ' options');
                }
            } else {
                $this->error('❌ Failed: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Exception: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
