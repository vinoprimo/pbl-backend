<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PengajuanPencairan;
use App\Models\SaldoPenjual;
use Carbon\Carbon;

class PengajuanPencairanController extends Controller
{
    /**
     * Get withdrawal requests for authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = PengajuanPencairan::where('id_user', $user->id_user)
                ->with(['saldoPenjual', 'creator', 'updater']);
            
            // Filter by status if provided
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status_pencairan', $request->status);
            }
            
            // Pagination
            $perPage = $request->input('per_page', 10);
            $withdrawals = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => $withdrawals
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch withdrawal requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new withdrawal request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jumlah_dana' => 'required|numeric|min:10000', // Minimum 10k
            'keterangan' => 'nullable|string|max:500',
            'nomor_rekening' => 'required|string|max:50',
            'nama_bank' => 'required|string|max:100',
            'nama_pemilik_rekening' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $amount = $request->jumlah_dana;

            // Get seller balance
            $saldoPenjual = SaldoPenjual::where('id_user', $user->id_user)->first();

            if (!$saldoPenjual) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Saldo penjual tidak ditemukan'
                ], 404);
            }

            // Check if sufficient balance
            if (!$saldoPenjual->hasSufficientBalance($amount)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Saldo tidak mencukupi'
                ], 400);
            }

            // Check for pending withdrawal
            $pendingWithdrawal = PengajuanPencairan::where('id_user', $user->id_user)
                ->whereIn('status_pencairan', ['Menunggu', 'Diproses'])
                ->exists();

            if ($pendingWithdrawal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda masih memiliki pengajuan pencairan yang sedang diproses'
                ], 400);
            }

            // Hold the balance
            $holdSuccess = $saldoPenjual->holdBalance($amount);
            if (!$holdSuccess) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal menahan saldo'
                ], 500);
            }

            // Create withdrawal request
            $withdrawal = PengajuanPencairan::create([
                'id_user' => $user->id_user,
                'id_saldo_penjual' => $saldoPenjual->id_saldo_penjual,
                'jumlah_dana' => $amount,
                'keterangan' => $request->keterangan,
                'nomor_rekening' => $request->nomor_rekening,
                'nama_bank' => $request->nama_bank,
                'nama_pemilik_rekening' => $request->nama_pemilik_rekening,
                'tanggal_pengajuan' => Carbon::now(),
                'status_pencairan' => 'Menunggu',
                'created_by' => $user->id_user
            ]);

            DB::commit();

            // Load relationships for response
            $withdrawal->load(['saldoPenjual', 'creator']);

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan pencairan berhasil dibuat',
                'data' => $withdrawal
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat pengajuan pencairan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific withdrawal request
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            
            $withdrawal = PengajuanPencairan::where('id_pencairan', $id)
                ->where('id_user', $user->id_user)
                ->with(['saldoPenjual', 'creator', 'updater'])
                ->first();

            if (!$withdrawal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengajuan pencairan tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel withdrawal request (only if still pending)
     */
    public function cancel($id)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            
            $withdrawal = PengajuanPencairan::where('id_pencairan', $id)
                ->where('id_user', $user->id_user)
                ->where('status_pencairan', 'Menunggu')
                ->first();

            if (!$withdrawal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengajuan pencairan tidak ditemukan atau tidak dapat dibatalkan'
                ], 404);
            }

            // Release held balance
            $saldoPenjual = $withdrawal->saldoPenjual;
            $releaseSuccess = $saldoPenjual->releaseHeldBalance($withdrawal->jumlah_dana);
            
            if (!$releaseSuccess) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal melepas saldo yang ditahan'
                ], 500);
            }

            // Update withdrawal status
            $withdrawal->update([
                'status_pencairan' => 'Ditolak',
                'catatan_admin' => 'Dibatalkan oleh pengguna',
                'updated_by' => $user->id_user
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan pencairan berhasil dibatalkan',
                'data' => $withdrawal->fresh(['saldoPenjual', 'creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membatalkan pengajuan pencairan: ' . $e->getMessage()
            ], 500);
        }
    }
}
