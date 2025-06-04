<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\GambarBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BarangManagementController extends Controller
{
    /**
     * Display a listing of all products
     */
    public function index()
    {
        try {
            $products = Barang::with([
                'kategori', 
                'toko', 
                'gambarBarang' => function($query) {
                    $query->orderBy('urutan', 'asc');
                }
            ])->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Products retrieved successfully',
                'data' => $products
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching products: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve products: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Filter products with optional parameters
     */
    public function filter(Request $request)
    {
        try {
             // Initialize the query
            $query = Barang::with(['kategori', 'toko', 'gambarBarang' => function($query) {
                $query->orderBy('urutan', 'asc');
            }]);
            
            // Apply filters if provided
            if ($request->has('category_id') && $request->category_id != '') {
                $query->where('id_kategori', $request->category_id);
            }
            
            if ($request->has('search') && $request->search != '') {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('nama_barang', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('deskripsi_barang', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('slug', 'LIKE', "%{$searchTerm}%");
                });
            }
            
            if ($request->has('status') && $request->status != '') {
                $query->where('status_barang', $request->status);
            }
            
            // Sort by price if requested
            if ($request->has('price_sort') && $request->price_sort != '') {
                $direction = $request->price_sort === 'highest' ? 'desc' : 'asc';
                $query->orderBy('harga', $direction);
            } else {
                // Default sort by newest first
                $query->orderBy('id_barang', 'desc');
            }
            
            // Paginate the results
            $perPage = $request->per_page ?? 10;
            $products = $query->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Products filtered successfully',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to filter products: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get list of available categories for filtering.
     */
    public function getCategories()
    {
        try {
            $categories = Kategori::where('is_active', true)
                ->orderBy('nama_kategori')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Categories retrieved successfully',
                'data' => $categories
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching categories: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Display the specified product.
     */
    public function show($id)
    {
        try {
            $barang = Barang::with([
                'kategori', 
                'toko',
                'gambarBarang' => function($query) {
                    $query->orderBy('is_primary', 'desc')
                        ->orderBy('urutan', 'asc');
                }
            ])->find($id);
            
            if (!$barang) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => null
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => $barang
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching product: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Update the specified product.
     */
    public function update(Request $request, $id)
    {
        try {
            // Find the product
            $barang = Barang::find($id);
            
            if (!$barang) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => null
                ], 404);
            }
            
            // Validate the request
            $validator = Validator::make($request->all(), [
                'id_kategori' => 'sometimes|exists:kategori,id_kategori',
                'nama_barang' => 'sometimes|string|max:255',
                'deskripsi_barang' => 'sometimes|nullable|string',
                'harga' => 'sometimes|numeric|min:0',
                'grade' => 'sometimes|string|max:50',
                'status_barang' => 'sometimes|in:Tersedia,Terjual,Habis',
                'stok' => 'sometimes|integer|min:0',
                'kondisi_detail' => 'sometimes|nullable|string',
                'berat_barang' => 'sometimes|numeric|min:0',
                'dimensi' => 'sometimes|nullable|string|max:50',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Update slug if name is updated
            if ($request->has('nama_barang') && $barang->nama_barang != $request->nama_barang) {
                $slug = Str::slug($request->nama_barang);
                $originalSlug = $slug;
                $count = 1;
                
                // Ensure the slug is unique
                while (Barang::where('slug', $slug)->where('id_barang', '!=', $id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }
                
                $barang->slug = $slug;
            }
            
            // Update only the fields that are present in the request
            if ($request->has('nama_barang')) $barang->nama_barang = $request->nama_barang;
            if ($request->has('id_kategori')) $barang->id_kategori = $request->id_kategori;
            if ($request->has('deskripsi_barang')) $barang->deskripsi_barang = $request->deskripsi_barang;
            if ($request->has('harga')) $barang->harga = $request->harga;
            if ($request->has('grade')) $barang->grade = $request->grade;
            if ($request->has('status_barang')) $barang->status_barang = $request->status_barang;
            if ($request->has('stok')) $barang->stok = $request->stok;
            if ($request->has('kondisi_detail')) $barang->kondisi_detail = $request->kondisi_detail;
            if ($request->has('berat_barang')) $barang->berat_barang = $request->berat_barang;
            if ($request->has('dimensi')) $barang->dimensi = $request->dimensi;
            
            $barang->updated_by = Auth::id();
            $barang->save();
            
            // Return the updated product with its relationships
            $updatedBarang = Barang::with(['kategori', 'toko', 'gambarBarang'])->find($id);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $updatedBarang
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error updating product: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Soft delete the specified product.
     */
    public function softDelete($id)
    {
        try {
            $barang = Barang::find($id);
            
            if (!$barang) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => null
                ], 404);
            }
            
            $barang->is_deleted = true;
            $barang->updated_by = Auth::id();
            $barang->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product soft deleted successfully',
                'data' => null
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error soft deleting product: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to soft delete product: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Restore a soft-deleted product.
     */
    public function restore($id)
    {
        try {
            $barang = Barang::find($id);
            
            if (!$barang) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => null
                ], 404);
            }
            
            $barang->is_deleted = false;
            $barang->updated_by = Auth::id();
            $barang->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product restored successfully',
                'data' => $barang
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error restoring product: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to restore product: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Permanently delete a product.
     */
    public function destroy($id)
    {
        try {
            $barang = Barang::find($id);
            
            if (!$barang) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => null
                ], 404);
            }
            
            // Delete associated images first
            GambarBarang::where('id_barang', $id)->delete();
            
            // Delete the product
            $barang->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product permanently deleted',
                'data' => null
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error deleting product: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
