<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RajaOngkirService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.rajaongkir.api_key');
        $this->baseUrl = config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1');
        
        // Log configuration for debugging
        Log::info('RajaOngkir Service Initialized', [
            'api_key_configured' => !empty($this->apiKey),
            'api_key_length' => $this->apiKey ? strlen($this->apiKey) : 0,
            'base_url' => $this->baseUrl
        ]);
    }

    /**
     * Calculate domestic shipping cost
     */
    public function calculateDomesticCost($originPostalCode, $destinationPostalCode, $weight, $courier = 'jne')
    {
        try {
            Log::info('RajaOngkir API Request', [
                'origin' => $originPostalCode,
                'destination' => $destinationPostalCode,
                'weight' => $weight,
                'courier' => $courier,
                'api_key_configured' => !empty($this->apiKey),
                'api_key_preview' => $this->apiKey ? substr($this->apiKey, 0, 8) . '...' : 'null'
            ]);

            // Check if API key is configured
            if (!$this->apiKey) {
                Log::error('RajaOngkir API key not configured');
                return [
                    'success' => false,
                    'message' => 'RajaOngkir API key not configured properly'
                ];
            }

            // Validate postal codes
            if (empty($originPostalCode) || empty($destinationPostalCode)) {
                Log::error('Invalid postal codes provided', [
                    'origin' => $originPostalCode,
                    'destination' => $destinationPostalCode
                ]);
                return [
                    'success' => false,
                    'message' => 'Origin or destination postal code is missing'
                ];
            }

            // Ensure postal codes are strings and clean them
            $cleanOrigin = trim((string)$originPostalCode);
            $cleanDestination = trim((string)$destinationPostalCode);

            if (strlen($cleanOrigin) < 5 || strlen($cleanDestination) < 5) {
                Log::error('Invalid postal code format', [
                    'origin' => $cleanOrigin,
                    'destination' => $cleanDestination,
                    'origin_length' => strlen($cleanOrigin),
                    'destination_length' => strlen($cleanDestination)
                ]);
                return [
                    'success' => false,
                    'message' => 'Invalid postal code format. Postal codes must be at least 5 digits.'
                ];
            }

            // Use form data format as shown in your working curl
            $formData = [
                'origin' => $cleanOrigin,
                'destination' => $cleanDestination,
                'weight' => $weight,
                'courier' => $courier
            ];

            Log::info('Making RajaOngkir API call', [
                'url' => $this->baseUrl . '/calculate/domestic-cost',
                'data' => $formData,
                'headers' => [
                    'key' => substr($this->apiKey, 0, 8) . '...',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);

            // Use the exact format that works in Postman
            $response = Http::timeout(30)
                ->withHeaders([
                    'key' => $this->apiKey,  // Use 'key' header instead of 'Authorization'
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])
                ->asForm()  // This will format data as form-encoded
                ->post($this->baseUrl . '/calculate/domestic-cost', $formData);

            Log::info('RajaOngkir API Response Status', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 500),
                'full_response' => $response->json()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('RajaOngkir API Response Data', ['data' => $data]);

                if (isset($data['meta']['status']) && $data['meta']['status'] === 'success') {
                    if (empty($data['data'])) {
                        return [
                            'success' => false,
                            'message' => 'No shipping options available for this route'
                        ];
                    }

                    return [
                        'success' => true,
                        'data' => $data['data']
                    ];
                } else {
                    Log::error('RajaOngkir API Error Response', ['response' => $data]);
                    return [
                        'success' => false,
                        'message' => $data['meta']['message'] ?? 'Shipping calculation failed'
                    ];
                }
            } else {
                $errorData = $response->json();
                Log::error('RajaOngkir API HTTP Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'error_data' => $errorData
                ]);
                
                // Check for specific error messages
                if ($response->status() === 401 || 
                    (isset($errorData['meta']['message']) && 
                     str_contains(strtolower($errorData['meta']['message']), 'api key'))) {
                    return [
                        'success' => false,
                        'message' => 'Invalid API key, key not found or expired'
                    ];
                }

                // Check for origin/destination not found
                if (isset($errorData['meta']['message'])) {
                    $errorMessage = $errorData['meta']['message'];
                    if (str_contains(strtolower($errorMessage), 'origin not found')) {
                        return [
                            'success' => false,
                            'message' => 'Origin postal code not found. Please check store address postal code.'
                        ];
                    }
                    if (str_contains(strtolower($errorMessage), 'destination not found')) {
                        return [
                            'success' => false,
                            'message' => 'Destination postal code not found. Please check delivery address postal code.'
                        ];
                    }
                }
                
                return [
                    'success' => false,
                    'message' => isset($errorData['meta']['message']) 
                        ? $errorData['meta']['message'] 
                        : 'Failed to connect to shipping service'
                ];
            }
        } catch (\Exception $e) {
            Log::error('RajaOngkir API Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Shipping service temporarily unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format shipping options for frontend
     */
    public function formatShippingOptions($shippingData)
    {
        $formatted = [];

        foreach ($shippingData as $option) {
            $formatted[] = [
                'courier_code' => $option['code'] ?? '',
                'courier_name' => $option['name'] ?? '',
                'service' => $option['service'] ?? '',
                'service_code' => $option['service'] ?? '', // Keep for backward compatibility
                'description' => $option['description'] ?? '',
                'cost' => (int) ($option['cost'] ?? 0),
                'etd' => $this->formatEtd($option['etd'] ?? ''),
                'display_name' => $this->formatDisplayName($option),
                'full_service_name' => $this->formatFullServiceName($option) // Add full name for opsi_pengiriman
            ];
        }

        // Sort by cost (cheapest first)
        usort($formatted, function($a, $b) {
            return $a['cost'] <=> $b['cost'];
        });

        return $formatted;
    }

    /**
     * Format ETD string
     */
    private function formatEtd($etd)
    {
        if (empty($etd)) {
            return '1-2 days';
        }

        // If ETD already contains "day" or "hari", return as is
        if (strpos(strtolower($etd), 'day') !== false || strpos(strtolower($etd), 'hari') !== false) {
            return $etd;
        }

        // Extract numbers from ETD string and add "days"
        if (preg_match('/(\d+)/', $etd, $matches)) {
            $days = $matches[1];
            return $days . ' day' . ($days > 1 ? 's' : '');
        }

        return $etd;
    }

    /**
     * Format display name for shipping option
     */
    private function formatDisplayName($option)
    {
        $courierName = $option['name'] ?? '';
        $service = $option['service'] ?? '';
        $description = $option['description'] ?? '';
        
        // Shorten long courier names
        $shortNames = [
            'Jalur Nugraha Ekakurir (JNE)' => 'JNE',
            'J&T Express' => 'J&T',
            'SiCepat Ekspres' => 'SiCepat'
        ];

        $displayCourier = $shortNames[$courierName] ?? $courierName;
        
        // Use description if available, otherwise use service
        $serviceDisplay = !empty($description) ? $description : $service;
        
        return $displayCourier . ' ' . $serviceDisplay;
    }

    /**
     * Format full service name for opsi_pengiriman column
     */
    private function formatFullServiceName($option)
    {
        $courierName = $option['name'] ?? '';
        $service = $option['service'] ?? '';
        $description = $option['description'] ?? '';
        
        // Shorten long courier names for cleaner display
        $shortNames = [
            'Jalur Nugraha Ekakurir (JNE)' => 'JNE',
            'J&T Express' => 'J&T',
            'SiCepat Ekspres' => 'SiCepat'
        ];

        $displayCourier = $shortNames[$courierName] ?? $courierName;
        
        // Use description if available, otherwise use service
        $serviceDisplay = !empty($description) ? $description : $service;
        
        // Return format: "JNE Regular Service" or "J&T Economy"
        return trim($displayCourier . ' ' . $serviceDisplay);
    }
}
