<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Keranjang;
use App\Models\Barang;

class KeranjangController extends Controller
{
    /**
     * Display cart items for the authenticated user
     * Includes product availability information
     */
    public function index()
    {
        $user = Auth::user();
        
        // Include additional information about product and store
        $cartItems = Keranjang::where('id_user', $user->id_user)
            ->with([
                'barang.gambarBarang', 
                'barang.toko'
            ])
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $cartItems
        ]);
    }
    
    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'id_barang' => 'required|exists:barang,id_barang',
            'jumlah' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if product exists and is available
        $barang = Barang::findOrFail($request->id_barang);
        
        if ($barang->status_barang != 'Tersedia' || $barang->is_deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak tersedia'
            ], 400);
        }
        
        // Check stock availability
        if ($barang->stok < $request->jumlah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stok tidak mencukupi'
            ], 400);
        }
        
        // Check if item already exists in cart
        $existingItem = Keranjang::where('id_user', $user->id_user)
            ->where('id_barang', $request->id_barang)
            ->first();
        
        if ($existingItem) {
            // Update existing cart item
            $newQuantity = $existingItem->jumlah + $request->jumlah;
            
            // Check if new quantity exceeds stock
            if ($newQuantity > $barang->stok) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Total jumlah melebihi stok yang tersedia'
                ], 400);
            }
            
            $existingItem->jumlah = $newQuantity;
            $existingItem->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Jumlah produk dalam keranjang diperbarui',
                'data' => $existingItem
            ]);
        } else {
            // Create new cart item
            $cartItem = new Keranjang();
            $cartItem->id_user = $user->id_user;
            $cartItem->id_barang = $request->id_barang;
            $cartItem->jumlah = $request->jumlah;
            $cartItem->is_selected = false;
            $cartItem->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil ditambahkan ke keranjang',
                'data' => $cartItem
            ], 201);
        }
    }
    
    /**
     * Update cart item (quantity or selection status)
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        // Find cart item
        $cartItem = Keranjang::where('id_keranjang', $id)
            ->where('id_user', $user->id_user)
            ->first();
        
        if (!$cartItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item tidak ditemukan dalam keranjang'
            ], 404);
        }
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'jumlah' => 'sometimes|required|integer|min:1',
            'is_selected' => 'sometimes|required|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Update quantity if provided
        if ($request->has('jumlah')) {
            // Check stock availability
            $barang = Barang::findOrFail($cartItem->id_barang);
            
            if ($barang->stok < $request->jumlah) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Jumlah melebihi stok yang tersedia'
                ], 400);
            }
            
            $cartItem->jumlah = $request->jumlah;
        }
        
        // Update selection status if provided
        if ($request->has('is_selected')) {
            $cartItem->is_selected = $request->is_selected;
        }
        
        $cartItem->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Item keranjang berhasil diperbarui',
            'data' => $cartItem
        ]);
    }
    
    /**
     * Remove item from cart
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        // Find cart item
        $cartItem = Keranjang::where('id_keranjang', $id)
            ->where('id_user', $user->id_user)
            ->first();
        
        if (!$cartItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item tidak ditemukan dalam keranjang'
            ], 404);
        }
        
        $cartItem->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Item berhasil dihapus dari keranjang'
        ]);
    }
    
    /**
     * Select all items in cart
     */
    public function selectAll(Request $request)
    {
        $user = Auth::user();
        
        $select = $request->has('select') ? $request->select : true;
        
        Keranjang::where('id_user', $user->id_user)
            ->update(['is_selected' => $select]);
        
        return response()->json([
            'status' => 'success',
            'message' => $select ? 'Semua item dipilih' : 'Semua item tidak dipilih'
        ]);
    }
    
    /**
     * Create purchase from selected cart items
     * Only include available items in checkout
     * Fixed to prevent creating empty orders
     */
    public function checkout(Request $request)
    {
        $user = Auth::user();
        
        // Log that we're starting the checkout process
        \Illuminate\Support\Facades\Log::info('Starting cart checkout', [
            'user_id' => $user->id_user,
            'request' => $request->all()
        ]);
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'id_alamat' => 'required|exists:alamat_user,id_alamat',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Get selected cart items
        $selectedItems = Keranjang::where('id_user', $user->id_user)
            ->where('is_selected', true)
            ->with(['barang.toko'])
            ->get();
        
        // Log number of selected items
        \Illuminate\Support\Facades\Log::info('Selected cart items', [
            'count' => $selectedItems->count()
        ]);
        
        if ($selectedItems->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada item yang dipilih untuk checkout'
            ], 400);
        }
        
        // Filter only available items
        $availableItems = $selectedItems->filter(function($item) {
            $barang = $item->barang;
            return $barang && $barang->status_barang == 'Tersedia' && !$barang->is_deleted && $barang->stok >= $item->jumlah;
        });
        
        // Log number of available items after filtering
        \Illuminate\Support\Facades\Log::info('Available items after filtering', [
            'count' => $availableItems->count()
        ]);
        
        if ($availableItems->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Semua item yang dipilih tidak tersedia atau stok habis'
            ], 400);
        }
        
        // Group items by store to check if we need multi-store checkout
        $itemsByStore = $availableItems->groupBy(function($item) {
            return $item->barang->id_toko;
        });
        
        $multipleStores = count($itemsByStore) > 1;
        
        // Log checkout type
        \Illuminate\Support\Facades\Log::info('Checkout type', [
            'multiple_stores' => $multipleStores,
            'store_count' => count($itemsByStore)
        ]);
        
        // If multiple stores, use the multi-store checkout flow
        if ($multipleStores) {
            return $this->processMultiStoreCheckout($request, $itemsByStore, $availableItems);
        }

        // Single store checkout flow
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Create a new purchase
            $pembelian = new \App\Models\Pembelian();
            $pembelian->id_pembeli = $user->id_user;
            $pembelian->id_alamat = $request->id_alamat;
            $pembelian->kode_pembelian = \App\Models\Pembelian::generateKodePembelian();
            $pembelian->status_pembelian = 'Draft';
            $pembelian->is_deleted = false;
            $pembelian->created_by = $user->id_user;
            $pembelian->save();
            
            \Illuminate\Support\Facades\Log::info('Purchase created', [
                'purchase_id' => $pembelian->id_pembelian,
                'code' => $pembelian->kode_pembelian
            ]);
            
            // Create purchase details from available selected cart items
            $detailCount = 0;
            foreach ($availableItems as $item) {
                // Add additional check to make sure the item has a valid product
                if (!$item->barang) {
                    \Illuminate\Support\Facades\Log::warning('Invalid product in cart item', [
                        'cart_item_id' => $item->id_keranjang
                    ]);
                    continue;
                }
                
                \App\Models\DetailPembelian::create([
                    'id_pembelian' => $pembelian->id_pembelian,
                    'id_barang' => $item->id_barang,
                    'id_toko' => $item->barang->id_toko,
                    'harga_satuan' => $item->barang->harga,
                    'jumlah' => $item->jumlah,
                    'subtotal' => $item->barang->harga * $item->jumlah
                ]);
                $detailCount++;
            }
            
            // Log number of details created
            \Illuminate\Support\Facades\Log::info('Purchase details created', [
                'count' => $detailCount
            ]);
            
            // Remove available selected items from cart
            if ($detailCount > 0) {
                $availableItemIds = $availableItems->pluck('id_keranjang')->toArray();
                Keranjang::whereIn('id_keranjang', $availableItemIds)->delete();
                
                \Illuminate\Support\Facades\Log::info('Removed items from cart', [
                    'count' => count($availableItemIds)
                ]);
            }
            
            // Check if there were any details created - if not, rollback transaction
            if ($detailCount === 0) {
                \Illuminate\Support\Facades\Log::error('No purchase details created');
                \Illuminate\Support\Facades\DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid items to checkout'
                ], 400);
            }
            
            // Verify again that the details were actually created
            $actualDetailCount = \App\Models\DetailPembelian::where('id_pembelian', $pembelian->id_pembelian)->count();
            if ($actualDetailCount === 0) {
                \Illuminate\Support\Facades\Log::error('Verification failed: No purchase details found in database');
                \Illuminate\Support\Facades\DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create purchase details'
                ], 500);
            }
            
            \Illuminate\Support\Facades\DB::commit();
            
            // If there were some unavailable items, notify the user
            if ($availableItems->count() < $selectedItems->count()) {
                $message = 'Checkout berhasil, beberapa item tidak tersedia atau stok habis';
            } else {
                $message = 'Checkout berhasil';
            }
            
            \Illuminate\Support\Facades\Log::info('Checkout completed successfully');
            
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'kode_pembelian' => $pembelian->kode_pembelian,
                    'id_pembelian' => $pembelian->id_pembelian
                ]
            ], 201);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollback();
            \Illuminate\Support\Facades\Log::error('Error during checkout: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process checkout for multiple stores from cart
     * Creates a single "master" order with a special flag for multi-store checkout
     */
    private function processMultiStoreCheckout(Request $request, $itemsByStore, $allItems)
    {
        $user = Auth::user();
        
        \Illuminate\Support\Facades\Log::info('Starting multi-store checkout from cart', [
            'store_count' => $itemsByStore->count(),
            'user_id' => $user->id_user,
            'address_id' => $request->id_alamat
        ]);
        
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Create a single "master" purchase that will contain all items
            $masterPurchase = new \App\Models\Pembelian();
            $masterPurchase->id_pembeli = $user->id_user;
            $masterPurchase->id_alamat = $request->id_alamat;
            $masterPurchase->kode_pembelian = \App\Models\Pembelian::generateKodePembelian();
            $masterPurchase->status_pembelian = 'Draft';
            $masterPurchase->is_deleted = false;
            $masterPurchase->created_by = $user->id_user;
            
            // Add metadata to purchase to indicate it's a multi-store purchase
            $masterPurchase->catatan_pembeli = json_encode([
                'is_multi_store' => true,
                'store_count' => $itemsByStore->count()
            ]);
            $masterPurchase->save();
            
            \Illuminate\Support\Facades\Log::info('Master purchase created for multi-store checkout', [
                'purchase_id' => $masterPurchase->id_pembelian,
                'code' => $masterPurchase->kode_pembelian,
                'address_id' => $masterPurchase->id_alamat
            ]);
            
            $totalDetailCount = 0;
            
            // Add all items to the master purchase
            foreach ($allItems as $item) {
                if (!$item->barang) {
                    \Illuminate\Support\Facades\Log::warning('Invalid product in cart item', [
                        'cart_item_id' => $item->id_keranjang
                    ]);
                    continue;
                }
                
                // Ensure product has a valid store
                if (!$item->barang->id_toko) {
                    \Illuminate\Support\Facades\Log::warning('Product has no store ID', [
                        'product_id' => $item->id_barang,
                        'product_name' => $item->barang->nama_barang ?? 'Unknown'
                    ]);
                    continue;
                }
                
                $detail = new \App\Models\DetailPembelian();
                $detail->id_pembelian = $masterPurchase->id_pembelian;
                $detail->id_barang = $item->id_barang;
                $detail->id_toko = $item->barang->id_toko;
                $detail->harga_satuan = $item->barang->harga;
                $detail->jumlah = $item->jumlah;
                $detail->subtotal = $item->barang->harga * $item->jumlah;
                $detail->save();
                
                $totalDetailCount++;
                
                \Illuminate\Support\Facades\Log::debug('Added detail to master purchase', [
                    'detail_id' => $detail->id_detail_pembelian,
                    'product' => $item->barang->nama_barang,
                    'store_id' => $item->barang->id_toko
                ]);
            }
            
            // Check if any details were created
            if ($totalDetailCount === 0) {
                \Illuminate\Support\Facades\Log::error('No purchase details created for multi-store checkout');
                \Illuminate\Support\Facades\DB::rollback();
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create purchase details'
                ], 500);
            }
            
            // Verify details were actually created
            $actualDetailCount = \App\Models\DetailPembelian::where('id_pembelian', $masterPurchase->id_pembelian)->count();
            if ($actualDetailCount === 0) {
                \Illuminate\Support\Facades\Log::error('Verification failed: No purchase details found in database');
                \Illuminate\Support\Facades\DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create purchase details'
                ], 500);
            }
            
            // Remove items from cart
            $itemIds = $allItems->pluck('id_keranjang')->toArray();
            Keranjang::whereIn('id_keranjang', $itemIds)->delete();
            
            \Illuminate\Support\Facades\Log::info('Removed items from cart', [
                'count' => count($itemIds)
            ]);
            
            \Illuminate\Support\Facades\DB::commit();
            
            \Illuminate\Support\Facades\Log::info('Multi-store checkout master purchase created successfully', [
                'purchase_code' => $masterPurchase->kode_pembelian,
                'detail_count' => $totalDetailCount,
                'store_count' => $itemsByStore->count()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Multiple store checkout created',
                'data' => [
                    'kode_pembelian' => $masterPurchase->kode_pembelian,
                    'id_pembelian' => $masterPurchase->id_pembelian,
                    'store_count' => $itemsByStore->count(),
                    'is_multi_store' => true
                ]
            ], 201);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollback();
            \Illuminate\Support\Facades\Log::error('Error during multi-store checkout: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Buy Now - Add item to cart and immediately checkout
     * Fixed to prevent creating empty orders
     */
    public function buyNow(Request $request)
    {
        $user = Auth::user();
        
        // Log that we're starting the Buy Now process
        \Illuminate\Support\Facades\Log::info('Starting Buy Now checkout', [
            'user_id' => $user->id_user,
            'request' => $request->except(['id_alamat']) // Don't log the address details
        ]);
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'id_barang' => 'required_without:product_slug|exists:barang,id_barang',
            'product_slug' => 'required_without:id_barang|string',
            'jumlah' => 'required|integer|min:1',
            'id_alamat' => 'required|exists:alamat_user,id_alamat',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find product by ID or slug
        $barang = null;
        try {
            if ($request->has('id_barang')) {
                $barang = Barang::findOrFail($request->id_barang);
                \Illuminate\Support\Facades\Log::info('Product found by ID', [
                    'id' => $request->id_barang,
                    'name' => $barang->nama_barang
                ]);
            } else {
                $barang = Barang::where('slug', $request->product_slug)->firstOrFail();
                \Illuminate\Support\Facades\Log::info('Product found by slug', [
                    'slug' => $request->product_slug,
                    'name' => $barang->nama_barang
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Product not found', [
                'id' => $request->id_barang,
                'slug' => $request->product_slug,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Check if product is available
        if ($barang->status_barang != 'Tersedia' || $barang->is_deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak tersedia'
            ], 400);
        }
        
        // Check stock availability
        if ($barang->stok < $request->jumlah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stok tidak mencukupi'
            ], 400);
        }

        // Verify product belongs to a store
        if (!$barang->id_toko) {
            \Illuminate\Support\Facades\Log::error('Product has no store assigned', [
                'product_id' => $barang->id_barang
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Product has no valid store'
            ], 400);
        }
        
        // Use DB transaction
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Skip the cart part - let's directly create the purchase
            
            // Create a new purchase
            $pembelian = new \App\Models\Pembelian();
            $pembelian->id_pembeli = $user->id_user;
            $pembelian->id_alamat = $request->id_alamat;
            $pembelian->kode_pembelian = \App\Models\Pembelian::generateKodePembelian();
            $pembelian->status_pembelian = 'Draft';
            $pembelian->is_deleted = false;
            $pembelian->created_by = $user->id_user;
            $pembelian->save();
            
            \Illuminate\Support\Facades\Log::info('Purchase created', [
                'purchase_id' => $pembelian->id_pembelian,
                'code' => $pembelian->kode_pembelian
            ]);
            
            // Create purchase detail
            $detail = new \App\Models\DetailPembelian();
            $detail->id_pembelian = $pembelian->id_pembelian;
            $detail->id_barang = $barang->id_barang;
            $detail->id_toko = $barang->id_toko;
            $detail->harga_satuan = $barang->harga;
            $detail->jumlah = $request->jumlah;
            $detail->subtotal = $barang->harga * $request->jumlah;
            $detail->save();
            
            \Illuminate\Support\Facades\Log::info('Purchase detail created', [
                'detail_id' => $detail->id_detail_pembelian
            ]);
            
            // Verify details were created
            $actualDetailCount = \App\Models\DetailPembelian::where('id_pembelian', $pembelian->id_pembelian)->count();
            if ($actualDetailCount === 0) {
                \Illuminate\Support\Facades\Log::error('Verification failed: No purchase details found in database');
                \Illuminate\Support\Facades\DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create purchase details'
                ], 500);
            }
            
            \Illuminate\Support\Facades\DB::commit();
            
            \Illuminate\Support\Facades\Log::info('Buy Now completed successfully');
            
            return response()->json([
                'status' => 'success',
                'message' => 'Buy Now successful',
                'data' => [
                    'kode_pembelian' => $pembelian->kode_pembelian,
                    'id_pembelian' => $pembelian->id_pembelian
                ]
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollback();
            \Illuminate\Support\Facades\Log::error('Error during Buy Now: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Buy now failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
