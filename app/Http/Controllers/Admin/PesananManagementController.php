<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pembelian;
use App\Models\DetailPembelian;
use App\Models\Tagihan;
use App\Models\PengirimanPembelian;
use Carbon\Carbon;

class PesananManagementController extends Controller
{
    /**
     * Display a listing of orders with optional filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Pembelian::with([
                'pembeli',
                'detailPembelian.toko',
                'detailPembelian.barang.gambar_barang',
                'detailPembelian.pengiriman_pembelian',
                'tagihan',
                'alamat.province',
                'alamat.regency',
                'alamat.district',
                'alamat.village'
            ])
            ->where('status_pembelian', '!=', 'Draft'); // Exclude Draft orders
            
            // Apply status filter
            if ($request->has('status') && $request->status) {
                $query->where('status_pembelian', $request->status);
            }
            
            // Apply payment status filter
            if ($request->has('payment_status') && $request->payment_status) {
                $query->whereHas('tagihan', function($q) use ($request) {
                    $q->where('status_pembayaran', $request->payment_status);
                });
            }
            
            // Apply date range filter
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            // Apply search filter (by order code or customer name)
            if ($request->has('search') && $request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('kode_pembelian', 'like', "%{$searchTerm}%")
                      ->orWhereHas('pembeli', function($subQuery) use ($searchTerm) {
                          $subQuery->where('name', 'like', "%{$searchTerm}%")
                                   ->orWhere('email', 'like', "%{$searchTerm}%");
                      });
                });
            }
            
            // Default sort by newest
            $query->orderBy('created_at', 'desc');
            
            // Paginate results
            $perPage = $request->input('per_page', 10);
            $orders = $query->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch orders: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get order statistics for dashboard
     */
    public function getOrderStats()
    {
        try {
            // Get count of orders by status
            $orderStatuses = Pembelian::select('status_pembelian', DB::raw('count(*) as count'))
                ->groupBy('status_pembelian')
                ->get()
                ->pluck('count', 'status_pembelian')
                ->toArray();
                
            // Get payment statistics
            $paymentStatuses = Tagihan::select('status_pembayaran', DB::raw('count(*) as count'))
                ->groupBy('status_pembayaran')
                ->get()
                ->pluck('count', 'status_pembayaran')
                ->toArray();
                
            // Get orders for last 7 days
            $lastWeekOrders = Pembelian::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get();
                
            // Calculate total order value this month
            $monthlyRevenue = Tagihan::where('status_pembayaran', 'Sukses')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total_tagihan');
                
            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_statuses' => $orderStatuses,
                    'payment_statuses' => $paymentStatuses,
                    'last_week_orders' => $lastWeekOrders,
                    'monthly_revenue' => $monthlyRevenue,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching order stats: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Display details for a specific order
     */
    public function show($kode)
    {
        try {
            // Log the start of the request for debugging
            Log::info('Loading order details for code: ' . $kode);
            
            // Use eager loading with optional relationship loading for pengiriman_pembelian
            // This ensures the query won't fail even if shipping data doesn't exist
            $order = Pembelian::with([
                'pembeli',
                'detailPembelian',  // Load all detail items first
                'detailPembelian.toko',
                'detailPembelian.barang',
                'detailPembelian.barang.gambar_barang', // Use snake_case field name
                'tagihan',
                'alamat.province',
                'alamat.regency',
                'alamat.district',
                'alamat.village'
            ])
            ->where('kode_pembelian', $kode)
            ->first();
            
            if (!$order) {
                Log::warning('Order not found with code: ' . $kode);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }
            
            // Manually load shipping data where it exists to prevent errors
            // This handles cases where orders haven't been shipped yet
            foreach ($order->detailPembelian as $detail) {
                // Try to load shipping info only for orders that should have it
                if (in_array($order->status_pembelian, ['Dikirim', 'Selesai'])) {
                    $shipping = PengirimanPembelian::where('id_detail_pembelian', $detail->id_detail)
                        ->first();
                    if ($shipping) {
                        $detail->pengiriman_pembelian = $shipping;
                    } else {
                        $detail->pengiriman_pembelian = null;
                    }
                } else {
                    // For orders that don't normally have shipping info yet, set to null explicitly
                    $detail->pengiriman_pembelian = null;
                }
            }
            
            // Log successful loading of order
            Log::info('Order details loaded successfully', [
                'order_id' => $order->id_pembelian,
                'details_count' => $order->detailPembelian->count(),
            ]);
            
            return response()->json([
                'status' => 'success',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching order details: ' . $e->getMessage(), [
                'code' => $kode,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order details: ' . $e->getMessage(),
                'trace' => env('APP_DEBUG', false) ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    /**
     * Update order status
     */
    public function updateStatus(Request $request, $kode)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:Draft,Menunggu Pembayaran,Dibayar,Diproses,Dikirim,Selesai,Dibatalkan',
                'admin_notes' => 'nullable|string',
            ]);
            
            $order = Pembelian::where('kode_pembelian', $kode)->first();
            
            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }
            
            // Check if status transition is valid
            if (!$order->canUpdateStatusTo($request->status)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid status transition from ' . $order->status_pembelian . ' to ' . $request->status
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Update order status
            $order->status_pembelian = $request->status;
            $order->updated_by = Auth::id();
            
            // Add admin notes if provided
            if ($request->has('admin_notes')) {
                // Assuming you have an admin_notes column in pembelian table
                $order->admin_notes = $request->admin_notes;
            }
            
            $order->save();
            
            // If status is changed to 'Dibatalkan', update payment status if applicable
            if ($request->status === 'Dibatalkan') {
                $tagihan = $order->tagihan;
                if ($tagihan && $tagihan->status_pembayaran !== 'Sukses') {
                    $tagihan->status_pembayaran = 'Gagal';
                    $tagihan->save();
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Order status updated successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating order status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order status: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Add admin comment to order
     */
    public function addComment(Request $request, $kode)
    {
        try {
            $request->validate([
                'comment' => 'required|string',
            ]);
            
            $order = Pembelian::where('kode_pembelian', $kode)->first();
            
            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }
            
            // Assuming you have a comments relation or column
            // This implementation may need adjustment based on your actual data model
            
            // For now, we'll just update the admin_notes field
            $order->admin_notes = $request->comment;
            $order->updated_by = Auth::id();
            $order->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Comment added successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding comment: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add comment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
