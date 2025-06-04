<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Pembelian;
use App\Models\DetailPembelian;
use App\Models\Tagihan;
use App\Models\Barang;
use App\Models\AlamatUser;
use App\Models\AlamatToko;
use App\Models\Toko;
use App\Services\RajaOngkirService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class PembelianController extends Controller
{
    private $rajaOngkirService;

    public function __construct(RajaOngkirService $rajaOngkirService)
    {
        $this->rajaOngkirService = $rajaOngkirService;
    }

    /**
     * Display a listing of purchases for the authenticated user
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            // Query purchases with eager loading
            $purchases = Pembelian::where('id_pembeli', $user->id_user)
                ->with([
                    'alamat',
                    'detailPembelian.barang.gambarBarang',
                    'tagihan'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Ensure $purchases is always an array even if empty
            $result = $purchases->toArray();
            
            // Log the number of purchases for debugging
            \Log::info('User purchases fetched', [
                'user_id' => $user->id_user,
                'count' => count($result)
            ]);
            
            return response()->json([
                'status' => 'success',
                'data' => $result // Ensure we're returning an array
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching purchases: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load purchases: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get purchase by code
     */
    public function show(string $kode): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $purchase = Pembelian::with([
                'pembeli',
                'alamat.province',
                'alamat.regency', 
                'alamat.district',
                'alamat.village',
                'detailPembelian.barang.gambar_barang',
                'detailPembelian.barang.toko.alamat_toko.province',
                'detailPembelian.barang.toko.alamat_toko.regency',
                'detailPembelian.barang.toko.alamat_toko.district',
                'detailPembelian.toko.alamat_toko.province',
                'detailPembelian.toko.alamat_toko.regency',
                'detailPembelian.toko.alamat_toko.district',
                'detailPembelian.pesanPenawaran',
                'detailPembelian.pengiriman_pembelian',
                'tagihan',
                'review',
                'komplain' => function($query) {
                    $query->with('retur'); // Eager load retur relationship
                }
            ])
            ->where('kode_pembelian', $kode)
            ->where('id_pembeli', $user->id_user)
            ->first();

            if (!$purchase) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Purchase not found'
                ], 404);
            }

            // Convert to array for processing
            $purchaseData = $purchase->toArray();
            
            // Process each detail_pembelian to ensure we have complete store information
            if ($purchaseData['detail_pembelian']) {
                foreach ($purchaseData['detail_pembelian'] as &$detail) {
                    // Mark if this item is from an offer
                    $detail['is_from_offer'] = !is_null($detail['id_pesan']);
                    
                    // Calculate savings if from offer
                    if ($detail['is_from_offer'] && isset($detail['barang']['harga'])) {
                        $detail['offer_price'] = $detail['harga_satuan'];
                        $detail['original_price'] = $detail['barang']['harga'];
                        $detail['savings'] = ($detail['barang']['harga'] - $detail['harga_satuan']) * $detail['jumlah'];
                    }

                    // Ensure complete store information is available
                    if (!isset($detail['toko']) || empty($detail['toko'])) {
                        // Load store details manually if not loaded through relationship
                        $storeDetails = Toko::with([
                            'alamat_toko.province',
                            'alamat_toko.regency',
                            'alamat_toko.district'
                        ])->find($detail['id_toko']);
                        
                        if ($storeDetails) {
                            $detail['toko'] = [
                                'id_toko' => $storeDetails->id_toko,
                                'id_user' => $storeDetails->id_user,
                                'nama_toko' => $storeDetails->nama_toko,
                                'slug' => $storeDetails->slug,
                                'deskripsi' => $storeDetails->deskripsi,
                                'alamat' => $storeDetails->alamat,
                                'kontak' => $storeDetails->kontak,
                                'is_active' => $storeDetails->is_active,
                                'alamat_toko' => $storeDetails->alamat_toko->map(function($alamat) {
                                    return [
                                        'id_alamat_toko' => $alamat->id_alamat_toko,
                                        'nama_pengirim' => $alamat->nama_pengirim,
                                        'no_telepon' => $alamat->no_telepon,
                                        'alamat_lengkap' => $alamat->alamat_lengkap,
                                        'kode_pos' => $alamat->kode_pos,
                                        'is_primary' => $alamat->is_primary,
                                        'province' => $alamat->province ? [
                                            'id' => $alamat->province->id,
                                            'name' => $alamat->province->name
                                        ] : null,
                                        'regency' => $alamat->regency ? [
                                            'id' => $alamat->regency->id,
                                            'name' => $alamat->regency->name
                                        ] : null,
                                        'district' => $alamat->district ? [
                                            'id' => $alamat->district->id,
                                            'name' => $alamat->district->name
                                        ] : null,
                                    ];
                                })->toArray()
                            ];
                        } else {
                            // Fallback if store not found
                            $detail['toko'] = [
                                'id_toko' => $detail['id_toko'],
                                'nama_toko' => "Store {$detail['id_toko']}",
                                'slug' => null,
                                'deskripsi' => null,
                                'alamat' => null,
                                'kontak' => null,
                                'is_active' => true,
                                'alamat_toko' => []
                            ];
                        }
                    }

                    // Also ensure barang has toko data for consistency
                    if (isset($detail['barang']) && (!isset($detail['barang']['toko']) || empty($detail['barang']['toko']))) {
                        $detail['barang']['toko'] = $detail['toko'];
                    }

                    // Ensure barang has id_toko for consistency
                    if (isset($detail['barang']) && !isset($detail['barang']['id_toko'])) {
                        $detail['barang']['id_toko'] = $detail['id_toko'];
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $purchaseData
            ]);

        } catch (\Exception $e) {
            \Log::error("Error fetching purchase {$kode}: {$e->getMessage()}");
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch purchase details'
            ], 500);
        }
    }
    
    /**
     * Create a new purchase (direct buy)
     * Fixed to prevent creating empty orders
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Validate request
        $validator = Validator::make($request->all(), [
            // Accept either id_barang or product_slug
            'id_barang' => 'required_without:product_slug|exists:barang,id_barang',
            'product_slug' => 'required_without:id_barang|string',
            'jumlah' => 'required|integer|min:1',
            'id_alamat' => 'required|exists:alamat_user,id_alamat',
            'catatan_pembeli' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Get the product by ID or slug - with improved logging
        $barang = null;
        try {
            if ($request->has('id_barang')) {
                $barang = Barang::findOrFail($request->id_barang);
                \Log::info('Product found by ID', ['id' => $request->id_barang, 'name' => $barang->nama_barang]);
            } else {
                $barang = Barang::where('slug', $request->product_slug)->firstOrFail();
                \Log::info('Product found by slug', ['slug' => $request->product_slug, 'name' => $barang->nama_barang]);
            }
        } catch (\Exception $e) {
            \Log::error('Product not found', [
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
        
        // Check if address belongs to user
        $alamat = AlamatUser::where('id_alamat', $request->id_alamat)
                          ->where('id_user', $user->id_user)
                          ->first();
        
        if (!$alamat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alamat tidak valid'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            \Log::info('Creating purchase for product', [
                'product' => $barang->nama_barang,
                'quantity' => $request->jumlah
            ]);
            
            // Create purchase
            $pembelian = new Pembelian();
            $pembelian->id_pembeli = $user->id_user;
            $pembelian->id_alamat = $request->id_alamat;
            $pembelian->kode_pembelian = Pembelian::generateKodePembelian();
            $pembelian->status_pembelian = 'Draft';
            $pembelian->catatan_pembeli = $request->catatan_pembeli;
            $pembelian->is_deleted = false;
            $pembelian->created_by = $user->id_user;
            $pembelian->save();
            
            \Log::info('Purchase created', [
                'purchase_code' => $pembelian->kode_pembelian,
                'purchase_id' => $pembelian->id_pembelian
            ]);
            
            // Create purchase detail
            $detail = new DetailPembelian();
            $detail->id_pembelian = $pembelian->id_pembelian;
            $detail->id_barang = $barang->id_barang;
            $detail->id_toko = $barang->id_toko;
            $detail->harga_satuan = $barang->harga;
            $detail->jumlah = $request->jumlah;
            $detail->subtotal = $barang->harga * $request->jumlah;
            $detail->save();
            
            \Log::info('Purchase detail created', [
                'detail_id' => $detail->id_detail_pembelian
            ]);
            
            // Double-check that purchase details were created successfully
            $detailCount = DetailPembelian::where('id_pembelian', $pembelian->id_pembelian)->count();
            
            if ($detailCount === 0) {
                // If no details were created, roll back the transaction
                \Log::error('No purchase details were created for purchase ID: ' . $pembelian->id_pembelian);
                DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create purchase details'
                ], 500);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Pembelian berhasil dibuat',
                'data' => [
                    'kode_pembelian' => $pembelian->kode_pembelian,
                    'id_pembelian' => $pembelian->id_pembelian
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error creating purchase', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process checkout for the purchase
     */
    public function checkout(Request $request, $kode)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode)
                           ->where('id_pembeli', $user->id_user)
                           ->where('status_pembelian', 'Draft')
                           ->with('detailPembelian.barang', 'alamat')
                           ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau sudah diproses'
            ], 404);
        }
        
        // Validate checkout data
        $validator = Validator::make($request->all(), [
            'opsi_pengiriman' => 'required|string',
            'biaya_kirim' => 'required|numeric|min:0',
            'metode_pembayaran' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if products are still available and have enough stock before checkout
        foreach ($pembelian->detailPembelian as $detail) {
            $barang = $detail->barang;
            
            // Check if product is still available
            if ($barang->status_barang != 'Tersedia' || $barang->is_deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Produk ' . $barang->nama_barang . ' tidak tersedia lagi'
                ], 400);
            }
            
            // Check if there's enough stock
            if ($barang->stok < $detail->jumlah) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok produk ' . $barang->nama_barang . ' tidak mencukupi. Tersedia: ' . $barang->stok
                ], 400);
            }
        }
        
        DB::beginTransaction();
        try {
            // Calculate total product price
            $totalHarga = 0;
            foreach ($pembelian->detailPembelian as $detail) {
                $totalHarga += $detail->subtotal;
                
                // IMPORTANT: Removed stock reduction logic from here
                // We'll only reduce stock after payment is confirmed
            }
            
            // Set admin fee (you can adjust this as needed)
            $biayaAdmin = 1000; // Fixed admin fee of Rp 1.000
            
            // Calculate total invoice amount
            $totalTagihan = $totalHarga + $request->biaya_kirim + $biayaAdmin;
            
            // Update purchase status
            $pembelian->status_pembelian = 'Menunggu Pembayaran';
            $pembelian->updated_by = $user->id_user;
            $pembelian->save();
            
            // Create invoice
            $tagihan = new Tagihan();
            $tagihan->id_pembelian = $pembelian->id_pembelian;
            $tagihan->kode_tagihan = Tagihan::generateKodeTagihan();
            $tagihan->total_harga = $totalHarga;
            $tagihan->biaya_kirim = $request->biaya_kirim;
            $tagihan->opsi_pengiriman = $request->opsi_pengiriman;
            $tagihan->biaya_admin = $biayaAdmin;
            $tagihan->total_tagihan = $totalTagihan;
            $tagihan->metode_pembayaran = $request->metode_pembayaran;
            $tagihan->status_pembayaran = 'Menunggu';
            $tagihan->setPaymentDeadline(24); // Set 24 hours payment deadline
            $tagihan->save();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Checkout berhasil',
                'data' => [
                    'id_tagihan' => $tagihan->id_tagihan,
                    'kode_tagihan' => $tagihan->kode_tagihan,
                    'total_tagihan' => $tagihan->total_tagihan,
                    'deadline_pembayaran' => $tagihan->deadline_pembayaran
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat checkout: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process checkout for multiple stores in single purchase
     * Uses existing Tagihan model instead of MultiTagihan
     * Fixed to prevent creating empty orders
     */
    public function multiCheckout(Request $request, $kode)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode)
                           ->where('id_pembeli', $user->id_user)
                           ->where('status_pembelian', 'Draft')
                           ->with(['detailPembelian.barang', 'detailPembelian.toko'])
                           ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau sudah diproses'
            ], 404);
        }
        
        // Verify the original purchase has detail items
        if ($pembelian->detailPembelian->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Purchase has no items'
            ], 400);
        }
        
        // Validate checkout data
        $validator = Validator::make($request->all(), [
            'stores' => 'required|array',
            'stores.*.id_toko' => 'required|exists:toko,id_toko',
            'stores.*.id_alamat' => 'required|exists:alamat_user,id_alamat',
            'stores.*.opsi_pengiriman' => 'required|string',
            'stores.*.biaya_kirim' => 'required|numeric|min:0',
            'stores.*.catatan_pembeli' => 'nullable|string',
            'metode_pembayaran' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Group details by store
        $detailsByStore = collect($pembelian->detailPembelian)->groupBy('id_toko');
        
        // Check if all stores from the purchase are included in the request
        $requestStoreIds = collect($request->stores)->pluck('id_toko')->toArray();
        $purchaseStoreIds = $detailsByStore->keys()->toArray();
        
        $missingStores = array_diff($purchaseStoreIds, $requestStoreIds);
        if (!empty($missingStores)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not all stores from the purchase are included in checkout configuration'
            ], 400);
        }
        
        // Check if products are still available and have enough stock
        foreach ($pembelian->detailPembelian as $detail) {
            $barang = $detail->barang;
            
            // Check if product is still available
            if ($barang->status_barang != 'Tersedia' || $barang->is_deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Produk ' . $barang->nama_barang . ' tidak tersedia lagi'
                ], 400);
            }
            
            // Check if there's enough stock
            if ($barang->stok < $detail->jumlah) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Stok produk ' . $barang->nama_barang . ' tidak mencukupi. Tersedia: ' . $barang->stok
                ], 400);
            }
        }
        
        DB::beginTransaction();
        try {
            // Set admin fee (you can adjust this as needed)
            $biayaAdmin = 1000; // Fixed admin fee of Rp 1.000
            
            // Split the original purchase by store
            $storePurchases = [];
            $invoices = [];
            $totalAllPurchases = 0;
            $firstTagihan = null;
            
            // Generate a group ID to identify related orders
            $groupId = 'GRP' . time() . rand(1000, 9999);
            
            // Create a counter to track successfully created store purchases
            $successfulPurchases = 0;
            
            // Process each store
            foreach ($request->stores as $storeConfig) {
                $storeId = $storeConfig['id_toko'];
                
                // Get details for this store
                $storeDetails = $detailsByStore->get($storeId);
                
                if (!$storeDetails || $storeDetails->isEmpty()) {
                    \Log::warning('No details found for store ID: ' . $storeId);
                    continue; // Skip if no details for this store
                }
                
                // Create a new purchase for this store
                $storePurchase = new Pembelian();
                $storePurchase->id_pembeli = $user->id_user;
                $storePurchase->id_alamat = $storeConfig['id_alamat'];
                $storePurchase->kode_pembelian = Pembelian::generateKodePembelian();
                $storePurchase->status_pembelian = 'Menunggu Pembayaran';
                $storePurchase->catatan_pembeli = $storeConfig['catatan_pembeli'] ?? null;
                $storePurchase->is_deleted = false;
                $storePurchase->created_by = $user->id_user;
                $storePurchase->save();
                
                // Calculate store totals
                $storeTotalHarga = 0;
                $detailsCreated = 0;
                
                // Copy details to the new purchase
                foreach ($storeDetails as $detail) {
                    $newDetail = new DetailPembelian();
                    $newDetail->id_pembelian = $storePurchase->id_pembelian;
                    $newDetail->id_barang = $detail->id_barang;
                    $newDetail->id_toko = $detail->id_toko;
                    $newDetail->harga_satuan = $detail->harga_satuan;
                    $newDetail->jumlah = $detail->jumlah;
                    $newDetail->subtotal = $detail->subtotal;
                    $newDetail->save();
                    
                    $detailsCreated++;
                    $storeTotalHarga += $detail->subtotal;
                }
                
                // Verify details were actually created
                if ($detailsCreated === 0) {
                    \Log::error('No details created for purchase ID: ' . $storePurchase->id_pembelian);
                    // Delete the empty purchase
                    $storePurchase->delete();
                    continue; // Skip to the next store
                }
                
                // Create invoice for this store
                $tagihan = new Tagihan();
                $tagihan->id_pembelian = $storePurchase->id_pembelian;
                $tagihan->kode_tagihan = Tagihan::generateKodeTagihan();
                $tagihan->total_harga = $storeTotalHarga;
                $tagihan->biaya_kirim = $storeConfig['biaya_kirim'];
                $tagihan->opsi_pengiriman = $storeConfig['opsi_pengiriman'];
                
                // Add admin fee to first invoice only
                if (empty($invoices)) {
                    $tagihan->biaya_admin = $biayaAdmin;
                    $tagihan->total_tagihan = $storeTotalHarga + $storeConfig['biaya_kirim'] + $biayaAdmin;
                    $firstTagihan = $tagihan;
                } else {
                    $tagihan->biaya_admin = 0;
                    $tagihan->total_tagihan = $storeTotalHarga + $storeConfig['biaya_kirim'];
                }
                
                $tagihan->metode_pembayaran = $request->metode_pembayaran;
                $tagihan->status_pembayaran = 'Menunggu';
                $tagihan->group_id = $groupId; // Set the group ID for related orders
                $tagihan->setPaymentDeadline(24); // Set 24 hours payment deadline
                $tagihan->save();
                
                $successfulPurchases++;
                $storePurchases[] = $storePurchase;
                $invoices[] = $tagihan;
                $totalAllPurchases += $tagihan->total_tagihan;
            }
            
            // If no successful purchases were created, rollback and return error
            if ($successfulPurchases === 0) {
                DB::rollback();
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid store purchases could be created'
                ], 400);
            }
            
            // IMPORTANT: Instead of setting original purchase to "Diproses", mark it as "Dibatalkan"
            // and is_deleted to true so it doesn't show up in the order list
            $pembelian->status_pembelian = 'Dibatalkan';
            $pembelian->is_deleted = true;  // Set is_deleted to true to hide it completely
            $pembelian->updated_by = $user->id_user;
            $pembelian->save();
            
            DB::commit();
            
            // We'll use the first invoice for payment processing
            // but the payment will apply to all invoices in the group
            return response()->json([
                'status' => 'success',
                'message' => 'Multi-store checkout berhasil',
                'data' => [
                    'kode_tagihan' => $firstTagihan->kode_tagihan,
                    'group_id' => $groupId,
                    'total_tagihan' => $totalAllPurchases,
                    'deadline_pembayaran' => $firstTagihan->deadline_pembayaran,
                    'store_count' => count($storePurchases)
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during multi-store checkout: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat checkout: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cancel a purchase
     */
    public function cancel($kode)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode)
                           ->where('id_pembeli', $user->id_user)
                           ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan'
            ], 404);
        }
        
        if (!$pembelian->canBeCancelled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak dapat dibatalkan'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            // Update purchase status
            $pembelian->status_pembelian = 'Dibatalkan';
            $pembelian->updated_by = $user->id_user;
            $pembelian->save();
            
            // Cancel related invoice if it exists
            $tagihan = $pembelian->tagihan;
            if ($tagihan) {
                $tagihan->status_pembayaran = 'Gagal';
                $tagihan->save();
            }
            
            // We don't need to restore stock anymore since we're not reducing it at checkout
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Pembelian berhasil dibatalkan'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membatalkan pembelian: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm delivery of an order
     */
    public function confirmDelivery($kode)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode)
                           ->where('id_pembeli', $user->id_user)
                           ->where('status_pembelian', 'Dikirim')
                           ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau tidak dapat dikonfirmasi'
            ], 404);
        }
        
        DB::beginTransaction();
        try {
            // Update purchase status to 'Diterima' instead of 'Selesai'
            $pembelian->status_pembelian = 'Diterima';
            $pembelian->updated_by = $user->id_user;
            $pembelian->save();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Pengiriman berhasil dikonfirmasi',
                'data' => [
                    'status_pembelian' => $pembelian->status_pembelian
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengonfirmasi pengiriman: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete a purchase after confirmation
     */
    public function completePurchase($kode)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode)
                           ->where('id_pembeli', $user->id_user)
                           ->where('status_pembelian', 'Diterima')
                           ->with('detailPembelian.barang.toko')
                           ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau tidak dapat diselesaikan'
            ], 404);
        }
        
        DB::beginTransaction();
        try {
            $pembelian->status_pembelian = 'Selesai';
            $pembelian->updated_by = $user->id_user;
            $pembelian->save();
            
            // Add balance to sellers automatically
            $this->addBalanceToSellers($pembelian);
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan berhasil diselesaikan',
                'data' => [
                    'status_pembelian' => $pembelian->status_pembelian
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyelesaikan pesanan'
            ], 500);
        }
    }

    /**
     * Add balance to sellers when purchase is completed
     */
    private function addBalanceToSellers($pembelian)
    {
        try {
            // Group details by seller/store
            $sellerTotals = [];
            
            foreach ($pembelian->detailPembelian as $detail) {
                $sellerId = $detail->barang->toko->id_user;
                $amount = $detail->subtotal; // Only product price, no shipping or admin fees
                
                if (!isset($sellerTotals[$sellerId])) {
                    $sellerTotals[$sellerId] = 0;
                }
                $sellerTotals[$sellerId] += $amount;
            }
            
            // Add balance for each seller
            $saldoController = new SaldoPenjualController();
            foreach ($sellerTotals as $sellerId => $totalAmount) {
                $success = $saldoController->addBalance($sellerId, $totalAmount, $pembelian->id_pembelian);
                
                if (!$success) {
                    \Log::error('Failed to add balance to seller', [
                        'seller_id' => $sellerId,
                        'amount' => $totalAmount,
                        'pembelian_id' => $pembelian->id_pembelian
                    ]);
                }
            }
            
            \Log::info('Seller balances updated for completed purchase', [
                'pembelian_id' => $pembelian->id_pembelian,
                'kode_pembelian' => $pembelian->kode_pembelian,
                'seller_count' => count($sellerTotals)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error adding balance to sellers', [
                'pembelian_id' => $pembelian->id_pembelian,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't throw exception here to avoid rolling back the purchase completion
        }
    }

    /**
     * Calculate shipping options for a purchase
     */
    public function calculateShipping(Request $request, $kode)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'id_alamat' => 'required|exists:alamat_user,id_alamat',
            'id_toko' => 'required|exists:toko,id_toko'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $pembelian = Pembelian::where('kode_pembelian', $kode)
                           ->where('id_pembeli', $user->id_user)
                           ->with('detailPembelian.barang')
                           ->first();

        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Purchase not found'
            ], 404);
        }

        try {
            // Get store address
            $storeAddress = AlamatToko::where('id_toko', $request->id_toko)
                ->where('is_primary', true)
                ->first();

            if (!$storeAddress) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store address not found'
                ], 404);
            }

            // Get user address
            $userAddress = AlamatUser::where('id_alamat', $request->id_alamat)
                ->where('id_user', $user->id_user)
                ->first();

            if (!$userAddress) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User address not found'
                ], 404);
            }

            // Calculate total weight for this store - gunakan data asli
            $totalWeight = 0;
            $storeDetails = $pembelian->detailPembelian->where('id_toko', $request->id_toko);
            
            foreach ($storeDetails as $detail) {
                // Gunakan berat_barang langsung (sudah dalam gram)
                $weightInGrams = $detail->barang->berat_barang ? $detail->barang->berat_barang : 500;
                $totalWeight += $weightInGrams * $detail->jumlah;
            }

            // Minimum weight 1000 grams
            $totalWeight = max($totalWeight, 1000);

            \Log::info('Purchase shipping calculation', [
                'purchase_code' => $kode,
                'store_id' => $request->id_toko,
                'total_weight' => $totalWeight,
                'product_details' => $storeDetails->map(function($detail) {
                    return [
                        'id_barang' => $detail->id_barang,
                        'nama_barang' => $detail->barang->nama_barang,
                        'quantity' => $detail->jumlah,
                        'weight_per_item' => $detail->barang->berat_barang,
                        'total_weight' => ($detail->barang->berat_barang ?: 500) * $detail->jumlah
                    ];
                })->toArray()
            ]);

            // Get shipping options dengan multiple couriers
            $result = $this->rajaOngkirService->calculateDomesticCost(
                $storeAddress->kode_pos,
                $userAddress->kode_pos,
                $totalWeight,
                'jne:jnt:sicepat'  // Multiple couriers untuk opsi yang lebih banyak
            );

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 500);
            }

            $shippingOptions = $this->rajaOngkirService->formatShippingOptions($result['data']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'shipping_options' => $shippingOptions,
                    'total_weight' => $totalWeight
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Shipping calculation error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate shipping cost'
            ], 500);
        }
    }
}
