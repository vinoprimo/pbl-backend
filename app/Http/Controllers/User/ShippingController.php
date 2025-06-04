<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\AlamatUser;
use App\Models\AlamatToko;
use App\Models\Barang;
use App\Services\RajaOngkirService;

class ShippingController extends Controller
{
    private $rajaOngkirService;

    public function __construct(RajaOngkirService $rajaOngkirService)
    {
        $this->rajaOngkirService = $rajaOngkirService;
    }

    /**
     * Calculate shipping cost using RajaOngkir API
     */
    public function calculateShippingCost(Request $request)
    {
        $user = Auth::user();
        
        // Debug: Log the API key configuration
        \Log::info('Shipping calculation started', [
            'user_id' => $user->id_user,
            'request_data' => $request->all(),
            'rajaongkir_key_configured' => !empty(config('services.rajaongkir.api_key')),
            'env_key_configured' => !empty(env('RAJAONGKIR_API_KEY')),
            'api_key_preview' => config('services.rajaongkir.api_key') ? substr(config('services.rajaongkir.api_key'), 0, 8) . '...' : 'null'
        ]);
        
        $validator = Validator::make($request->all(), [
            'id_toko' => 'required|exists:toko,id_toko',
            'id_alamat' => 'required|exists:alamat_user,id_alamat',
            'products' => 'required|array',
            'products.*.id_barang' => 'required|exists:barang,id_barang',
            'products.*.quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get store address with better error handling
            $storeAddress = AlamatToko::where('id_toko', $request->id_toko)
                ->where('is_primary', true)
                ->first();

            if (!$storeAddress) {
                // If no primary address found, get any address for this store
                $storeAddress = AlamatToko::where('id_toko', $request->id_toko)->first();
                
                if (!$storeAddress) {
                    \Log::error('No store address found', [
                        'id_toko' => $request->id_toko,
                        'available_addresses' => AlamatToko::where('id_toko', $request->id_toko)->count()
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Store address not found. Please set up store address first.'
                    ], 404);
                }
                
                \Log::warning('Using non-primary store address', [
                    'id_toko' => $request->id_toko,
                    'address_id' => $storeAddress->id_alamat_toko
                ]);
            }

            // Validate store address has postal code
            if (empty($storeAddress->kode_pos)) {
                \Log::error('Store address missing postal code', [
                    'id_toko' => $request->id_toko,
                    'address_id' => $storeAddress->id_alamat_toko
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store address is missing postal code'
                ], 400);
            }

            // Get user address and verify ownership
            $userAddress = AlamatUser::where('id_alamat', $request->id_alamat)
                ->where('id_user', $user->id_user)
                ->first();

            if (!$userAddress) {
                \Log::error('User address not found or unauthorized', [
                    'id_alamat' => $request->id_alamat,
                    'user_id' => $user->id_user
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'User address not found or unauthorized'
                ], 404);
            }

            // Validate user address has postal code
            if (empty($userAddress->kode_pos)) {
                \Log::error('User address missing postal code', [
                    'id_alamat' => $request->id_alamat,
                    'user_id' => $user->id_user
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Delivery address is missing postal code'
                ], 400);
            }

            // Calculate total weight - gunakan data asli tanpa konversi
            $totalWeight = 0;
            foreach ($request->products as $productData) {
                $barang = Barang::find($productData['id_barang']);
                if ($barang) {
                    // Gunakan berat_barang langsung (sudah dalam gram)
                    // Default ke 500 gram jika tidak ada data berat
                    $weightInGrams = $barang->berat_barang ? $barang->berat_barang : 500;
                    $totalWeight += $weightInGrams * $productData['quantity'];
                }
            }

            // Minimum weight 1000 grams (1kg)
            $totalWeight = max($totalWeight, 1000);

            \Log::info('Calling RajaOngkir with parameters', [
                'origin_postal_code' => $storeAddress->kode_pos,
                'destination_postal_code' => $userAddress->kode_pos,
                'total_weight' => $totalWeight,
                'courier' => 'jne:jnt:sicepat', // Updated to show multiple couriers
                'store_address_details' => [
                    'id_alamat_toko' => $storeAddress->id_alamat_toko,
                    'nama_pengirim' => $storeAddress->nama_pengirim,
                    'alamat_lengkap' => $storeAddress->alamat_lengkap,
                    'is_primary' => $storeAddress->is_primary
                ],
                'user_address_details' => [
                    'id_alamat' => $userAddress->id_alamat,
                    'nama_penerima' => $userAddress->nama_penerima,
                    'alamat_lengkap' => $userAddress->alamat_lengkap
                ],
                'product_weights' => array_map(function($productData) {
                    $barang = Barang::find($productData['id_barang']);
                    return [
                        'id_barang' => $productData['id_barang'],
                        'quantity' => $productData['quantity'],
                        'weight_per_item' => $barang ? $barang->berat_barang : 500,
                        'total_weight' => ($barang ? $barang->berat_barang : 500) * $productData['quantity']
                    ];
                }, $request->products)
            ]); // Added missing closing bracket and semicolon

            // Call RajaOngkir API with multiple couriers
            $result = $this->rajaOngkirService->calculateDomesticCost(
                $storeAddress->kode_pos,
                $userAddress->kode_pos,
                $totalWeight,
                'jne:jnt:sicepat'  // Multiple couriers untuk opsi yang lebih banyak
            );

            if (!$result['success']) {
                \Log::error('RajaOngkir API failed', [
                    'result' => $result,
                    'origin' => $storeAddress->kode_pos,
                    'destination' => $userAddress->kode_pos
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 500);
            }

            // Format shipping options with better error handling
            try {
                $shippingOptions = $this->rajaOngkirService->formatShippingOptions($result['data']);

                if (empty($shippingOptions)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No shipping options available for this route'
                    ], 404);
                }

                \Log::info('Shipping calculation successful', [
                    'options_count' => count($shippingOptions),
                    'total_weight' => $totalWeight,
                    'origin' => $storeAddress->kode_pos,
                    'destination' => $userAddress->kode_pos
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'shipping_options' => $shippingOptions,
                        'total_weight' => $totalWeight,
                        'origin' => $storeAddress->kode_pos,
                        'destination' => $userAddress->kode_pos
                    ]
                ]);
            } catch (\Exception $formatError) {
                \Log::error('Error formatting shipping options', [
                    'error' => $formatError->getMessage(),
                    'raw_data' => $result['data']
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to format shipping options'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Shipping calculation error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate shipping cost: ' . $e->getMessage()
            ], 500);
        }
    }
}
