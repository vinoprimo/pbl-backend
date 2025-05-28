<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Komplain;
use App\Models\Pembelian;
use Carbon\Carbon;

class KomplainManagementController extends Controller
{
    /**
     * Display a listing of complaints with optional filtering
     */
    public function index(Request $request)
    {
        try {
            // Debug log
            Log::info('Fetching komplain with params:', $request->all());

            $query = Komplain::with([
                'user:id_user,name,email',
                'pembelian' => function($q) {
                    $q->select('id_pembelian', 'kode_pembelian', 'status_pembelian');
                }
            ]);
            
            // Apply filters
            if ($request->filled('status')) {
                $query->where('status_komplain', $request->status);
            }
            
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->whereHas('user', function($subQuery) use ($searchTerm) {
                        $subQuery->where('name', 'like', "%{$searchTerm}%")
                                ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('pembelian', function($subQuery) use ($searchTerm) {
                        $subQuery->where('kode_pembelian', 'like', "%{$searchTerm}%");
                    });
                });
            }
            
            // Default sort by newest
            $query->latest();
            
            // Paginate results
            $perPage = $request->input('per_page', 10);
            $komplains = $query->paginate($perPage);

            // Debug log
            Log::info('Successfully fetched komplain data', [
                'count' => $komplains->count(),
                'total' => $komplains->total()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data komplain retrieved successfully',
                'data' => $komplains
            ]);

        } catch (\Exception $e) {
            Log::error('Error in KomplainManagementController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch komplain data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get complaint statistics
     */
    public function getComplaintStats()
    {
        try {
            // Get count of complaints by status
            $complaintStatuses = Komplain::select('status_komplain', DB::raw('count(*) as count'))
                ->groupBy('status_komplain')
                ->get()
                ->pluck('count', 'status_komplain')
                ->toArray();
            
            // Get complaints for last 7 days
            $lastWeekComplaints = Komplain::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Get complaint types distribution
            $complaintTypes = Komplain::select('alasan_komplain', DB::raw('count(*) as count'))
                ->groupBy('alasan_komplain')
                ->get()
                ->pluck('count', 'alasan_komplain')
                ->toArray();
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'complaint_statuses' => $complaintStatuses,
                    'last_week_complaints' => $lastWeekComplaints,
                    'complaint_types' => $complaintTypes
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching complaint stats: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch complaint statistics'
            ], 500);
        }
    }

    /**
     * Show complaint details
     */
    public function show($id_komplain)
    {
        try {
            Log::info('Fetching complaint details:', ['id' => $id_komplain]);

            $complaint = Komplain::with([
                'user:id_user,name,email',
                'pembelian' => function($query) {
                    $query->select(
                        'id_pembelian', 
                        'kode_pembelian', 
                        'status_pembelian', 
                        'catatan_pembeli'
                    )->with([
                        'detailPembelian' => function($q) {
                            $q->select(
                                'id_detail',
                                'id_pembelian',
                                'id_barang',
                                'id_toko',
                                'jumlah',
                                'harga_satuan',
                                'subtotal'
                            )->with([
                                'barang:id_barang,nama_barang,harga'
                            ]);
                        },
                        'alamat' => function($q) {
                            $q->with(['province', 'regency', 'district', 'village']);
                        }
                    ]);
                },
                'retur'
            ])->findOrFail($id_komplain);

            Log::info('Successfully fetched complaint details', [
                'complaint_id' => $complaint->id_komplain,
                'user' => $complaint->user ? $complaint->user->name : 'No user',
                'pembelian' => $complaint->pembelian ? $complaint->pembelian->kode_pembelian : 'No order',
                'detail_pembelian' => $complaint->pembelian ? 
                    $complaint->pembelian->detailPembelian->first()?->id_detail : 'No detail'
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $complaint
            ]);

        } catch (\Exception $e) {
            Log::error('Error in complaint details:', [
                'complaint_id' => $id_komplain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch complaint details',
                'debug_message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Process complaint (accept/reject)
     */
    public function processComplaint(Request $request, $id_komplain)
    {
        try {
            $request->validate([
                'status' => 'required|in:Diproses,Ditolak',
                'admin_notes' => 'required|string'
            ]);

            DB::beginTransaction();

            $complaint = Komplain::findOrFail($id_komplain);
            
            if (!$complaint->canBeProcessed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Komplain tidak dapat diproses dalam status saat ini'
                ], 422);
            }

            // Update complaint status
            $complaint->status_komplain = $request->status;
            $complaint->admin_notes = $request->admin_notes;
            $complaint->processed_by = Auth::id();
            $complaint->processed_at = now();
            $complaint->save();

            // If complaint is rejected, update purchase status to Selesai
            if ($request->status === 'Ditolak') {
                $pembelian = Pembelian::where('id_pembelian', $complaint->id_pembelian)->first();
                if ($pembelian) {
                    $pembelian->status_pembelian = 'Selesai';
                    $pembelian->updated_by = Auth::id();
                    $pembelian->save();

                    Log::info('Purchase status updated after complaint rejection', [
                        'pembelian_id' => $pembelian->id_pembelian,
                        'new_status' => 'Selesai'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $request->status === 'Diproses' ? 
                    'Komplain diterima dan akan diproses' : 
                    'Komplain telah ditolak dan pesanan diselesaikan',
                'data' => $complaint
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing complaint: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses komplain'
            ], 500);
        }
    }

    /**
     * Add admin comment to complaint
     */
    public function addComment(Request $request, $id_komplain)
    {
        try {
            $request->validate([
                'comment' => 'required|string'
            ]);

            $complaint = Komplain::findOrFail($id_komplain);
            
            // Add the new comment to existing admin notes
            $complaint->admin_notes = $complaint->admin_notes 
                ? $complaint->admin_notes . "\n" . now() . ": " . $request->comment
                : now() . ": " . $request->comment;
            
            $complaint->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Comment added successfully',
                'data' => $complaint
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding comment to complaint: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add comment'
            ], 500);
        }
    }
}
