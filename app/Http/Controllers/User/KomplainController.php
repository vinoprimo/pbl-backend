<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Komplain;
use App\Models\Pembelian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class KomplainController extends Controller
{
    public function store(Request $request, $id_pembelian)
    {
        \Log::info('Komplain request received:', [
            'id_pembelian' => $id_pembelian,
            'user_id' => auth()->id(),
            'has_file' => $request->hasFile('bukti_komplain'),
            'all_data' => $request->all()
        ]);

        // Validate request first
        $validator = Validator::make($request->all(), [
            'alasan_komplain' => 'required|in:Barang Tidak Sesuai,Barang Rusak,Barang Tidak Sampai,Lainnya',
            'isi_komplain' => 'required|string|max:1000',
            'bukti_komplain' => 'required|file|image|mimes:jpeg,png,jpg|max:2048'
        ], [
            'bukti_komplain.required' => 'Bukti foto wajib diunggah',
            'bukti_komplain.image' => 'File harus berupa gambar',
            'bukti_komplain.mimes' => 'Format file harus jpeg, png, atau jpg',
            'bukti_komplain.max' => 'Ukuran file maksimal 2MB'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Find purchase and ensure it belongs to user
        $pembelian = Pembelian::with('komplain')
            ->where('id_pembelian', $id_pembelian)
            ->where('id_pembeli', $user->id_user)
            ->whereIn('status_pembelian', ['Dikirim', 'Diterima']) // Mengubah kondisi status
            ->first();

        // Add debug logging
        \Log::info('Purchase query result:', [
            'found' => $pembelian ? true : false,
            'status' => $pembelian ? $pembelian->status_pembelian : null,
            'user_id' => $user->id_user,
            'id_pembelian' => $id_pembelian
        ]);

        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau tidak dapat dikomplain',
                'debug' => [
                    'id_pembelian' => $id_pembelian,
                    'user_id' => $user->id_user,
                    'status' => $pembelian ? $pembelian->status_pembelian : null
                ]
            ], 404);
        }

        // Check if complaint already exists
        if ($pembelian->komplain) {
            return response()->json([
                'status' => 'error',
                'message' => 'Komplain untuk pembelian ini sudah ada'
            ], 400);
        }

        // Handle image upload
        $imageUrl = null;
        if ($request->hasFile('bukti_komplain')) {
            $file = $request->file('bukti_komplain');
            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $directoryPath = 'komplain/' . $id_pembelian;
            $path = $file->storeAs($directoryPath, $fileName, 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        // Create complaint
        $komplain = new Komplain();
        $komplain->id_user = $user->id_user;
        $komplain->id_pembelian = $id_pembelian;
        $komplain->alasan_komplain = $request->alasan_komplain;
        $komplain->isi_komplain = $request->isi_komplain;
        $komplain->bukti_komplain = $imageUrl;
        $komplain->status_komplain = 'Menunggu';
        $komplain->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Komplain berhasil diajukan',
            'data' => $komplain
        ], 201);
    }

    public function show($id_pembelian)
    {
        try {
            $user = Auth::user();
            
            $komplain = Komplain::where('id_pembelian', $id_pembelian)
                ->where('id_user', $user->id_user)
                ->with([
                    'pembelian' => function($query) {
                        $query->select(
                            'id_pembelian', 
                            'kode_pembelian', 
                            'status_pembelian'
                        )->with([
                            'detailPembelian' => function($q) {
                                $q->select(
                                    'id_detail',
                                    'id_pembelian',
                                    'id_barang',
                                    'id_toko'
                                );
                            }
                        ]);
                    },
                    'retur'
                ])
                ->first();

            if (!$komplain) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Komplain tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $komplain
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in showing complaint: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data komplain'
            ], 500);
        }
    }

    // Add method to check if user can create retur
    private function canCreateRetur(Komplain $komplain): bool
    {
        return $komplain->status_komplain === Komplain::STATUS_DIPROSES && !$komplain->isRejected();
    }

    public function update(Request $request, $id_komplain)
    {
        $user = Auth::user();
        
        $komplain = Komplain::where('id_komplain', $id_komplain)
                         ->where('id_user', $user->id_user)
                         ->where('status_komplain', '!=', 'Selesai')
                         ->first();

        if (!$komplain) {
            return response()->json([
                'status' => 'error',
                'message' => 'Komplain tidak ditemukan atau tidak dapat diubah'
            ], 404);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'isi_komplain' => 'required|string|max:1000',
            'bukti_komplain' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle new image upload if provided
        if ($request->hasFile('bukti_komplain')) {
            // Delete old image if exists
            if ($komplain->bukti_komplain) {
                $oldPath = str_replace('/storage/', '', parse_url($komplain->bukti_komplain, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }

            // Upload new image
            $file = $request->file('bukti_komplain');
            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $directoryPath = 'komplain/' . $komplain->id_pembelian;
            $path = $file->storeAs($directoryPath, $fileName, 'public');
            $komplain->bukti_komplain = Storage::disk('public')->url($path);
        }

        $komplain->isi_komplain = $request->isi_komplain;
        $komplain->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Komplain berhasil diperbarui',
            'data' => $komplain
        ]);
    }

    public function getByUser()
    {
        try {
            $user = Auth::user();
            
            $komplains = Komplain::where('id_user', $user->id_user)
                ->with([
                    'pembelian' => function($query) {
                        $query->select(
                            'id_pembelian', 
                            'kode_pembelian', 
                            'status_pembelian'
                        )->with([
                            'detailPembelian' => function($q) {
                                $q->select(
                                    'id_detail',
                                    'id_pembelian',
                                    'id_barang',
                                    'id_toko'
                                );
                            }
                        ]);
                    },
                    'retur'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $komplains
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching user complaints: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data komplain'
            ], 500);
        }
    }
}
