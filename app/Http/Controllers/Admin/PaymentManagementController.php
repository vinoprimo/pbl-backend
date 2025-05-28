<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Tagihan;
use App\Models\Pembelian;
use Carbon\Carbon;

class PaymentManagementController extends Controller
{
    /**
     * Display a listing of payments with optional filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Tagihan::with([
                'pembelian',
                'pembelian.pembeli',
                'pembelian.detailPembelian.barang'
            ]);
            
            // Apply status filter
            if ($request->has('status') && $request->status) {
                $query->where('status_pembayaran', $request->status);
            }
            
            // Apply payment method filter
            if ($request->has('payment_method') && $request->payment_method) {
                $query->where('metode_pembayaran', $request->payment_method);
            }
            
            // Apply date range filter
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            // Apply search filter (by invoice code or transaction ID)
            if ($request->has('search') && $request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('kode_tagihan', 'like', "%{$searchTerm}%")
                      ->orWhere('midtrans_transaction_id', 'like', "%{$searchTerm}%")
                      ->orWhereHas('pembelian', function($subQuery) use ($searchTerm) {
                          $subQuery->where('kode_pembelian', 'like', "%{$searchTerm}%");
                      })
                      ->orWhereHas('pembelian.pembeli', function($subQuery) use ($searchTerm) {
                          $subQuery->where('name', 'like', "%{$searchTerm}%")
                                   ->orWhere('email', 'like', "%{$searchTerm}%");
                      });
                });
            }
            
            // Default sort by newest
            $query->orderBy('created_at', 'desc');
            
            // Amount range filter
            if ($request->has('min_amount') && $request->min_amount) {
                $query->where('total_tagihan', '>=', $request->min_amount);
            }
            
            if ($request->has('max_amount') && $request->max_amount) {
                $query->where('total_tagihan', '<=', $request->max_amount);
            }
            
            // Paginate results
            $perPage = $request->input('per_page', 10);
            $payments = $query->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => $payments,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payments: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payments: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get payment statistics for dashboard
     */
    public function getPaymentStats()
    {
        try {
            // Get count of payments by status
            $paymentStatuses = Tagihan::select('status_pembayaran', DB::raw('count(*) as count'))
                ->groupBy('status_pembayaran')
                ->get()
                ->pluck('count', 'status_pembayaran')
                ->toArray();
                
            // Get payment methods distribution
            $paymentMethods = Tagihan::select('metode_pembayaran', DB::raw('count(*) as count'))
                ->groupBy('metode_pembayaran')
                ->get()
                ->pluck('count', 'metode_pembayaran')
                ->toArray();
                
            // Get payments for last 7 days
            $lastWeekPayments = Tagihan::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('sum(total_tagihan) as amount')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->where('status_pembayaran', 'Dibayar')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
                
            // Calculate total revenue this month
            $monthlyRevenue = Tagihan::where('status_pembayaran', 'Dibayar')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total_tagihan');
                
            // Payment success rate
            $totalPayments = Tagihan::count();
            $successfulPayments = Tagihan::where('status_pembayaran', 'Dibayar')->count();
            $successRate = $totalPayments > 0 ? ($successfulPayments / $totalPayments) * 100 : 0;
                
            return response()->json([
                'status' => 'success',
                'data' => [
                    'payment_statuses' => $paymentStatuses,
                    'payment_methods' => $paymentMethods,
                    'last_week_payments' => $lastWeekPayments,
                    'monthly_revenue' => $monthlyRevenue,
                    'success_rate' => round($successRate, 2)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payment stats: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payment statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Display details for a specific payment
     */
    public function show($kode)
    {
        try {
            // Try to find by tagihan.kode_tagihan first
            $payment = Tagihan::with([
                'pembelian',
                'pembelian.pembeli',
                'pembelian.detailPembelian.barang',
                'pembelian.detailPembelian.toko',
                'pembelian.alamat.province',
                'pembelian.alamat.regency',
                'pembelian.alamat.district',
                'pembelian.alamat.village'
            ])
            ->where('kode_tagihan', $kode)
            ->first();
            
            // If not found, try by midtrans_transaction_id
            if (!$payment) {
                $payment = Tagihan::with([
                    'pembelian',
                    'pembelian.pembeli',
                    'pembelian.detailPembelian.barang',
                    'pembelian.detailPembelian.toko',
                    'pembelian.alamat.province',
                    'pembelian.alamat.regency',
                    'pembelian.alamat.district',
                    'pembelian.alamat.village'
                ])
                ->where('midtrans_transaction_id', $kode)
                ->first();
            }
            
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching payment details: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payment details: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Update payment status manually
     */
    public function updateStatus(Request $request, $kode)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:Menunggu,Dibayar,Gagal,Expired',
                'admin_notes' => 'nullable|string',
            ]);
            
            $payment = Tagihan::where('kode_tagihan', $kode)->first();
            
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Update payment status
            $oldStatus = $payment->status_pembayaran;
            $payment->status_pembayaran = $request->status;
            
            // If status is changing to Dibayar, set payment date
            if ($request->status === 'Dibayar' && $oldStatus !== 'Dibayar') {
                $payment->tanggal_pembayaran = now();
            }
            
            // If status is changing from Dibayar to something else, clear payment date
            if ($oldStatus === 'Dibayar' && $request->status !== 'Dibayar') {
                $payment->tanggal_pembayaran = null;
            }
            
            $payment->save();
            
            // Update related purchase status
            if ($payment->pembelian) {
                $pembelian = $payment->pembelian;
                
                // If payment status is Dibayar, update purchase status to Dibayar
                if ($request->status === 'Dibayar' && $pembelian->status_pembelian === 'Menunggu Pembayaran') {
                    $pembelian->status_pembelian = 'Dibayar';
                }
                
                // If payment status is changed from Dibayar to something else, revert purchase status
                if ($oldStatus === 'Dibayar' && $request->status !== 'Dibayar' && $pembelian->status_pembelian === 'Dibayar') {
                    $pembelian->status_pembelian = 'Menunggu Pembayaran';
                }
                
                // If payment status is Gagal or Expired, cancel the purchase
                if (($request->status === 'Gagal' || $request->status === 'Expired') && $pembelian->status_pembelian !== 'Dibatalkan') {
                    $pembelian->status_pembelian = 'Dibatalkan';
                }
                
                $pembelian->admin_notes = $request->admin_notes ?? $pembelian->admin_notes;
                $pembelian->updated_by = Auth::id();
                $pembelian->save();
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Payment status updated successfully',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating payment status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment status: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Process refund for a payment
     */
    public function processRefund(Request $request, $kode)
    {
        try {
            $request->validate([
                'reason' => 'required|string',
                'amount' => 'nullable|numeric',
                'full_refund' => 'required|boolean',
            ]);
            
            $payment = Tagihan::where('kode_tagihan', $kode)->first();
            
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found'
                ], 404);
            }
            
            // Check if payment is eligible for refund
            if ($payment->status_pembayaran !== 'Dibayar') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only successful payments can be refunded'
                ], 422);
            }
            
            // In a real-world scenario, you would integrate with payment gateway API
            // to process the actual refund
            // For now, we're just updating the status in our database
            
            DB::beginTransaction();
            
            // Record refund information
            $refundAmount = $request->full_refund ? $payment->total_tagihan : $request->amount;
            
            // Update payment status
            $payment->status_pembayaran = 'Refund';
            $payment->refund_amount = $refundAmount;
            $payment->refund_reason = $request->reason;
            $payment->refund_date = now();
            $payment->refunded_by = Auth::id();
            $payment->save();
            
            // Update order status if needed
            if ($payment->pembelian && $request->full_refund) {
                $payment->pembelian->status_pembelian = 'Dibatalkan';
                $payment->pembelian->admin_notes = 'Refunded: ' . $request->reason;
                $payment->pembelian->updated_by = Auth::id();
                $payment->pembelian->save();
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Refund processed successfully',
                'data' => [
                    'payment' => $payment,
                    'refund_amount' => $refundAmount
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing refund: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process refund: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Manually verify a pending payment
     */
    public function verifyManually(Request $request, $kode)
    {
        try {
            $request->validate([
                'admin_notes' => 'nullable|string',
            ]);
            
            $payment = Tagihan::where('kode_tagihan', $kode)->first();
            
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found'
                ], 404);
            }
            
            // Check if payment can be verified
            if ($payment->status_pembayaran !== 'Menunggu') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only pending payments can be manually verified'
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Update payment status to successful
            $payment->status_pembayaran = 'Dibayar';
            $payment->tanggal_pembayaran = now();
            $payment->save();
            
            // Update order status
            if ($payment->pembelian) {
                $payment->pembelian->status_pembelian = 'Dibayar';
                $payment->pembelian->admin_notes = 'Payment manually verified by admin. ' . ($request->admin_notes ?? '');
                $payment->pembelian->updated_by = Auth::id();
                $payment->pembelian->save();
            }
            
            // If this payment is part of a group, update all related payments
            if ($payment->group_id) {
                $payment->markGroupAsPaid();
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Payment manually verified successfully',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error verifying payment: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
