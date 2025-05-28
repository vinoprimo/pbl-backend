<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pembelian;
use App\Models\DetailPembelian;
use App\Models\Barang;
use App\Models\Toko;
use App\Models\PengirimanPembelian;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PesananTokoController extends Controller
{
    /**
     * Display a listing of orders for the seller's shop
     * Filtered by status if provided
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get seller's shop
            $toko = Toko::where('id_user', $user->id_user)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }
            
            // Query to get orders for this shop
            $query = DetailPembelian::where('id_toko', $toko->id_toko)
                ->with([
                    'pembelian' => function ($q) {
                        $q->where('is_deleted', false)
                          // Exclude orders that are in "Draft" or "Menunggu Pembayaran" status
                          ->whereNotIn('status_pembelian', ['Draft', 'Menunggu Pembayaran']);
                    },
                    'pembelian.pembeli',
                    'pembelian.alamat.province',
                    'pembelian.alamat.regency',
                    'pembelian.alamat.district',
                    'pembelian.alamat.village',
                    'barang.gambarBarang',
                    'pengirimanPembelian'
                ]);
            
            // Filter by status if provided
            if ($request->has('status') && $request->status !== 'all') {
                $query->whereHas('pembelian', function ($q) use ($request) {
                    $q->where('status_pembelian', $request->status)
                      // Ensure we keep the exclusion of draft and pending payment orders
                      ->whereNotIn('status_pembelian', ['Draft', 'Menunggu Pembayaran']);
                });
            } else {
                // Make sure we only include orders that have been paid or further in the flow
                $query->whereHas('pembelian', function ($q) {
                    $q->whereNotIn('status_pembelian', ['Draft', 'Menunggu Pembayaran']);
                });
            }
            
            // Get the results and group by pembelian
            $detailPembelian = $query->get();
            
            // Group details by purchase id
            $groupedOrders = [];
            foreach ($detailPembelian as $detail) {
                // Skip if pembelian is null (could happen if order was deleted or filtered out)
                if (!$detail->pembelian) continue;
                
                $purchaseId = $detail->id_pembelian;
                if (!isset($groupedOrders[$purchaseId])) {
                    $groupedOrders[$purchaseId] = [
                        'id_pembelian' => $detail->id_pembelian,
                        'kode_pembelian' => $detail->pembelian->kode_pembelian,
                        'status_pembelian' => $detail->pembelian->status_pembelian,
                        'created_at' => $detail->pembelian->created_at,
                        'updated_at' => $detail->pembelian->updated_at,
                        'alamat' => $detail->pembelian->alamat,
                        'pembeli' => $detail->pembelian->pembeli,
                        'catatan_pembeli' => $detail->pembelian->catatan_pembeli,
                        'pengiriman' => $detail->pengirimanPembelian,
                        'items' => []
                    ];
                }
                
                // Add detail to items array
                $groupedOrders[$purchaseId]['items'][] = [
                    'id_detail_pembelian' => $detail->id_detail_pembelian,
                    'id_barang' => $detail->id_barang,
                    'jumlah' => $detail->jumlah,
                    'harga_satuan' => $detail->harga_satuan,
                    'subtotal' => $detail->subtotal,
                    'barang' => $detail->barang
                ];
                
                // Calculate total for this order
                if (!isset($groupedOrders[$purchaseId]['total'])) {
                    $groupedOrders[$purchaseId]['total'] = 0;
                }
                $groupedOrders[$purchaseId]['total'] += $detail->subtotal;
            }
            
            // Convert to indexed array
            $result = array_values($groupedOrders);
            
            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching seller orders: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load orders: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified order details
     */
    public function show($kode)
    {
        try {
            $user = Auth::user();
            
            // Get seller's shop
            $toko = Toko::where('id_user', $user->id_user)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }
            
            // Find the purchase by code
            $pembelian = Pembelian::where('kode_pembelian', $kode)
                ->where('is_deleted', false)
                ->with([
                    'detailPembelian' => function ($q) use ($toko) {
                        // Only include details from this shop
                        $q->where('id_toko', $toko->id_toko);
                    },
                    'detailPembelian.barang.gambarBarang',
                    'detailPembelian.pengirimanPembelian',
                    'pembeli',
                    'alamat.province',
                    'alamat.regency',
                    'alamat.district',
                    'alamat.village',
                    'komplain' => function($query) {
                        $query->with('retur');
                    }
                ])
                ->first();
            
            if (!$pembelian) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesanan tidak ditemukan'
                ], 404);
            }
            
            // Check if this shop has any items in this order
            if ($pembelian->detailPembelian->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesanan ini tidak berisi produk dari toko Anda'
                ], 403);
            }
            
            // Calculate total for this shop items
            $total = $pembelian->detailPembelian->sum('subtotal');
            
            // Prepare order data
            $orderData = [
                'id_pembelian' => $pembelian->id_pembelian,
                'kode_pembelian' => $pembelian->kode_pembelian,
                'status_pembelian' => $pembelian->status_pembelian,
                'created_at' => $pembelian->created_at,
                'updated_at' => $pembelian->updated_at,
                'catatan_pembeli' => $pembelian->catatan_pembeli,
                'alamat' => $pembelian->alamat,
                'pembeli' => $pembelian->pembeli,
                'items' => $pembelian->detailPembelian,
                'total' => $total,
                'pengiriman' => $pembelian->detailPembelian->first()->pengirimanPembelian ?? null,
                'komplain' => $pembelian->komplain
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $orderData
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching order details: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load order details: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the order status to 'Diproses' (first step - confirm receipt)
     * Note: The status changes from 'Dibayar' to 'Diproses' directly without 'Dikonfirmasi'
     */
    public function confirmOrder(Request $request, $kode)
    {
        try {
            $user = Auth::user();
            
            // Get seller's shop
            $toko = Toko::where('id_user', $user->id_user)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }
            
            // Find the purchase by code
            $pembelian = Pembelian::where('kode_pembelian', $kode)
                ->whereIn('status_pembelian', ['Dibayar'])
                ->with(['detailPembelian' => function ($q) use ($toko) {
                    $q->where('id_toko', $toko->id_toko);
                }])
                ->first();
            
            if (!$pembelian) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesanan tidak ditemukan atau tidak dapat dikonfirmasi'
                ], 404);
            }
            
            // Check if this shop has any items in this order
            if ($pembelian->detailPembelian->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesanan ini tidak berisi produk dari toko Anda'
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Update purchase status to 'Diproses' (status after payment confirmed)
            $pembelian->status_pembelian = 'Diproses';
            $pembelian->updated_by = $user->id_user;
            $pembelian->save();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan berhasil dikonfirmasi',
                'data' => [
                    'status_pembelian' => $pembelian->status_pembelian
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error confirming order: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm order: ' . $e->getMessage()
            ], 500);
        }
    }

    // Remove processOrder method since it's redundant - we go directly from Dibayar to Diproses
    // The shipOrder method will now accept orders with status 'Diproses'
    
    /**
     * Update order status to 'Dikirim' and add shipping information
     */
    public function shipOrder(Request $request, $kode)
    {
        try {
            // Validate request - remove kurir field
            $validator = Validator::make($request->all(), [
                'nomor_resi' => 'required|string|max:100',
                'catatan_pengiriman' => 'nullable|string',
                'bukti_pengiriman' => 'required|image|max:2048', // 2MB max
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = Auth::user();
            
            // Get seller's shop
            $toko = Toko::where('id_user', $user->id_user)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }
            
            // Find the purchase by code - only allow shipping for orders with status "Diproses"
            $pembelian = Pembelian::where('kode_pembelian', $kode)
                ->where('status_pembelian', 'Diproses')
                ->with(['detailPembelian' => function ($q) use ($toko) {
                    $q->where('id_toko', $toko->id_toko);
                }])
                ->first();
            
            if (!$pembelian) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesanan tidak ditemukan atau tidak dalam status Diproses'
                ], 404);
            }
            
            // Check if this shop has any items in this order
            if ($pembelian->detailPembelian->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesanan ini tidak berisi produk dari toko Anda'
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Handle image upload - IMPROVED VERSION
            $buktiPath = null;
            if ($request->hasFile('bukti_pengiriman')) {
                $file = $request->file('bukti_pengiriman');
                $fileName = time() . '_' . $file->getClientOriginalName();
                
                // Make sure the directory exists
                if (!file_exists(public_path('storage/pengiriman'))) {
                    mkdir(public_path('storage/pengiriman'), 0755, true);
                }
                
                // Store the file directly in the public storage
                $file->move(public_path('storage/pengiriman'), $fileName);
                
                // Set the relative path for database storage (accessible from web)
                $buktiPath = 'storage/pengiriman/' . $fileName;
                
                // Log successful upload
                Log::info('Shipping proof image saved', [
                    'file_name' => $fileName,
                    'path' => $buktiPath,
                    'full_url' => asset($buktiPath)
                ]);
            }
            
            // Create shipping record - ensure we use id_detail as the foreign key
            $pengiriman = new PengirimanPembelian();
            $pengiriman->id_detail_pembelian = $pembelian->detailPembelian->first()->id_detail;
            $pengiriman->nomor_resi = $request->nomor_resi;
            $pengiriman->catatan_pengiriman = $request->catatan_pengiriman;
            $pengiriman->bukti_pengiriman = $buktiPath;
            $pengiriman->tanggal_pengiriman = Carbon::now();
            $pengiriman->save();
            
            // Log the newly created shipping record for debugging
            Log::info('Created shipping record', [
                'pengiriman_id' => $pengiriman->id_pengiriman,
                'detail_id' => $pembelian->detailPembelian->first()->id_detail,
                'resi' => $request->nomor_resi,
                'bukti_path' => $buktiPath,
                'bukti_url' => asset($buktiPath) // Full URL for easier debugging
            ]);
            
            // Update purchase status
            $pembelian->status_pembelian = 'Dikirim';
            $pembelian->updated_by = $user->id_user;
            $pembelian->save();
            
            DB::commit();
            
            // Include full image URL in response for frontend
            $pengirimanData = $pengiriman->toArray();
            $pengirimanData['bukti_pengiriman_url'] = asset($buktiPath);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan berhasil dikirim',
                'data' => [
                    'status_pembelian' => $pembelian->status_pembelian,
                    'pengiriman' => $pengirimanData
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error shipping order: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to ship order: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get order statistics for the seller's dashboard
     */
    public function getOrderStats()
    {
        try {
            $user = Auth::user();
            
            // Get seller's shop
            $toko = Toko::where('id_user', $user->id_user)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }
            
            // Count orders by status
            // Only count orders that have been paid - exclude Draft and Menunggu Pembayaran
            $newOrders = DetailPembelian::where('id_toko', $toko->id_toko)
                ->whereHas('pembelian', function ($q) {
                    $q->where('status_pembelian', 'Dibayar')
                        ->where('is_deleted', false);
                })
                ->distinct('id_pembelian')
                ->count('id_pembelian');
            
            // No more "Confirmed" status, so we remove this count    
            // $confirmedOrders = ...
                
            $processingOrders = DetailPembelian::where('id_toko', $toko->id_toko)
                ->whereHas('pembelian', function ($q) {
                    $q->where('status_pembelian', 'Diproses')
                        ->where('is_deleted', false);
                })
                ->distinct('id_pembelian')
                ->count('id_pembelian');
                
            $shippedOrders = DetailPembelian::where('id_toko', $toko->id_toko)
                ->whereHas('pembelian', function ($q) {
                    $q->where('status_pembelian', 'Dikirim')
                        ->where('is_deleted', false);
                })
                ->distinct('id_pembelian')
                ->count('id_pembelian');
                
            $completedOrders = DetailPembelian::where('id_toko', $toko->id_toko)
                ->whereHas('pembelian', function ($q) {
                    $q->where('status_pembelian', 'Selesai')
                        ->where('is_deleted', false);
                })
                ->distinct('id_pembelian')
                ->count('id_pembelian');
            
            // Recent orders - only include paid orders and beyond
            $recentOrders = DetailPembelian::where('id_toko', $toko->id_toko)
                ->whereHas('pembelian', function ($q) {
                    $q->whereNotIn('status_pembelian', ['Draft', 'Menunggu Pembayaran'])
                      ->where('is_deleted', false);
                })
                ->with([
                    'pembelian',
                    'pembelian.pembeli',
                    'barang'
                ])
                ->orderBy('id_detail', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($detail) {
                    return [
                        'id_pembelian' => $detail->id_pembelian,
                        'kode_pembelian' => $detail->pembelian->kode_pembelian,
                        'status_pembelian' => $detail->pembelian->status_pembelian,
                        'nama_pembeli' => $detail->pembelian->pembeli->name,
                        'jumlah_produk' => 1, // This could be improved to count all products
                        'total' => $detail->subtotal,
                        'created_at' => $detail->pembelian->created_at
                    ];
                });
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'new_orders' => $newOrders,
                    'processing_orders' => $processingOrders,
                    'shipped_orders' => $shippedOrders,
                    'completed_orders' => $completedOrders,
                    'recent_orders' => $recentOrders
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting order stats: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get order stats: ' . $e->getMessage()
            ], 500);
        }
    }
}
