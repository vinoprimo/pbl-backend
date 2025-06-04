<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanPencairan;
use App\Models\SaldoPenjual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PencairanManagementController extends Controller
{
    /**
     * Get all withdrawal requests for admin
     */
    public function index(Request $request)
    {
        try {
            $query = PengajuanPencairan::with([
                'user:id_user,name,email,username',
                'saldoPenjual',
                'creator:id_user,name',
                'updater:id_user,name'
            ]);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status_pencairan', $request->status);
            }
            
            if ($request->filled('date_from')) {
                $query->whereDate('tanggal_pengajuan', '>=', $request->date_from);
            }
            
            if ($request->filled('date_to')) {
                $query->whereDate('tanggal_pengajuan', '<=', $request->date_to);
            }

            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->whereHas('user', function($subQuery) use ($searchTerm) {
                        $subQuery->where('name', 'like', "%{$searchTerm}%")
                                ->orWhere('email', 'like', "%{$searchTerm}%")
                                ->orWhere('username', 'like', "%{$searchTerm}%");
                    })
                    ->orWhere('nama_bank', 'like', "%{$searchTerm}%")
                    ->orWhere('nomor_rekening', 'like', "%{$searchTerm}%")
                    ->orWhere('nama_pemilik_rekening', 'like', "%{$searchTerm}%");
                });
            }

            // Apply amount range filter
            if ($request->filled('amount_min')) {
                $query->where('jumlah_dana', '>=', $request->amount_min);
            }
            
            if ($request->filled('amount_max')) {
                $query->where('jumlah_dana', '<=', $request->amount_max);
            }

            $pencairan = $query->latest('tanggal_pengajuan')->paginate($request->input('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => $pencairan
            ]);

        } catch (\Exception $e) {
            Log::error('Error in withdrawal index: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data pencairan'
            ], 500);
        }
    }

    /**
     * Get specific withdrawal request details
     */
    public function show($id_pencairan)
    {
        try {
            $pencairan = PengajuanPencairan::with([
                'user',
                'saldoPenjual',
                'creator',
                'updater'
            ])->findOrFail($id_pencairan);

            return response()->json([
                'status' => 'success',
                'data' => $pencairan
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawal detail: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil detail pencairan'
            ], 500);
        }
    }

    /**
     * Process withdrawal request (approve/reject)
     */
    public function processPencairan(Request $request, $id_pencairan)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,complete',
            'catatan_admin' => 'nullable|string|max:1000'
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
            $admin = Auth::user();
            $pencairan = PengajuanPencairan::with('saldoPenjual')->findOrFail($id_pencairan);

            // Validate action based on current status
            if ($request->action === 'approve' && !$pencairan->canBeApproved()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengajuan pencairan tidak dapat disetujui'
                ], 422);
            }

            if ($request->action === 'reject' && !$pencairan->canBeRejected()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengajuan pencairan tidak dapat ditolak'
                ], 422);
            }

            switch ($request->action) {
                case 'approve':
                    $this->approvePencairan($pencairan, $admin, $request->catatan_admin);
                    break;
                    
                case 'reject':
                    $this->rejectPencairan($pencairan, $admin, $request->catatan_admin);
                    break;
                    
                case 'complete':
                    $this->completePencairan($pencairan, $admin, $request->catatan_admin);
                    break;
            }

            DB::commit();

            $actionText = [
                'approve' => 'disetujui',
                'reject' => 'ditolak', 
                'complete' => 'diselesaikan'
            ];

            return response()->json([
                'status' => 'success',
                'message' => "Pengajuan pencairan berhasil {$actionText[$request->action]}",
                'data' => $pencairan->fresh(['user', 'saldoPenjual', 'creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing withdrawal: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pengajuan pencairan'
            ], 500);
        }
    }

    /**
     * Approve withdrawal request
     */
    private function approvePencairan($pencairan, $admin, $catatan = null)
    {
        $pencairan->status_pencairan = 'Diproses';
        $pencairan->catatan_admin = $catatan;
        $pencairan->updated_by = $admin->id_user;
        $pencairan->save();

        Log::info('Withdrawal approved', [
            'id_pencairan' => $pencairan->id_pencairan,
            'admin_id' => $admin->id_user,
            'amount' => $pencairan->jumlah_dana
        ]);
    }

    /**
     * Reject withdrawal request
     */
    private function rejectPencairan($pencairan, $admin, $catatan = null)
    {
        // Release held balance back to available balance
        $saldoPenjual = $pencairan->saldoPenjual;
        $releaseSuccess = $saldoPenjual->releaseHeldBalance($pencairan->jumlah_dana);
        
        if (!$releaseSuccess) {
            throw new \Exception('Gagal melepas saldo yang ditahan');
        }

        $pencairan->status_pencairan = 'Ditolak';
        $pencairan->catatan_admin = $catatan;
        $pencairan->updated_by = $admin->id_user;
        $pencairan->save();

        Log::info('Withdrawal rejected', [
            'id_pencairan' => $pencairan->id_pencairan,
            'admin_id' => $admin->id_user,
            'amount' => $pencairan->jumlah_dana
        ]);
    }

    /**
     * Complete withdrawal request (mark as transferred)
     */
    private function completePencairan($pencairan, $admin, $catatan = null)
    {
        if ($pencairan->status_pencairan !== 'Diproses') {
            throw new \Exception('Pengajuan pencairan harus berstatus Diproses');
        }

        // Withdraw balance (remove from held balance permanently)
        $saldoPenjual = $pencairan->saldoPenjual;
        $withdrawSuccess = $saldoPenjual->withdrawBalance($pencairan->jumlah_dana);
        
        if (!$withdrawSuccess) {
            throw new \Exception('Gagal mengurangi saldo tertahan');
        }

        $pencairan->status_pencairan = 'Selesai';
        $pencairan->tanggal_pencairan = now();
        $pencairan->catatan_admin = $catatan;
        $pencairan->updated_by = $admin->id_user;
        $pencairan->save();

        Log::info('Withdrawal completed', [
            'id_pencairan' => $pencairan->id_pencairan,
            'admin_id' => $admin->id_user,
            'amount' => $pencairan->jumlah_dana
        ]);
    }

    /**
     * Add admin comment to withdrawal request
     */
    public function addComment(Request $request, $id_pencairan)
    {
        $validator = Validator::make($request->all(), [
            'catatan_admin' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Auth::user();
            $pencairan = PengajuanPencairan::findOrFail($id_pencairan);

            $pencairan->catatan_admin = $request->catatan_admin;
            $pencairan->updated_by = $admin->id_user;
            $pencairan->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Catatan berhasil ditambahkan',
                'data' => $pencairan->fresh(['user', 'saldoPenjual', 'creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding comment to withdrawal: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan catatan'
            ], 500);
        }
    }

    /**
     * Get withdrawal statistics
     */
    public function getPencairanStats()
    {
        try {
            $pencairanStatuses = PengajuanPencairan::select('status_pencairan', DB::raw('count(*) as count'))
                ->groupBy('status_pencairan')
                ->get()
                ->pluck('count', 'status_pencairan')
                ->toArray();

            $totalAmount = PengajuanPencairan::sum('jumlah_dana');
            $approvedAmount = PengajuanPencairan::where('status_pencairan', 'Selesai')->sum('jumlah_dana');
            $pendingAmount = PengajuanPencairan::whereIn('status_pencairan', ['Menunggu', 'Diproses'])->sum('jumlah_dana');

            $monthlyStats = PengajuanPencairan::select(
                    DB::raw('YEAR(tanggal_pengajuan) as year'),
                    DB::raw('MONTH(tanggal_pengajuan) as month'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(jumlah_dana) as total_amount')
                )
                ->where('tanggal_pengajuan', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            $recentPencairan = PengajuanPencairan::with('user:id_user,name,email')
                ->latest('tanggal_pengajuan')
                ->limit(5)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'pencairan_statuses' => $pencairanStatuses,
                    'total_amount' => $totalAmount,
                    'approved_amount' => $approvedAmount,
                    'pending_amount' => $pendingAmount,
                    'monthly_stats' => $monthlyStats,
                    'recent_pencairan' => $recentPencairan
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching withdrawal stats: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil statistik pencairan'
            ], 500);
        }
    }

    /**
     * Bulk process withdrawal requests
     */
    public function bulkProcess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'withdrawal_ids' => 'required|array|min:1',
            'withdrawal_ids.*' => 'exists:pengajuan_pencairan,id_pencairan',
            'action' => 'required|in:approve,reject',
            'catatan_admin' => 'nullable|string|max:1000'
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
            $admin = Auth::user();
            $processedCount = 0;
            $errors = [];

            foreach ($request->withdrawal_ids as $id) {
                try {
                    $pencairan = PengajuanPencairan::with('saldoPenjual')->findOrFail($id);
                    
                    if ($request->action === 'approve' && $pencairan->canBeApproved()) {
                        $this->approvePencairan($pencairan, $admin, $request->catatan_admin);
                        $processedCount++;
                    } elseif ($request->action === 'reject' && $pencairan->canBeRejected()) {
                        $this->rejectPencairan($pencairan, $admin, $request->catatan_admin);
                        $processedCount++;
                    } else {
                        $errors[] = "Pencairan ID {$id} tidak dapat diproses";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error processing withdrawal ID {$id}: " . $e->getMessage();
                }
            }

            DB::commit();

            $response = [
                'status' => 'success',
                'message' => "Berhasil memproses {$processedCount} pengajuan pencairan",
                'processed_count' => $processedCount
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk processing withdrawals: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pengajuan pencairan secara bulk'
            ], 500);
        }
    }
}
