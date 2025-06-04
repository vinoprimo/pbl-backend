<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturBarang;
use App\Models\Pembelian;
use App\Models\Komplain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturBarangManagementController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = ReturBarang::with([
                'user:id_user,name,email',
                'pembelian:id_pembelian,kode_pembelian',
                'komplain:id_komplain,status_komplain'
            ]);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status_retur', $request->status);
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

            $returs = $query->latest()->paginate($request->input('per_page', 10));

            return response()->json([
                'status' => 'success',
                'data' => $returs
            ]);

        } catch (\Exception $e) {
            Log::error('Error in retur index: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data retur'
            ], 500);
        }
    }

    public function show($id_retur)
    {
        try {
            $retur = ReturBarang::with([
                'user',
                'pembelian',
                'detailPembelian.barang',
                'komplain'
            ])->findOrFail($id_retur);

            return response()->json([
                'status' => 'success',
                'data' => $retur
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching retur detail: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil detail retur'
            ], 500);
        }
    }

    public function processRetur(Request $request, $id_retur)
    {
        try {
            $request->validate([
                'status' => 'required|in:Disetujui,Ditolak,Diproses,Selesai',
                'admin_notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $retur = ReturBarang::findOrFail($id_retur);
            $pembelian = Pembelian::findOrFail($retur->id_pembelian); // Get related purchase
            $komplain = Komplain::findOrFail($retur->id_komplain); // Get related complaint

            // Check if the retur can be approved
            if ($retur->status_retur !== 'Menunggu Persetujuan' && $request->status === 'Disetujui') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Retur sudah diproses sebelumnya'
                ], 422);
            }

            $retur->status_retur = $request->status;
            $retur->admin_notes = $request->admin_notes;
            $retur->updated_by = Auth::id();

            if ($request->status === 'Disetujui') {
                $retur->tanggal_disetujui = now();
                $pembelian->status_pembelian = 'Dibatalkan'; // Update purchase status
                $komplain->status_komplain = 'Selesai'; // Update complaint status
            } else if ($request->status === 'Ditolak') {
                $pembelian->status_pembelian = 'Selesai'; // Update purchase status
            } else if ($request->status === 'Selesai') {
                $retur->tanggal_selesai = now();
            }

            $retur->save();
            $pembelian->save(); // Save the purchase
            $komplain->save(); // Save the complaint

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Retur berhasil diproses',
                'data' => $retur
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing retur: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses retur'
            ], 500);
        }
    }


    public function getReturStats()
    {
        try {
            $returStatuses = ReturBarang::select('status_retur', DB::raw('count(*) as count'))
                ->groupBy('status_retur')
                ->get()
                ->pluck('count', 'status_retur')
                ->toArray();

            $returTypes = ReturBarang::select('alasan_retur', DB::raw('count(*) as count'))
                ->groupBy('alasan_retur')
                ->get()
                ->pluck('count', 'alasan_retur')
                ->toArray();

            $lastWeekReturs = ReturBarang::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'retur_statuses' => $returStatuses,
                    'retur_types' => $returTypes,
                    'last_week_returs' => $lastWeekReturs
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching retur stats: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil statistik retur'
            ], 500);
        }
    }
}
