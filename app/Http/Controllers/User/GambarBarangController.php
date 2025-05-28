<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\GambarBarang;
use App\Models\Toko;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GambarBarangController extends Controller
{
    /**
     * Store a newly created product image.
     */
    public function store(Request $request, $id_barang)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get the user's shop
        $toko = Toko::where('id_user', $user->id_user)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki toko'
            ], 404);
        }
        
        // Find the product and ensure it belongs to the user's shop
        $barang = Barang::where('id_barang', $id_barang)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'gambar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_primary' => 'boolean',
            'urutan' => 'integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Handle file upload
        if ($request->hasFile('gambar')) {
            $file = $request->file('gambar');
            // Generate unique filename
            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            
            // Define directory path - store in public/products/[product_id]
            $directoryPath = 'products/' . $barang->id_barang;
            
            // Store the file in public disk
            $path = $file->storeAs($directoryPath, $fileName, 'public');
            
            // Generate URL that can be accessed publicly
            $url = Storage::disk('public')->url($path);
            
            // If this is set to primary, update all other images of this product
            if ($request->input('is_primary', false)) {
                GambarBarang::where('id_barang', $barang->id_barang)
                           ->where('is_primary', true)
                           ->update(['is_primary' => false]);
            }
            
            // Get the next urutan if not provided
            $urutan = $request->input('urutan', 0);
            if ($urutan == 0) {
                $lastUrutan = GambarBarang::where('id_barang', $barang->id_barang)
                                         ->max('urutan');
                $urutan = $lastUrutan ? $lastUrutan + 1 : 1;
            }
            
            // Create the image record
            $gambar = new GambarBarang();
            $gambar->id_barang = $barang->id_barang;
            $gambar->url_gambar = $url;
            $gambar->urutan = $urutan;
            $gambar->is_primary = $request->input('is_primary', false);
            $gambar->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Gambar produk berhasil ditambahkan',
                'data' => $gambar
            ], 201);
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'Tidak ada file yang diunggah'
        ], 400);
    }
    
    /**
     * Update the specified product image.
     */
    public function update(Request $request, $id_barang, $id_gambar)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get the user's shop
        $toko = Toko::where('id_user', $user->id_user)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki toko'
            ], 404);
        }
        
        // Find the product and ensure it belongs to the user's shop
        $barang = Barang::where('id_barang', $id_barang)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Find the image
        $gambar = GambarBarang::where('id_gambar', $id_gambar)
                             ->where('id_barang', $id_barang)
                             ->first();
                             
        if (!$gambar) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gambar produk tidak ditemukan'
            ], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'is_primary' => 'boolean',
            'urutan' => 'integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Update the image details
        if ($request->has('is_primary') && $request->is_primary) {
            // If setting this image as primary, update all other images
            GambarBarang::where('id_barang', $barang->id_barang)
                       ->where('is_primary', true)
                       ->update(['is_primary' => false]);
            
            $gambar->is_primary = true;
        } else if ($request->has('is_primary')) {
            $gambar->is_primary = $request->is_primary;
        }
        
        if ($request->has('urutan')) {
            $gambar->urutan = $request->urutan;
        }
        
        $gambar->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Gambar produk berhasil diperbarui',
            'data' => $gambar
        ]);
    }
    
    /**
     * Remove the specified product image.
     */
    public function destroy($id_barang, $id_gambar)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get the user's shop
        $toko = Toko::where('id_user', $user->id_user)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki toko'
            ], 404);
        }
        
        // Find the product and ensure it belongs to the user's shop
        $barang = Barang::where('id_barang', $id_barang)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Find the image
        $gambar = GambarBarang::where('id_gambar', $id_gambar)
                             ->where('id_barang', $id_barang)
                             ->first();
                             
        if (!$gambar) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gambar produk tidak ditemukan'
            ], 404);
        }
        
        // Extract the filename from the URL
        $filePath = parse_url($gambar->url_gambar, PHP_URL_PATH);
        $filePath = str_replace('/storage/', '', $filePath);
        
        // Delete the file from storage if it exists
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
        
        // Delete the record
        $gambar->delete();
        
        // If this was a primary image, set another image as primary
        if ($gambar->is_primary) {
            $newPrimary = GambarBarang::where('id_barang', $barang->id_barang)
                                     ->orderBy('urutan', 'asc')
                                     ->first();
            if ($newPrimary) {
                $newPrimary->is_primary = true;
                $newPrimary->save();
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Gambar produk berhasil dihapus'
        ]);
    }
    
    /**
     * Delete the specified product image by product slug.
     */
    public function destroyByBarangSlug(Request $request, $slug, $id_gambar)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get the user's shop
        $toko = Toko::where('id_user', $user->id_user)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki toko'
            ], 404);
        }
        
        // Find the product by slug and ensure it belongs to the user's shop
        $barang = Barang::where('slug', $slug)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Find the image
        $gambar = GambarBarang::where('id_gambar', $id_gambar)
                             ->where('id_barang', $barang->id_barang)
                             ->first();
                             
        if (!$gambar) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gambar tidak ditemukan'
            ], 404);
        }
        
        // Delete the file from storage
        $path = str_replace('/storage/', '', parse_url($gambar->url_gambar, PHP_URL_PATH));
        Storage::disk('public')->delete($path);
        
        // Delete the database record
        $gambar->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Gambar berhasil dihapus'
        ]);
    }
    
    /**
     * Get all images for a product
     */
    public function index($id_barang)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get the user's shop
        $toko = Toko::where('id_user', $user->id_user)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki toko'
            ], 404);
        }
        
        // Find the product and ensure it belongs to the user's shop
        $barang = Barang::where('id_barang', $id_barang)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Get all images for this product
        $gambar = GambarBarang::where('id_barang', $id_barang)
                             ->orderBy('is_primary', 'desc')
                             ->orderBy('urutan', 'asc')
                             ->get();
                             
        return response()->json([
            'status' => 'success',
            'data' => $gambar
        ]);
    }
    
    /**
     * Update the specified product image by product slug.
     */
    public function updateByBarangSlug(Request $request, $slug, $id_gambar)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get the user's shop
        $toko = Toko::where('id_user', $user->id_user)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki toko'
            ], 404);
        }
        
        // Find the product by slug and ensure it belongs to the user's shop
        $barang = Barang::where('slug', $slug)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'is_primary' => 'required|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the image
        $gambar = GambarBarang::where('id_gambar', $id_gambar)
                             ->where('id_barang', $barang->id_barang)
                             ->first();
                             
        if (!$gambar) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gambar tidak ditemukan'
            ], 404);
        }
        
        // If this image is being set as primary, update all other images of this product
        if ($request->input('is_primary', false)) {
            GambarBarang::where('id_barang', $barang->id_barang)
                       ->where('is_primary', true)
                       ->update(['is_primary' => false]);
        }
        
        // Update the image record
        $gambar->is_primary = $request->input('is_primary');
        $gambar->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Status gambar berhasil diperbarui',
            'data' => $gambar
        ]);
    }

    /**
     * Store a new image by product slug.
     */
    public function storeByBarangSlug(Request $request, $slug)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get the user's shop
        $toko = Toko::where('id_user', $user->id_user)->first();
        
        if (!$toko) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum memiliki toko'
            ], 404);
        }
        
        // Find the product by slug and ensure it belongs to the user's shop
        $barang = Barang::where('slug', $slug)
                    ->where('id_toko', $toko->id_toko)
                    ->where('is_deleted', false)
                    ->first();
                    
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'gambar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_primary' => 'boolean',
            'urutan' => 'integer|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Handle file upload
        if ($request->hasFile('gambar')) {
            $file = $request->file('gambar');
            // Generate unique filename
            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            
            // Define directory path - store in public/products/[product_id]
            $directoryPath = 'products/' . $barang->id_barang;
            
            // Store the file in public disk
            $path = $file->storeAs($directoryPath, $fileName, 'public');
            
            // Generate URL that can be accessed publicly
            $url = Storage::disk('public')->url($path);
            
            // If this is set to primary, update all other images of this product
            if ($request->input('is_primary', false)) {
                GambarBarang::where('id_barang', $barang->id_barang)
                           ->where('is_primary', true)
                           ->update(['is_primary' => false]);
            }
            
            // Get the next urutan if not provided
            $urutan = $request->input('urutan', 0);
            if ($urutan == 0) {
                $lastUrutan = GambarBarang::where('id_barang', $barang->id_barang)
                                         ->max('urutan');
                $urutan = $lastUrutan ? $lastUrutan + 1 : 1;
            }
            
            // Create the image record
            $gambar = new GambarBarang();
            $gambar->id_barang = $barang->id_barang;
            $gambar->url_gambar = $url;
            $gambar->urutan = $urutan;
            $gambar->is_primary = $request->input('is_primary', false);
            $gambar->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Gambar produk berhasil ditambahkan',
                'data' => $gambar
            ], 201);
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'Tidak ada file yang diunggah'
        ], 400);
    }
}
