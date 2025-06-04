<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Toko;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BarangController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request)
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
        
        // Query products from the user's shop with pagination
        $barang = Barang::where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->with(['kategori', 'gambarBarang' => function($query) {
                            $query->where('is_primary', true)->orderBy('urutan', 'asc');
                        }])
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);
        
        return response()->json([
            'status' => 'success',
            'data' => $barang
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
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
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'id_kategori' => 'required|exists:kategori,id_kategori',
            'nama_barang' => 'required|string|max:255',
            'deskripsi_barang' => 'required|string',
            'harga' => 'required|numeric|min:0',
            'grade' => 'required|string|max:255',
            'status_barang' => 'required|in:Tersedia,Terjual,Habis',
            'stok' => 'required|integer|min:0',
            'kondisi_detail' => 'required|string',
            'berat_barang' => 'required|numeric|min:0',
            'dimensi' => 'required|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Create a slug from the product name
        $slug = Str::slug($request->nama_barang);
        $originalSlug = $slug;
        $count = 1;
        
        // Ensure the slug is unique
        while (Barang::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }
        
        // Create the new product
        $barang = new Barang();
        $barang->id_kategori = $request->id_kategori;
        $barang->id_toko = $toko->id_toko;
        $barang->nama_barang = $request->nama_barang;
        $barang->slug = $slug;
        $barang->deskripsi_barang = $request->deskripsi_barang;
        $barang->harga = $request->harga;
        $barang->grade = $request->grade;
        $barang->status_barang = $request->status_barang;
        $barang->stok = $request->stok;
        $barang->kondisi_detail = $request->kondisi_detail;
        $barang->berat_barang = $request->berat_barang;
        $barang->dimensi = $request->dimensi;
        $barang->created_by = $user->id_user;
        $barang->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil ditambahkan',
            'data' => $barang
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show($id)
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
        $barang = Barang::where('id_barang', $id)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->with(['kategori', 'gambarBarang' => function($query) {
                            $query->orderBy('is_primary', 'desc')->orderBy('urutan', 'asc');
                        }])
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $barang
        ]);
    }

    /**
     * Display the specified product by slug.
     */
    public function getBySlug($slug)
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
                        ->with(['kategori', 'toko', 'gambarBarang' => function($query) {
                            $query->orderBy('is_primary', 'desc')->orderBy('urutan', 'asc');
                        }])
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $barang
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, $id)
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
        $barang = Barang::where('id_barang', $id)
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
            'id_kategori' => 'exists:kategori,id_kategori',
            'nama_barang' => 'string|max:255',
            'deskripsi_barang' => 'string',
            'harga' => 'numeric|min:0',
            'grade' => 'string|max:255',
            'status_barang' => 'in:Tersedia,Terjual,Habis',
            'stok' => 'integer|min:0',
            'kondisi_detail' => 'string',
            'berat_barang' => 'numeric|min:0',
            'dimensi' => 'string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
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
            $barang->nama_barang = $request->nama_barang;
        }
        
        // Update the fields if they are present in the request
        if ($request->has('id_kategori')) $barang->id_kategori = $request->id_kategori;
        if ($request->has('deskripsi_barang')) $barang->deskripsi_barang = $request->deskripsi_barang;
        if ($request->has('harga')) $barang->harga = $request->harga;
        if ($request->has('grade')) $barang->grade = $request->grade;
        if ($request->has('status_barang')) $barang->status_barang = $request->status_barang;
        if ($request->has('stok')) $barang->stok = $request->stok;
        if ($request->has('kondisi_detail')) $barang->kondisi_detail = $request->kondisi_detail;
        if ($request->has('berat_barang')) $barang->berat_barang = $request->berat_barang;
        if ($request->has('dimensi')) $barang->dimensi = $request->dimensi;
        
        $barang->updated_by = $user->id_user;
        $barang->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data' => $barang
        ]);
    }

    /**
     * Update the specified product by slug.
     */
    public function updateBySlug(Request $request, $slug)
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
            'id_kategori' => 'exists:kategori,id_kategori',
            'nama_barang' => 'string|max:255',
            'deskripsi_barang' => 'string',
            'harga' => 'numeric|min:0',
            'grade' => 'string|max:255',
            'status_barang' => 'in:Tersedia,Terjual,Habis',
            'stok' => 'integer|min:0',
            'kondisi_detail' => 'string',
            'berat_barang' => 'numeric|min:0',
            'dimensi' => 'string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Update the product fields
        if ($request->has('nama_barang') && $barang->nama_barang != $request->nama_barang) {
            $newSlug = Str::slug($request->nama_barang);
            $originalSlug = $newSlug;
            $count = 1;
            
            // Ensure the slug is unique
            while (Barang::where('slug', $newSlug)->where('id_barang', '!=', $barang->id_barang)->exists()) {
                $newSlug = $originalSlug . '-' . $count++;
            }
            
            $barang->slug = $newSlug;
            $barang->nama_barang = $request->nama_barang;
        }
        
        if ($request->has('id_kategori')) $barang->id_kategori = $request->id_kategori;
        if ($request->has('deskripsi_barang')) $barang->deskripsi_barang = $request->deskripsi_barang;
        if ($request->has('harga')) $barang->harga = $request->harga;
        if ($request->has('grade')) $barang->grade = $request->grade;
        if ($request->has('status_barang')) $barang->status_barang = $request->status_barang;
        if ($request->has('stok')) $barang->stok = $request->stok;
        if ($request->has('kondisi_detail')) $barang->kondisi_detail = $request->kondisi_detail;
        if ($request->has('berat_barang')) $barang->berat_barang = $request->berat_barang;
        if ($request->has('dimensi')) $barang->dimensi = $request->dimensi;
        
        $barang->updated_by = $user->id_user;
        $barang->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data' => $barang
        ]);
    }
    
    /**
     * Remove the specified product (soft delete).
     */
    public function destroy($id)
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
        $barang = Barang::where('id_barang', $id)
                        ->where('id_toko', $toko->id_toko)
                        ->where('is_deleted', false)
                        ->first();
                        
        if (!$barang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Soft delete the product
        $barang->is_deleted = true;
        $barang->updated_by = $user->id_user;
        $barang->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil dihapus'
        ]);
    }

    /**
     * Remove the specified product by slug (soft delete).
     */
    public function destroyBySlug($slug)
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
        
        // Soft delete the product
        $barang->is_deleted = true;
        $barang->updated_by = $user->id_user;
        $barang->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil dihapus'
        ]);
    }

    /**
     * Get all products for public catalog
     */
    public function getPublicProducts(Request $request)
    {
        // Get query parameters
        $search = $request->input('search');
        $category = $request->input('category');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $perPage = $request->input('per_page', 12);
        
        // Start building the query
        $query = Barang::where('is_deleted', false)
                      ->where('status_barang', 'Tersedia')
                      ->with(['kategori', 'toko', 'gambarBarang' => function($query) {
                          $query->where('is_primary', true)->orderBy('urutan', 'asc');
                      }]);
        
        // Apply search filter if provided
        if ($search) {
            $query->where('nama_barang', 'like', "%{$search}%")
                  ->orWhere('deskripsi_barang', 'like', "%{$search}%");
        }
        
        // Apply category filter if provided
        if ($category) {
            $query->where('id_kategori', $category);
        }
        
        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);
        
        // Get paginated results
        $products = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Get a specific product by slug for public display
     */
    public function getPublicProductBySlug(string $slug): \Illuminate\Http\JsonResponse
    {
        try {
            Log::info("Fetching product by slug: {$slug}");
            
            // First, find the product without relationships
            $barang = Barang::where('slug', $slug)
                ->where('status_barang', 'Tersedia')
                ->where('is_deleted', false)
                ->first();

            if (!$barang) {
                Log::warning("Product not found: {$slug}");
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ], 404);
            }

            // Load relationships separately to avoid complex query issues
            $barang->load([
                'kategori',
                'gambarBarang' => function($query) {
                    $query->orderBy('is_primary', 'desc')
                          ->orderBy('created_at', 'asc');
                }
            ]);

            // Load store information separately
            $toko = Toko::where('id_toko', $barang->id_toko)
                ->where('is_deleted', false)
                ->first();

            if ($toko) {
                // Manually add toko data to the response
                $barang->toko = $toko;
                
                // Load store addresses if needed
                $alamatToko = \App\Models\AlamatToko::where('id_toko', $toko->id_toko)
                    ->with(['province', 'regency'])
                    ->get();
                
                $barang->toko->alamat_toko = $alamatToko;
            }

            Log::info("Product found successfully: {$barang->nama_barang}");

            return response()->json([
                'status' => 'success',
                'data' => $barang
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching product by slug {$slug}: " . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch product',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get featured products for public display
     */
    public function getFeaturedProducts()
    {
        try {
            // Debug: Get total count of all products
            $totalProducts = Barang::count();
            \Log::info('Total products in database: ' . $totalProducts);

            // Start with base query
            $query = Barang::query();
            
            // Add conditions one by one and log counts
            $query->where('is_deleted', false);
            \Log::info('Products after is_deleted check: ' . $query->count());
            
            $query->where('status_barang', 'Tersedia');
            \Log::info('Products after status check: ' . $query->count());

            // Add relationships
            $query->with(['kategori', 'toko', 'gambarBarang' => function($query) {
                $query->orderBy('urutan', 'asc');
            }]);

            // Order by most recent
            $query->orderBy('created_at', 'desc');

            // Get featured products
            $products = $query->take(10)->get();
            
            \Log::info('Final products count: ' . $products->count());
            \Log::info('Product IDs returned: ' . $products->pluck('id_barang'));

            return response()->json([
                'status' => 'success',
                'debug_info' => [
                    'total_products' => $totalProducts,
                    'filtered_count' => $products->count(),
                    'conditions_used' => [
                        'is_deleted' => false,
                        'status_barang' => 'Tersedia'
                    ]
                ],
                'data' => $products
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getFeaturedProducts: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch featured products',
                'debug_error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getRecommendedProducts()
    {
        try {
            \Log::info('Fetching recommended products...');
            
            $products = Barang::where('is_deleted', false)
                ->where('status_barang', 'Tersedia')
                ->with(['kategori', 'toko', 'gambar_barang' => function($query) {
                    $query->where('is_primary', true)
                          ->orderBy('urutan', 'asc');
                }])
                ->inRandomOrder()
                ->take(12)
                ->get()
                ->map(function ($product) {
                    if ($product->gambar_barang) {
                        foreach ($product->gambar_barang as &$gambar) {
                            // Ensure the image URL is formatted correctly without duplication
                            $gambar->url_gambar = trim($gambar->url_gambar);
                        }
                    }
                    return $product;
                });

            \Log::info('Found ' . $products->count() . ' recommended products');

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getRecommendedProducts: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recommended products'
            ], 500);
        }
    }
}