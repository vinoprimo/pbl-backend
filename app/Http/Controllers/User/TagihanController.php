<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Tagihan;
use App\Models\Pembelian;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;
use Carbon\Carbon;

class TagihanController extends Controller
{
    /**
     * Constructor to set up Midtrans configuration
     */
    public function __construct()
    {
        // Set up Midtrans configuration
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }
    
    /**
     * Get payment details by invoice code
     */
    public function show($kode)
    {
        $user = Auth::user();
        
        // Use with() to specify all the relationships we need to load
        $tagihan = Tagihan::whereHas('pembelian', function ($query) use ($user) {
                $query->where('id_pembeli', $user->id_user);
            })
            ->where('kode_tagihan', $kode)
            ->with([
                'pembelian',
                'pembelian.detailPembelian',
                'pembelian.detailPembelian.barang',
                'pembelian.detailPembelian.barang.gambarBarang',
                'pembelian.alamat',
                'pembelian.alamat.province',
                'pembelian.alamat.regency',
                'pembelian.alamat.district',
                'pembelian.alamat.village'
            ])
            ->first();
        
        if (!$tagihan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan tidak ditemukan'
            ], 404);
        }
        
        // Add debug logging to see what relationships are loaded
        \Log::debug('Tagihan loaded with relations', [
            'has_pembelian' => $tagihan->relationLoaded('pembelian') && $tagihan->pembelian !== null,
            'has_detail_pembelian' => $tagihan->pembelian && $tagihan->pembelian->relationLoaded('detailPembelian'),
            'detail_count' => $tagihan->pembelian && $tagihan->pembelian->detailPembelian ? count($tagihan->pembelian->detailPembelian) : 0
        ]);
        
        return response()->json([
            'status' => 'success',
            'data' => $tagihan
        ]);
    }
    
    /**
     * Process payment with Midtrans
     */
    public function processPayment($kode)
    {
        $user = Auth::user();
        
        $tagihan = Tagihan::whereHas('pembelian', function ($query) use ($user) {
                $query->where('id_pembeli', $user->id_user);
            })
            ->where('kode_tagihan', $kode)
            ->where('status_pembayaran', 'Menunggu')
            ->with(['pembelian.detailPembelian.barang', 'pembelian.alamat', 'pembelian.pembeli'])
            ->first();
        
        if (!$tagihan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan tidak ditemukan atau sudah dibayar'
            ], 404);
        }
        
        // Check if payment deadline has passed
        if (!$tagihan->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Batas waktu pembayaran telah berakhir'
            ], 400);
        }
        
        try {
            $pembelian = $tagihan->pembelian;
            $pembeli = $pembelian->pembeli;
            $alamat = $pembelian->alamat;
            
            // Ensure both address and buyer exist
            if (!$alamat || !$pembeli) {
                \Log::error('Missing address or buyer data', [
                    'invoice_id' => $tagihan->id_tagihan,
                    'invoice_code' => $tagihan->kode_tagihan,
                    'has_address' => (bool)$alamat,
                    'has_buyer' => (bool)$pembeli
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data alamat atau pembeli tidak lengkap'
                ], 400);
            }
            
            // Log key data for debugging
            \Log::info('Processing payment', [
                'invoice_code' => $tagihan->kode_tagihan,
                'total' => $tagihan->total_tagihan,
                'buyer' => $pembeli->name,
                'items_count' => count($pembelian->detailPembelian)
            ]);
            
            // Prepare item details for Midtrans
            $items = [];
            foreach ($pembelian->detailPembelian as $detail) {
                $items[] = [
                    'id' => $detail->id_barang,
                    'price' => $detail->harga_satuan,
                    'quantity' => $detail->jumlah,
                    'name' => substr($detail->barang->nama_barang, 0, 50) // Midtrans has a 50 char limit
                ];
            }
            
            // Add shipping cost as an item
            $items[] = [
                'id' => 'shipping-cost',
                'price' => $tagihan->biaya_kirim,
                'quantity' => 1,
                'name' => 'Biaya Pengiriman (' . $tagihan->opsi_pengiriman . ')'
            ];
            
            // Add admin fee as an item
            $items[] = [
                'id' => 'admin-fee',
                'price' => $tagihan->biaya_admin,
                'quantity' => 1,
                'name' => 'Biaya Admin'
            ];
            
            // Generate a unique order ID by appending a timestamp to avoid conflicts
            // This resolves the "order_id has already been taken" error when retrying payments
            $uniqueOrderId = $tagihan->kode_tagihan . '-' . time();
            
            // Prepare transaction details for Midtrans with the unique order ID
            $transactionDetails = [
                'order_id' => $uniqueOrderId,
                'gross_amount' => $tagihan->total_tagihan
            ];
            
            // Make sure we have phone number data or use a placeholder
            $phone = $pembeli->no_hp ?: '08123456789';
            
            // Prepare customer details for Midtrans
            $customerDetails = [
                'first_name' => $pembeli->name,
                'email' => $pembeli->email,
                'phone' => $phone,
                'billing_address' => [
                    'first_name' => $alamat->nama_penerima ?: $pembeli->name,
                    'phone' => $alamat->no_telp ?: $phone,
                    'address' => $alamat->alamat_lengkap,
                    'city' => $alamat->regency->name ?? 'Unknown',
                    'postal_code' => $alamat->kode_pos,
                    'country_code' => 'IDN'
                ]
            ];
            
            // Prepare transaction data for Midtrans
            $transactionData = [
                'transaction_details' => $transactionDetails,
                'item_details' => $items,
                'customer_details' => $customerDetails,
                'expiry' => [
                    'unit' => 'hour',
                    'duration' => 24
                ]
            ];
            
            // Get Snap Token from Midtrans
            $snapToken = Snap::getSnapToken($transactionData);
            
            // Generate the payment URL
            $paymentUrl = config('services.midtrans.is_production')
                ? 'https://app.midtrans.com/snap/v2/vtweb/' . $snapToken
                : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $snapToken;
            
            // Update invoice with Midtrans transaction ID, snap token and payment URL
            // Wrap in try/catch to handle potential database errors
            try {
                $tagihan->midtrans_transaction_id = $uniqueOrderId; // Store the unique order ID
                $tagihan->snap_token = $snapToken;
                $tagihan->payment_url = $paymentUrl;
                $tagihan->save();
                
                \Log::info('Payment process successful', [
                    'invoice_code' => $tagihan->kode_tagihan,
                    'midtrans_order_id' => $uniqueOrderId,
                    'snap_token' => $snapToken
                ]);
                
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'snap_token' => $snapToken,
                        'redirect_url' => $paymentUrl
                    ]
                ]);
            } catch (\Exception $dbError) {
                // Log the specific database error
                \Log::error('Database error when saving payment data', [
                    'invoice_code' => $tagihan->kode_tagihan,
                    'error' => $dbError->getMessage(),
                    'trace' => $dbError->getTraceAsString()
                ]);
                
                throw new \Exception('Error saving payment data to database: ' . $dbError->getMessage());
            }
            
        } catch (\Exception $e) {
            \Log::error('Midtrans Error', [
                'invoice_code' => $tagihan->kode_tagihan,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide a more user-friendly error message, especially for order_id errors
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'order_id has already been taken') !== false) {
                $errorMessage = 'Payment session expired. Please try again.';
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses pembayaran: ' . $errorMessage
            ], 500);
        }
    }
    
    /**
     * Check payment status
     */
    public function checkStatus($kode)
    {
        $user = Auth::user();
        
        $tagihan = Tagihan::whereHas('pembelian', function ($query) use ($user) {
                $query->where('id_pembeli', $user->id_user);
            })
            ->where('kode_tagihan', $kode)
            ->first();
        
        if (!$tagihan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tagihan tidak ditemukan'
            ], 404);
        }
        
        // For pending payments, check with Midtrans for the latest status
        // This helps in case the callback wasn't received correctly
        if ($tagihan->status_pembayaran === 'Menunggu' && $tagihan->midtrans_transaction_id) {
            try {
                // Get status from Midtrans
                $midtransStatus = $this->getMidtransStatus($tagihan->midtrans_transaction_id);
                
                // If we got a different status from Midtrans, update our database
                if ($midtransStatus && $midtransStatus !== 'pending' && $midtransStatus !== $tagihan->midtrans_status) {
                    Log::info('Updating payment status based on manual check', [
                        'invoice_code' => $tagihan->kode_tagihan,
                        'old_status' => $tagihan->midtrans_status,
                        'new_status' => $midtransStatus
                    ]);
                    
                    // Update the payment status
                    if ($midtransStatus === 'settlement' || $midtransStatus === 'capture') {
                        // Begin transaction to update related data
                        DB::beginTransaction();
                        
                        try {
                            $tagihan->status_pembayaran = 'Dibayar';
                            $tagihan->midtrans_status = $midtransStatus;
                            $tagihan->tanggal_pembayaran = now();
                            $tagihan->save();
                            
                            // Update purchase status and product status/stock
                            if ($tagihan->pembelian) {
                                $tagihan->pembelian->status_pembelian = 'Dibayar';
                                $tagihan->pembelian->save();
                                
                                // Get the purchase details and update product status
                                $details = \App\Models\DetailPembelian::where('id_pembelian', $tagihan->pembelian->id_pembelian)
                                    ->with('barang')
                                    ->get();
                                    
                                foreach ($details as $detail) {
                                    $barang = $detail->barang;
                                    if ($barang) {
                                        // Reduce stock
                                        $barang->stok -= $detail->jumlah;
                                        
                                        // Always mark as 'Dijual' when payment is successful, regardless of stock
                                        $barang->status_barang = 'Terjual';
                                        
                                        $barang->save();
                                        
                                        Log::info('Manual check: Updated product after payment', [
                                            'product_id' => $barang->id_barang,
                                            'product_name' => $barang->nama_barang,
                                            'new_status' => $barang->status_barang,
                                            'new_stock' => $barang->stok
                                        ]);
                                    }
                                }
                            }
                            
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollback();
                            Log::error('Error updating product status: ' . $e->getMessage());
                        }
                    } else if ($midtransStatus === 'cancel' || $midtransStatus === 'deny' || $midtransStatus === 'expire') {
                        $tagihan->status_pembayaran = 'Gagal';
                        $tagihan->midtrans_status = $midtransStatus;
                        $tagihan->save();
                        
                        // Update purchase status
                        if ($tagihan->pembelian) {
                            $tagihan->pembelian->status_pembelian = 'Dibatalkan';
                            $tagihan->pembelian->save();
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error checking Midtrans status: ' . $e->getMessage());
                // Continue with local status
            }
        }
        
        // Check if deadline has passed and update status if needed
        if ($tagihan->status_pembayaran === 'Menunggu' && 
            $tagihan->deadline_pembayaran && 
            now()->gt($tagihan->deadline_pembayaran)) {
            $tagihan->status_pembayaran = 'Expired';
            $tagihan->save();
            
            // Update purchase status
            if ($tagihan->pembelian) {
                $tagihan->pembelian->status_pembelian = 'Dibatalkan';
                $tagihan->pembelian->save();
            }
        }
        
        // Return the latest payment status
        return response()->json([
            'status' => 'success',
            'data' => [
                'kode_tagihan' => $tagihan->kode_tagihan,
                'status_pembayaran' => $tagihan->status_pembayaran,
                'midtrans_status' => $tagihan->midtrans_status,
                'total_tagihan' => $tagihan->total_tagihan,
                'deadline_pembayaran' => $tagihan->deadline_pembayaran,
                'tanggal_pembayaran' => $tagihan->tanggal_pembayaran,
                'is_valid' => $tagihan->isValid(),
                'is_paid' => $tagihan->isPaid(),
                'snap_token' => $tagihan->snap_token,
                'payment_url' => $tagihan->payment_url
            ]
        ]);
    }

    /**
     * Handle Midtrans notification callback
     */
    public function callback(Request $request)
    {
        try {
            // Log the raw notification data for debugging
            $notificationBody = $request->getContent();
            Log::info('Midtrans Raw Notification', ['body' => $notificationBody]);
            
            // Parse the notification
            $notification = new Notification();
            
            // Extract needed data from notification
            $orderId = $notification->order_id;
            $statusCode = $notification->status_code;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus = isset($notification->fraud_status) ? $notification->fraud_status : null;
            $paymentType = isset($notification->payment_type) ? $notification->payment_type : null;
            
            // Log the notification data for debugging
            Log::info('Midtrans Callback Processed', [
                'order_id' => $orderId,
                'status_code' => $statusCode,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus,
                'payment_type' => $paymentType
            ]);
            
            // Find the invoice by order ID - need to handle order IDs with timestamps
            // First try to find by exact match
            $tagihan = Tagihan::where('kode_tagihan', $orderId)
                        ->orWhere('midtrans_transaction_id', $orderId)
                        ->with(['pembelian.detailPembelian.barang'])
                        ->first();
            
            // If not found, try to find by the base order ID (without timestamp)
            if (!$tagihan && strpos($orderId, '-') !== false) {
                $baseOrderId = substr($orderId, 0, strrpos($orderId, '-'));
                Log::info('Trying base order ID', ['base_order_id' => $baseOrderId]);
                
                $tagihan = Tagihan::where('kode_tagihan', $baseOrderId)
                            ->with(['pembelian.detailPembelian.barang'])
                            ->first();
            }
            
            if (!$tagihan) {
                Log::error('Invoice not found in callback', ['order_id' => $orderId]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice not found'
                ], 404);
            }
            
            Log::info('Invoice found', [
                'invoice_id' => $tagihan->id_tagihan,
                'current_status' => $tagihan->status_pembayaran
            ]);
            
            // Process different transaction statuses
            if ($statusCode == '200') {
                // Handle successful payment - settlement or capture
                if ($transactionStatus == 'settlement' || 
                    ($transactionStatus == 'capture' && ($fraudStatus == 'accept' || $fraudStatus === null))) {
                    
                    // Begin transaction to update stock and status
                    DB::beginTransaction();
                    
                    try {
                        // Now that payment is successful, reduce product stock
                        if ($tagihan->pembelian && $tagihan->pembelian->detailPembelian) {
                            foreach ($tagihan->pembelian->detailPembelian as $detail) {
                                $barang = $detail->barang;
                                
                                // Reduce stock now that payment is confirmed
                                $barang->stok -= $detail->jumlah;
                                
                                // Update product status to sold for second-hand/pre-owned products
                                // This ensures the product no longer appears as available
                                if ($barang->stok <= 0) {
                                    $barang->status_barang = 'Habis';
                                } else {
                                    // For successful payment, mark as sold if it's a second-hand item
                                    // (This assumes that second-hand items should be marked as sold after purchase)
                                    $barang->status_barang = 'Terjual';
                                }
                                
                                $barang->save();
                                
                                Log::info('Updated product after payment', [
                                    'product_id' => $barang->id_barang,
                                    'product_name' => $barang->nama_barang,
                                    'new_status' => $barang->status_barang,
                                    'new_stock' => $barang->stok
                                ]);
                            }
                        }
                        
                        // Update invoice
                        $tagihan->midtrans_status = $transactionStatus;
                        $tagihan->status_pembayaran = 'Dibayar';
                        $tagihan->tanggal_pembayaran = now();
                        if ($paymentType) {
                            $tagihan->midtrans_payment_type = $paymentType;
                        }
                        $tagihan->save();
                        
                        // Update purchase status
                        $pembelian = $tagihan->pembelian;
                        if ($pembelian) {
                            $pembelian->status_pembelian = 'Dibayar';
                            $pembelian->save();
                        }
                        
                        DB::commit();
                        
                        Log::info('Payment successfully processed', [
                            'invoice_code' => $tagihan->kode_tagihan,
                            'new_status' => 'Dibayar'
                        ]);
                        
                        return response()->json(['status' => 'success']);
                        
                    } catch (\Exception $e) {
                        DB::rollback();
                        Log::error('Error processing successful payment: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString()
                        ]);
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Error processing payment: ' . $e->getMessage()
                        ], 500);
                    }
                }
                else if ($transactionStatus == 'pending') {
                    // Payment is still pending, update status only
                    $tagihan->midtrans_status = $transactionStatus;
                    $tagihan->status_pembayaran = 'Menunggu';
                    $tagihan->save();
                    
                    Log::info('Payment status updated to pending', [
                        'invoice_code' => $tagihan->kode_tagihan
                    ]);
                    
                    return response()->json(['status' => 'success']);
                }
                else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
                    // Payment failed, update status only - no stock changes needed
                    $tagihan->midtrans_status = $transactionStatus;
                    $tagihan->status_pembayaran = 'Gagal';
                    $tagihan->save();
                    
                    // Update purchase status
                    $pembelian = $tagihan->pembelian;
                    if ($pembelian) {
                        $pembelian->status_pembelian = 'Dibatalkan';
                        $pembelian->save();
                    }
                    
                    Log::info('Payment status updated to failed', [
                        'invoice_code' => $tagihan->kode_tagihan,
                        'reason' => $transactionStatus
                    ]);
                    
                    return response()->json(['status' => 'success']);
                }
            } else {
                // Handle failed transaction
                $tagihan->midtrans_status = 'failed';
                $tagihan->status_pembayaran = 'Gagal';
                $tagihan->save();
                
                // Update purchase status
                $pembelian = $tagihan->pembelian;
                if ($pembelian) {
                    $pembelian->status_pembelian = 'Dibatalkan';
                    $pembelian->save();
                }
                
                Log::info('Payment failed with non-200 status code', [
                    'invoice_code' => $tagihan->kode_tagihan,
                    'status_code' => $statusCode
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment failed'
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Midtrans Callback Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_body' => $request->getContent()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing callback: ' . $e->getMessage()
            ], 500);
        }
        
        // Default response if no conditions are met
        return response()->json(['status' => 'success']);
    }

    /**
     * Get payment status directly from Midtrans
     */
    private function getMidtransStatus($orderId)
    {
        try {
            $serverKey = config('services.midtrans.server_key');
            if (!$serverKey) {
                Log::error('Midtrans server key not configured');
                return null;
            }
            
            $auth = base64_encode($serverKey . ':');
            
            $ch = curl_init();
            
            $isProduction = config('services.midtrans.is_production', false);
            $baseUrl = $isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
            
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/' . $orderId . '/status');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . $auth
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                
                Log::info('Midtrans status check response', [
                    'order_id' => $orderId,
                    'transaction_status' => $data['transaction_status'] ?? 'unknown'
                ]);
                
                return $data['transaction_status'] ?? null;
            } else {
                Log::warning('Midtrans status check failed', [
                    'order_id' => $orderId,
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error checking Midtrans status: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all payments for the authenticated user
     */
    public function getAll()
    {
        $user = Auth::user();
        
        $tagihan = Tagihan::whereHas('pembelian', function ($query) use ($user) {
                $query->where('id_pembeli', $user->id_user);
            })
            ->with([
                'pembelian.detailPembelian.barang'
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Check and update expired payments
        foreach ($tagihan as $t) {
            if ($t->status_pembayaran === 'Menunggu' && 
                $t->deadline_pembayaran && 
                now()->gt($t->deadline_pembayaran)) {
                $t->status_pembayaran = 'Expired';
                $t->save();
                
                // Update purchase status
                if ($t->pembelian) {
                    $t->pembelian->status_pembelian = 'Dibatalkan';
                    $t->pembelian->save();
                }
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $tagihan
        ]);
    }
    
    /**
     * Debug endpoint to check Midtrans configuration
     */
    public function debugMidtransConfig()
    {
        try {
            $config = [
                'server_key_exists' => !empty(config('services.midtrans.server_key')),
                'client_key_exists' => !empty(config('services.midtrans.client_key')),
                'is_production' => config('services.midtrans.is_production', false),
                'api_url' => config('services.midtrans.is_production', false) ? 
                    'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com'
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error checking Midtrans configuration: ' . $e->getMessage()
            ], 500);
        }
    }
}
