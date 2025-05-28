<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\TokoController;
use App\Http\Controllers\User\BarangController;
use App\Http\Controllers\User\GambarBarangController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\TokoManagementController;
use App\Http\Controllers\Admin\KategoriController;
use App\Http\Controllers\Admin\BarangManagementController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\User\AlamatUserController;
use App\Http\Controllers\User\AlamatTokoController;
use App\Http\Controllers\User\PembelianController;
use App\Http\Controllers\User\DetailPembelianController;
use App\Http\Controllers\User\TagihanController;
use App\Http\Controllers\User\KeranjangController;
use App\Http\Controllers\User\PesananTokoController;
use App\Http\Controllers\Admin\PesananManagementController;
use App\Http\Controllers\Admin\PaymentManagementController;
use App\Http\Controllers\Admin\KomplainManagementController;
use App\Http\Controllers\User\ChatOfferController;

// Debug endpoint for checking auth status
Route::middleware('auth:sanctum')->get('/auth-check', function (Request $request) {
    $user = auth()->user();
    return response()->json([
        'authenticated' => true,
        'user' => [
            'id_user' => $user->id_user,
            'email' => $user->email,
            'role' => $user->role_name
        ]
    ]);
});

// Auth routes are imported from another file
require __DIR__.'/auth.php';

// Broadcasting authentication - make sure this works properly
Route::post('/broadcasting/auth', function (Request $request) {
    try {
        \Log::info('Broadcasting auth request received', [
            'has_auth_header' => $request->hasHeader('Authorization'),
            'has_cookie' => $request->hasHeader('Cookie'),
            'has_csrf_token' => $request->hasHeader('X-XSRF-TOKEN'),
            'session_id' => session()->getId(),
            'auth_check' => auth()->check(),
            'user_id' => auth()->check() ? auth()->user()->id_user : null,
            'body' => $request->all(),
            'cookies' => $request->cookies->all(),
            'headers' => [
                'cookie' => $request->header('Cookie'),
                'authorization' => $request->header('Authorization'),
                'x-xsrf-token' => $request->header('X-XSRF-TOKEN'),
                'user-agent' => $request->header('User-Agent'),
                'referer' => $request->header('Referer'),
                'origin' => $request->header('Origin'),
            ]
        ]);
        
        // Start the session manually if needed
        if (!session()->isStarted()) {
            session()->start();
        }
        
        // First check if user is authenticated
        if (!auth()->check()) {
            \Log::warning('User not authenticated for broadcasting', [
                'session_id' => session()->getId(),
                'session_data' => session()->all(),
                'guard' => config('auth.defaults.guard'),
                'provider' => config('auth.defaults.provider')
            ]);
            
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'User session not found or expired',
                'debug' => [
                    'session_id' => session()->getId(),
                    'session_started' => session()->isStarted(),
                    'has_cookies' => !empty($request->cookies->all())
                ]
            ], 401);
        }
        
        $user = auth()->user();
        \Log::info('User authenticated for broadcasting', [
            'user_id' => $user->id_user,
            'user_name' => $user->name
        ]);
        
        // Use Laravel's built-in broadcast auth
        $result = \Illuminate\Support\Facades\Broadcast::auth($request);
        \Log::info('Broadcast auth successful', ['result' => $result]);
        
        return $result;
    } catch (\Exception $e) {
        \Log::error('Broadcasting auth exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        
        return response()->json([
            'error' => 'Authentication failed', 
            'message' => $e->getMessage(),
            'debug' => config('app.debug') ? [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ] : null
        ], 403);
    }
})->middleware(['auth:sanctum'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Public routes - no auth required
Route::prefix('toko')->group(function() {
    // Public store access by slug
    Route::get('/slug/{slug}', [TokoController::class, 'getBySlug']);
});

// Public Product Routes
Route::get('/featured-products', [App\Http\Controllers\User\BarangController::class, 'getFeaturedProducts']);                                                                                                                                         
Route::get('/recommended-products', [App\Http\Controllers\User\BarangController::class, 'getRecommendedProducts']);

// Add a public kategori endpoint for the frontend
Route::get('/kategori', [App\Http\Controllers\User\KategoriController::class, 'index']);

// Public product catalog routes
Route::get('/products', [BarangController::class, 'getPublicProducts']);
Route::get('/products/{slug}', [BarangController::class, 'getPublicProductBySlug']);

// Region routes
Route::get('/provinces', [RegionController::class, 'getProvinces']);
Route::get('/provinces/{id}/regencies', [RegionController::class, 'getRegencies']);
Route::get('/regencies/{id}/districts', [RegionController::class, 'getDistricts']);
Route::get('/districts/{id}/villages', [RegionController::class, 'getVillages']);

// Public Midtrans notification callback
Route::post('/payments/callback', [TagihanController::class, 'callback']);

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('/user/profile', [UserController::class, 'getCurrentUser']);
    Route::get('/auth/me', [UserController::class, 'getCurrentUser']); // Alternative endpoint
    
    // Toko (Store) management for regular users
    Route::prefix('toko')->group(function() {
        // Get my store (based on authenticated user)
        Route::get('/my-store', [TokoController::class, 'getMyStore']);
        
        // Get store by ID (when ID is known)
        Route::get('/{id}', [TokoController::class, 'getById'])->where('id', '[0-9]+');
        
        // Store CRUD operations
        Route::post('/', [TokoController::class, 'store']);
        Route::put('/', [TokoController::class, 'update']);
        Route::delete('/', [TokoController::class, 'destroy']);
    });
    
    // Barang (Product) management for users
    Route::prefix('barang')->group(function() {
        Route::get('/', [BarangController::class, 'index']);
        Route::post('/', [BarangController::class, 'store']);
        Route::get('/slug/{slug}', [BarangController::class, 'getBySlug']);
        Route::get('/{id}', [BarangController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/slug/{slug}', [BarangController::class, 'updateBySlug']);
        Route::put('/{id}', [BarangController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/slug/{slug}', [BarangController::class, 'destroyBySlug']);
        Route::delete('/{id}', [BarangController::class, 'destroy'])->where('id', '[0-9]+');
            
        // Update to support both ID and slug-based parent routes
        Route::get('/{id_barang}/gambar', [GambarBarangController::class, 'index'])->where('id_barang', '[0-9]+');
        Route::get('/slug/{slug}/gambar', [GambarBarangController::class, 'indexByBarangSlug']);
        Route::post('/{id_barang}/gambar', [GambarBarangController::class, 'store'])->where('id_barang', '[0-9]+');
        Route::post('/slug/{slug}/gambar', [GambarBarangController::class, 'storeByBarangSlug']);
        Route::put('/{id_barang}/gambar/{id_gambar}', [GambarBarangController::class, 'update'])->where('id_barang', '[0-9]+');
        Route::put('/slug/{slug}/gambar/{id_gambar}', [GambarBarangController::class, 'updateByBarangSlug']);
        Route::delete('/{id_barang}/gambar/{id_gambar}', [GambarBarangController::class, 'destroy'])->where('id_barang', '[0-9]+');
        Route::delete('/slug/{slug}/gambar/{id_gambar}', [GambarBarangController::class, 'destroyByBarangSlug']); // Add this line
    });
    
    // User Address Management
    Route::get('/user/addresses', [AlamatUserController::class, 'index']);
    Route::get('/user/addresses/{id}', [AlamatUserController::class, 'show']);
    Route::post('/user/addresses', [AlamatUserController::class, 'store']);
    Route::put('/user/addresses/{id}', [AlamatUserController::class, 'update']);
    Route::delete('/user/addresses/{id}', [AlamatUserController::class, 'destroy']);
    Route::put('/user/addresses/{id}/primary', [AlamatUserController::class, 'setPrimary']);
    
    // Store Address Management
    Route::get('/toko/addresses', [AlamatTokoController::class, 'index']);
    Route::get('/toko/addresses/{id}', [AlamatTokoController::class, 'show']);
    Route::post('/toko/addresses', [AlamatTokoController::class, 'store']);
    Route::put('/toko/addresses/{id}', [AlamatTokoController::class, 'update']);
    Route::delete('/toko/addresses/{id}', [AlamatTokoController::class, 'destroy']);
    Route::patch('/toko/addresses/{id}/primary', [AlamatTokoController::class, 'setPrimary']);

    // Purchase Management
    Route::prefix('purchases')->group(function() {
        Route::get('/', [PembelianController::class, 'index']);
        Route::post('/', [PembelianController::class, 'store']);
        Route::get('/{kode}', [PembelianController::class, 'show']);
        Route::post('/{kode}/checkout', [PembelianController::class, 'checkout']);
        Route::post('/{kode}/multi-checkout', [PembelianController::class, 'multiCheckout']); // Add multi-checkout route here
        Route::put('/{kode}/cancel', [PembelianController::class, 'cancel']);
        Route::put('/{kode}/confirm-delivery', [PembelianController::class, 'confirmDelivery']); // Add this new route
        Route::put('/{kode}/complete', [PembelianController::class, 'completePurchase']);
        
        // Purchase Details Management
        Route::get('/{kode}/items', [DetailPembelianController::class, 'index']);
        Route::post('/{kode}/items', [DetailPembelianController::class, 'store']);
        Route::get('/{kode}/items/{id}', [DetailPembelianController::class, 'show']);
        Route::put('/{kode}/items/{id}', [DetailPembelianController::class, 'update']);
        Route::delete('/{kode}/items/{id}', [DetailPembelianController::class, 'destroy']);
    });
    
    // Payment Management
    Route::prefix('payments')->group(function() {
        Route::get('/', [TagihanController::class, 'getAll']); 
        Route::get('/{kode}', [TagihanController::class, 'show']);
        Route::post('/{kode}/process', [TagihanController::class, 'processPayment']);
        Route::get('/{kode}/status', [TagihanController::class, 'checkStatus']);
    });

    // Cart Management
    Route::prefix('cart')->group(function() {
        Route::get('/', [KeranjangController::class, 'index']);
        Route::post('/', [KeranjangController::class, 'store']);
        Route::put('/{id}', [KeranjangController::class, 'update']);
        Route::delete('/{id}', [KeranjangController::class, 'destroy']);
        Route::post('/select-all', [KeranjangController::class, 'selectAll']);
        Route::post('/checkout', [KeranjangController::class, 'checkout']);
        Route::post('/buy-now', [KeranjangController::class, 'buyNow']);
    });

    // Seller Order Management Routes
    Route::middleware(['auth:sanctum', 'verified'])->prefix('seller')->group(function () {
        // List all orders for seller's shop
        Route::get('/orders', [PesananTokoController::class, 'index']);
        
        // Get order statistics
        Route::get('/orders/stats', [PesananTokoController::class, 'getOrderStats']);
        
        // Get individual order details
        Route::get('/orders/{kode}', [PesananTokoController::class, 'show']);
        
        // Confirm receipt of the order and move to 'Diproses' status
        Route::post('/orders/{kode}/confirm', [PesananTokoController::class, 'confirmOrder']);
        
        // Ship an order and add shipping information
        Route::post('/orders/{kode}/ship', [PesananTokoController::class, 'shipOrder']);
    });

    // Review Management
    Route::prefix('reviews')->group(function() {
        Route::post('/{id_pembelian}', [App\Http\Controllers\User\ReviewController::class, 'store']);
        Route::get('/{id_pembelian}', [App\Http\Controllers\User\ReviewController::class, 'show']);
        Route::delete('/{id_review}', [App\Http\Controllers\User\ReviewController::class, 'destroy']);
        Route::get('/purchase/{id_pembelian}', [App\Http\Controllers\User\ReviewController::class, 'getByPembelian']);
    });

    // Complaint Management
    Route::middleware('auth:sanctum')->group(function () {
        // Make sure these routes are not nested under another group
        Route::prefix('komplain')->group(function() {
            Route::post('/{id_pembelian}', [App\Http\Controllers\User\KomplainController::class, 'store']);
            Route::get('/{id_pembelian}', [App\Http\Controllers\User\KomplainController::class, 'show']);
            Route::put('/{id_komplain}', [App\Http\Controllers\User\KomplainController::class, 'update']);
            Route::get('/user/list', [App\Http\Controllers\User\KomplainController::class, 'getByUser']);
        });
    });

    // User Retur Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('retur')->group(function() {
            Route::post('/', [App\Http\Controllers\User\ReturBarangController::class, 'store']);
            Route::get('/{id_retur}', [App\Http\Controllers\User\ReturBarangController::class, 'show']);
            Route::get('/user/list', [App\Http\Controllers\User\ReturBarangController::class, 'getByUser']);
        });
    });

    // Admin routes
    Route::middleware('role:admin,superadmin')->group(function() {  
        // User management (admin only)
        Route::prefix('users')->group(function() {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/{id}', [UserManagementController::class, 'show']);
            Route::put('/{id}', [UserManagementController::class, 'update']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });
        
        // Toko management (admin only)
        Route::prefix('admin/toko')->group(function() {
            Route::get('/', [TokoManagementController::class, 'index']);
            Route::get('/{id}', [TokoManagementController::class, 'show']);
            Route::put('/{id}', [TokoManagementController::class, 'update']);
            Route::delete('/{id}', [TokoManagementController::class, 'destroy']);
            Route::put('/{id}/soft-delete', [TokoManagementController::class, 'softDelete']);
            Route::put('/{id}/restore', [TokoManagementController::class, 'restore']);
        });
        
        // Kategori management (admin only)
        Route::prefix('admin/kategori')->group(function() {
            Route::get('/', [KategoriController::class, 'index']);
            Route::post('/', [KategoriController::class, 'store']);
            Route::get('/{id}', [KategoriController::class, 'show']);
            Route::put('/{id}', [KategoriController::class, 'update']);
            Route::delete('/{id}', [KategoriController::class, 'destroy']);
        });

        // Admin product management (admin only)
        Route::prefix('admin/barang')->group(function() {
            Route::get('/', [BarangManagementController::class, 'index']);
            Route::get('/filter', [BarangManagementController::class, 'filter']);
            Route::get('/categories', [BarangManagementController::class, 'getCategories']);
            Route::get('/{id}', [BarangManagementController::class, 'show'])->where('id', '[0-9]+');
            Route::get('/slug/{slug}', [BarangManagementController::class, 'showBySlug']);
            Route::put('/{id}', [BarangManagementController::class, 'update'])->where('id', '[0-9]+');
            Route::put('/{id}/soft-delete', [BarangManagementController::class, 'softDelete'])->where('id', '[0-9]+');
            Route::put('/{id}/restore', [BarangManagementController::class, 'restore'])->where('id', '[0-9]+');
            Route::delete('/{id}', [BarangManagementController::class, 'destroy'])->where('id', '[0-9]+');
        });

        // Admin order management (admin only)
        Route::prefix('admin/pesanan')->group(function() {
            Route::get('/', [PesananManagementController::class, 'index']);
            Route::get('/stats', [PesananManagementController::class, 'getOrderStats']);
            Route::get('/{kode}', [PesananManagementController::class, 'show']);
            Route::put('/{kode}/status', [PesananManagementController::class, 'updateStatus']);
            Route::post('/{kode}/comment', [PesananManagementController::class, 'addComment']);
        });

        // Admin payment management (admin only)
        Route::prefix('admin/payments')->group(function() {
            Route::get('/', [PaymentManagementController::class, 'index']);
            Route::get('/stats', [PaymentManagementController::class, 'getPaymentStats']);
            Route::get('/{kode}', [PaymentManagementController::class, 'show']);
            Route::put('/{kode}/status', [PaymentManagementController::class, 'updateStatus']);
            Route::post('/{kode}/refund', [PaymentManagementController::class, 'processRefund']);
            Route::post('/{kode}/verify', [PaymentManagementController::class, 'verifyManually']);
        });
        
        // Admin complaint management
        Route::prefix('admin/komplain')->group(function() {
            Route::get('/', [KomplainManagementController::class, 'index']);
            Route::get('/stats', [KomplainManagementController::class, 'getComplaintStats']);
            Route::get('/{id_komplain}', [KomplainManagementController::class, 'show']);
            Route::post('/{id_komplain}/process', [KomplainManagementController::class, 'processComplaint']);
            Route::post('/{id_komplain}/comment', [KomplainManagementController::class, 'addComment']);
        });

        // Admin retur management
        Route::prefix('admin/retur')->group(function() {
            Route::get('/', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'getReturStats']);
            Route::get('/{id_retur}', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'show']);
            Route::post('/{id_retur}/process', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'processRetur']);
        });
    });

    // Debug endpoints
    Route::middleware('auth:sanctum')->group(function() {
        // Debug endpoint to check purchase details directly
        Route::get('/debug/purchases/{kode}', function($kode) {
            $user = auth()->user();
            
            // Check if purchase exists
            $purchase = \App\Models\Pembelian::where('kode_pembelian', $kode)
                ->where('id_pembeli', $user->id_user)
                ->first();
            
            if (!$purchase) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase not found'
                ], 404);
            }
            
            // Check if detail pembelian exists
            $details = \App\Models\DetailPembelian::where('id_pembelian', $purchase->id_pembelian)
                ->with(['barang.gambarBarang', 'toko'])
                ->get();
            
            return response()->json([
                'status' => 'success',
                'purchase' => $purchase,
                'details_count' => $details->count(),
                'details' => $details
            ]);
        });

        // New debug endpoint to fetch purchase by ID
        Route::get('/debug/purchases/by-id/{id}', function($id) {
            $user = auth()->user();
            
            // Check if purchase exists
            $purchase = \App\Models\Pembelian::where('id_pembelian', $id)
                ->where('id_pembeli', $user->id_user)
                ->first();
            
            if (!$purchase) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase not found'
                ], 404);
            }
            
            // Check if detail pembelian exists
            $details = \App\Models\DetailPembelian::where('id_pembelian', $purchase->id_pembelian)
                ->with(['barang.gambarBarang', 'toko'])
                ->get();
            
            return response()->json([
                'status' => 'success',
                'purchase' => $purchase,
                'details_count' => $details->count(),
                'details' => $details
            ]);
        });

        // Debug routes for payment
        Route::get('/debug/midtrans-config', [App\Http\Controllers\User\TagihanController::class, 'debugMidtransConfig']);
    });

    // Chat and Offers Routes
    Route::middleware('auth:sanctum')->group(function () {
        // Chat room management
        Route::get('/chat', [App\Http\Controllers\User\RuangChatController::class, 'index']);
        Route::post('/chat', [App\Http\Controllers\User\RuangChatController::class, 'store']);
        Route::get('/chat/{id}', [App\Http\Controllers\User\RuangChatController::class, 'show']);
        Route::put('/chat/{id}', [App\Http\Controllers\User\RuangChatController::class, 'update']);
        Route::delete('/chat/{id}', [App\Http\Controllers\User\RuangChatController::class, 'destroy']);
        Route::patch('/chat/{id}/mark-read', [App\Http\Controllers\User\RuangChatController::class, 'markAsRead']);
        
        // Messages within chat rooms
        Route::get('/chat/{chatRoomId}/messages', [App\Http\Controllers\User\PesanController::class, 'index']);
        Route::post('/chat/{chatRoomId}/messages', [App\Http\Controllers\User\PesanController::class, 'store']);
        Route::put('/chat/messages/{id}', [App\Http\Controllers\User\PesanController::class, 'update']);
        Route::patch('/chat/messages/{id}/read', [App\Http\Controllers\User\PesanController::class, 'markAsRead']);
        
        // Offer routes
        Route::post('/chat/{roomId}/offers', [ChatOfferController::class, 'store']);
        Route::post('/chat/offers/{messageId}/respond', [ChatOfferController::class, 'respond']);
        Route::get('/chat/offers/{messageId}/check-purchase', [ChatOfferController::class, 'checkExistingPurchase']);
        Route::post('/chat/offers/{messageId}/purchase', [ChatOfferController::class, 'createPurchaseFromOffer']);
    });
});

