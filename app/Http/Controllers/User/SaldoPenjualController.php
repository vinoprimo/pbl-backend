<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\SaldoPenjual;
use App\Models\SaldoPerusahaan;
use App\Models\User;

class SaldoPenjualController extends Controller
{
    /**
     * Get seller balance for authenticated user
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            $saldoPenjual = SaldoPenjual::where('id_user', $user->id_user)
                ->with(['user', 'pengajuanPencairan' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->first();
            
            // Create saldo record if doesn't exist
            if (!$saldoPenjual) {
                $saldoPenjual = SaldoPenjual::create([
                    'id_user' => $user->id_user,
                    'saldo_tersedia' => 0,
                    'saldo_tertahan' => 0
                ]);
                $saldoPenjual->load('user');
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $saldoPenjual
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch seller balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get seller balance by user ID (for admin)
     */
    public function show($userId)
    {
        try {
            $user = Auth::user();
            
            // Check if user is admin or accessing own balance
            if (!$user->isAdminOrHigher() && $user->id_user != $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }
            
            $saldoPenjual = SaldoPenjual::where('id_user', $userId)
                ->with(['user', 'pengajuanPencairan' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->first();
            
            if (!$saldoPenjual) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Seller balance not found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $saldoPenjual
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch seller balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get balance history for seller
     */
    public function getBalanceHistory()
    {
        try {
            $user = Auth::user();
            
            // Get company balance history (money coming in)
            $saldoPerusahaan = SaldoPerusahaan::where('id_penjual', $user->id_user)
                ->with(['pembelian', 'penjual'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $saldoPerusahaan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch balance history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add balance to seller (internal method, called when purchase is completed)
     * This is typically called from the system, not directly by users
     */
    public function addBalance($userId, $amount, $pembelianId)
    {
        DB::beginTransaction();
        try {
            // Get or create seller balance
            $saldoPenjual = SaldoPenjual::firstOrCreate(
                ['id_user' => $userId],
                ['saldo_tersedia' => 0, 'saldo_tertahan' => 0]
            );
            
            // Add to available balance
            $saldoPenjual->saldo_tersedia += $amount;
            $saldoPenjual->save();
            
            // Create company balance record
            SaldoPerusahaan::create([
                'id_pembelian' => $pembelianId,
                'id_penjual' => $userId,
                'jumlah_saldo' => $amount,
                'status' => 'Siap Dicairkan'
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Failed to add seller balance', [
                'user_id' => $userId,
                'amount' => $amount,
                'pembelian_id' => $pembelianId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Hold balance for withdrawal request
     */
    public function holdBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $amount = $request->amount;
            
            $saldoPenjual = SaldoPenjual::where('id_user', $user->id_user)->first();
            
            if (!$saldoPenjual) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Seller balance not found'
                ], 404);
            }
            
            if (!$saldoPenjual->hasSufficientBalance($amount)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient balance'
                ], 400);
            }
            
            $success = $saldoPenjual->holdBalance($amount);
            
            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to hold balance'
                ], 500);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Balance held successfully',
                'data' => $saldoPenjual->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to hold balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release held balance
     */
    public function releaseBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $amount = $request->amount;
            
            $saldoPenjual = SaldoPenjual::where('id_user', $user->id_user)->first();
            
            if (!$saldoPenjual) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Seller balance not found'
                ], 404);
            }
            
            $success = $saldoPenjual->releaseHeldBalance($amount);
            
            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to release balance'
                ], 500);
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Balance released successfully',
                'data' => $saldoPenjual->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to release balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all seller balances (admin only)
     */
    public function getAllBalances(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user->isAdminOrHigher()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }
            
            $query = SaldoPenjual::with(['user']);
            
            // Add search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            // Add pagination
            $perPage = $request->input('per_page', 15);
            $saldoPenjual = $query->orderBy('updated_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => $saldoPenjual
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch seller balances: ' . $e->getMessage()
            ], 500);
        }
    }
}
