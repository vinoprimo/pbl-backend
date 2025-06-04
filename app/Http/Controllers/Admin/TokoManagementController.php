<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Toko;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TokoManagementController extends Controller
{
    /**
     * Display a listing of the stores.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Toko::with('user');
            
            // Default to non-deleted stores
            $query->where('is_deleted', false);
            
            // Search by store name
            if ($request->has('search') && $request->search !== '') {
                $query->where('nama_toko', 'like', '%' . $request->search . '%');
            }
            
            // Get all stores (paginated if specified)
            if ($request->has('per_page')) {
                $toko = $query->paginate($request->per_page);
            } else {
                $toko = $query->get();
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Stores retrieved successfully',
                'data' => $toko
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve stores: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified store by ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $toko = Toko::with('user')->where('id_toko', $id)->where('is_deleted', false)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found',
                    'data' => null
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Store retrieved successfully',
                'data' => $toko
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve store: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified store by slug.
     *
     * @param  string  $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function showBySlug($slug)
    {
        try {
            $toko = Toko::with('user')
                    ->where('slug', $slug)
                    ->where('is_deleted', false)
                    ->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found',
                    'data' => null
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Store retrieved successfully',
                'data' => $toko
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve store: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Store a new store.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id_user' => 'required|exists:users,id_user',
                'nama_toko' => 'required|string|max:255',
                'deskripsi' => 'nullable|string',
                'alamat' => 'nullable|string|max:255',
                'kontak' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);

            // Generate a unique slug
            $slug = Str::slug($validated['nama_toko']);
            $originalSlug = $slug;
            $count = 1;
            
            while (Toko::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }
            
            $validated['slug'] = $slug;
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['is_deleted'] = false;
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            $toko = Toko::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Store created successfully',
                'data' => $toko
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create store: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Update the specified store.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $toko = Toko::where('id_toko', $id)->where('is_deleted', false)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found',
                    'data' => null
                ], 404);
            }
            
            $validated = $request->validate([
                'nama_toko' => 'sometimes|required|string|max:255',
                'deskripsi' => 'nullable|string',
                'alamat' => 'nullable|string|max:255',
                'kontak' => 'nullable|string|max:255',
                'is_active' => 'sometimes|boolean',
            ]);
            
            // If name has changed, update the slug
            if (isset($validated['nama_toko']) && $validated['nama_toko'] !== $toko->nama_toko) {
                $slug = Str::slug($validated['nama_toko']);
                $originalSlug = $slug;
                $count = 1;
                
                while (Toko::where('slug', $slug)->where('id_toko', '!=', $toko->id_toko)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }
                
                $validated['slug'] = $slug;
            }
            
            $validated['updated_by'] = Auth::id();
            
            $toko->update($validated);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Store updated successfully',
                'data' => $toko
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update store: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Soft delete a store.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $toko = Toko::where('id_toko', $id)->where('is_deleted', false)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found',
                    'data' => null
                ], 404);
            }
            
            $toko->update([
                'is_deleted' => true,
                'is_active' => false,
                'updated_by' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Store deleted successfully',
                'data' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete store: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
    
    /**
     * Restore a soft-deleted store.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            $toko = Toko::where('id_toko', $id)->where('is_deleted', true)->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Deleted store not found',
                    'data' => null
                ], 404);
            }
            
            $toko->update([
                'is_deleted' => false,
                'updated_by' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Store restored successfully',
                'data' => $toko
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to restore store: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
