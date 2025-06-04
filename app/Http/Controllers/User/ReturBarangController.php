<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ReturBarang;
use App\Models\Pembelian;
use App\Models\Komplain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ReturBarangController extends Controller
{
    public function store(Request $request)
    {
        try {
            Log::info('Retur request received:', $request->all());

            $validator = Validator::make($request->all(), [
                'id_pembelian' => 'required|exists:pembelian,id_pembelian',
                'id_detail_pembelian' => 'required|exists:detail_pembelian,id_detail',
                'id_komplain' => 'required|exists:komplain,id_komplain',
                'alasan_retur' => 'required|in:Barang Rusak,Tidak Sesuai Deskripsi,Salah Kirim,Lainnya',
                'deskripsi_retur' => 'required|string|max:1000',
                'foto_bukti' => 'required|file|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify ownership and eligibility
            $komplain = Komplain::where('id_komplain', $request->id_komplain)
                ->where('id_user', Auth::id())
                ->where('status_komplain', 'Diproses')
                ->first();

            if (!$komplain) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Komplain tidak ditemukan atau tidak eligible untuk retur'
                ], 404);
            }

            // Handle image upload
            $imageUrl = null;
            if ($request->hasFile('foto_bukti')) {
                $file = $request->file('foto_bukti');
                $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $path = $file->storeAs('retur_barang', $fileName, 'public');
                $imageUrl = Storage::disk('public')->url($path);
            }

            $retur = new ReturBarang();
            $retur->id_user = Auth::id();
            $retur->id_pembelian = $request->id_pembelian;
            $retur->id_detail_pembelian = $request->id_detail_pembelian;
            $retur->id_komplain = $request->id_komplain;
            $retur->alasan_retur = $request->alasan_retur;
            $retur->deskripsi_retur = $request->deskripsi_retur;
            $retur->foto_bukti = $imageUrl;
            $retur->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan retur berhasil dibuat',
                'data' => $retur
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error in retur creation: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membuat retur'
            ], 500);
        }
    }

    public function show($id_retur)
    {
        try {
            $retur = ReturBarang::with(['user', 'pembelian', 'detailPembelian', 'komplain'])
                ->where('id_retur', $id_retur)
                ->where('id_user', Auth::id())
                ->first();

            if (!$retur) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Retur tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $retur
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching retur: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data retur'
            ], 500);
        }
    }

    public function getByUser()
    {
        try {
            $returs = ReturBarang::with(['pembelian', 'detailPembelian'])
                ->where('id_user', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $returs
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user returs: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data retur'
            ], 500);
        }
    }
}
