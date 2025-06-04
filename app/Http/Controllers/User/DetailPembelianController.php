<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Pembelian;
use App\Models\DetailPembelian;
use App\Models\Barang;

class DetailPembelianController extends Controller
{
    /**
     * Display purchase details for a specific purchase
     */
    public function index($kode)
    {
        $user = Auth::user();
        
        // Find purchase by code
        $pembelian = Pembelian::where('kode_pembelian', $kode)
            ->where('id_pembeli', $user->id_user)
            ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan'
            ], 404);
        }
        
        // Get purchase details with product information
        $details = DetailPembelian::where('id_pembelian', $pembelian->id_pembelian)
            ->with(['barang.gambarBarang', 'toko'])
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $details
        ]);
    }
    
    /**
     * Get a specific purchase detail
     */
    public function show($kode_pembelian, $id_detail)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode_pembelian)
            ->where('id_pembeli', $user->id_user)
            ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan'
            ], 404);
        }
        
        // Updated field name from id_detail to id_detail_pembelian
        $detailPembelian = DetailPembelian::where('id_detail_pembelian', $id_detail)
            ->where('id_pembelian', $pembelian->id_pembelian)
            ->with(['barang.gambarBarang', 'toko'])
            ->first();
        
        if (!$detailPembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail pembelian tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $detailPembelian
        ]);
    }
    
    /**
     * Add item to an existing purchase (only for Draft status)
     */
    public function store(Request $request, $kode_pembelian)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode_pembelian)
            ->where('id_pembeli', $user->id_user)
            ->where('status_pembelian', 'Draft')
            ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau tidak dalam status Draft'
            ], 404);
        }
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'id_barang' => 'required|exists:barang,id_barang',
            'jumlah' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Get product details
        $barang = Barang::with('toko')->findOrFail($request->id_barang);
        
        // Check if product is available
        if ($barang->status_barang != 'Tersedia' || $barang->is_deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak tersedia'
            ], 400);
        }
        
        // Check stock availability
        if ($barang->stok < $request->jumlah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stok tidak mencukupi'
            ], 400);
        }
        
        // Check if item from same product already exists in purchase
        $existingDetail = DetailPembelian::where('id_pembelian', $pembelian->id_pembelian)
            ->where('id_barang', $barang->id_barang)
            ->first();
        
        if ($existingDetail) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk sudah ada dalam pembelian ini'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            // Create purchase detail
            $detailPembelian = new DetailPembelian();
            $detailPembelian->id_pembelian = $pembelian->id_pembelian;
            $detailPembelian->id_barang = $barang->id_barang;
            $detailPembelian->id_toko = $barang->id_toko;
            $detailPembelian->harga_satuan = $barang->harga;
            $detailPembelian->jumlah = $request->jumlah;
            $detailPembelian->subtotal = $barang->harga * $request->jumlah;
            $detailPembelian->save();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Item ditambahkan ke pembelian',
                'data' => $detailPembelian
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a purchase detail (only for Draft status)
     */
    public function update(Request $request, $kode_pembelian, $id_detail)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode_pembelian)
            ->where('id_pembeli', $user->id_user)
            ->where('status_pembelian', 'Draft')
            ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau tidak dalam status Draft'
            ], 404);
        }
        
        // Updated field name from id_detail to id_detail_pembelian
        $detailPembelian = DetailPembelian::where('id_detail_pembelian', $id_detail)
            ->where('id_pembelian', $pembelian->id_pembelian)
            ->with('barang')
            ->first();
        
        if (!$detailPembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail pembelian tidak ditemukan'
            ], 404);
        }
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'jumlah' => 'required|integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check stock availability
        if ($detailPembelian->barang->stok < $request->jumlah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stok tidak mencukupi'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            // Update purchase detail
            $detailPembelian->jumlah = $request->jumlah;
            $detailPembelian->subtotal = $detailPembelian->harga_satuan * $request->jumlah;
            $detailPembelian->save();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Detail pembelian berhasil diperbarui',
                'data' => $detailPembelian
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove a purchase detail (only for Draft status)
     */
    public function destroy($kode_pembelian, $id_detail)
    {
        $user = Auth::user();
        
        $pembelian = Pembelian::where('kode_pembelian', $kode_pembelian)
            ->where('id_pembeli', $user->id_user)
            ->where('status_pembelian', 'Draft')
            ->first();
        
        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau tidak dalam status Draft'
            ], 404);
        }
        
        // Updated field name from id_detail to id_detail_pembelian
        $detailPembelian = DetailPembelian::where('id_detail_pembelian', $id_detail)
            ->where('id_pembelian', $pembelian->id_pembelian)
            ->first();
        
        if (!$detailPembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail pembelian tidak ditemukan'
            ], 404);
        }
        
        // Check if this is the last item in the purchase
        $detailCount = DetailPembelian::where('id_pembelian', $pembelian->id_pembelian)->count();
        
        if ($detailCount <= 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus item terakhir dalam pembelian. Batalkan pembelian jika diinginkan.'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            // Delete purchase detail
            $detailPembelian->delete();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Item berhasil dihapus dari pembelian'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
