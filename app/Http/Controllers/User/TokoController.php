<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Toko;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TokoController extends Controller
{
    /**
     * Get authenticated user's store
     */
    public function getMyStore()
    {
        // Get current authenticated user
        $user = Auth::user();
        
        // If not authenticated, return error
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        // Get user's store using the relationship in the database
        $toko = Toko::where('id_user', $user->id_user)
                    ->where('is_deleted', false)
                    ->first();
        
        // If no store found, return 404
        if (!$toko) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan'
            ], 404);
        }
        
        // Return the store data
        return response()->json([
            'success' => true,
            'data' => $toko
        ]);
    }

    /**
     * Get store by specific ID (for direct access)
     */
    public function getById($id)
    {
        // Convert ID to integer for safety
        $id = (int)$id;
        
        // Get the store by ID
        $toko = Toko::find($id);
        
        // If no store found, return 404
        if (!$toko) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan'
            ], 404);
        }
        
        // Return the store data
        return response()->json([
            'success' => true,
            'data' => $toko
        ]);
    }

    /**
     * Get store by slug (for public access)
     */
    public function getBySlug($slug)
    {
        $toko = Toko::where('slug', $slug)
                    ->where('is_active', true)
                    ->where('is_deleted', false)
                    ->first();
        
        if (!$toko) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $toko
        ]);
    }

    /**
     * Create a new store
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Check if user already has a store
        $existingToko = Toko::where('id_user', $user->id_user)
                           ->where('is_deleted', false)
                           ->first();
        
        if ($existingToko) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki toko'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'nama_toko' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'kontak' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Create unique slug from name
        $slug = Str::slug($request->nama_toko);
        $originalSlug = $slug;
        $count = 1;
        
        while (Toko::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        // Create the store
        $toko = Toko::create([
            'id_user' => $user->id_user,
            'nama_toko' => $request->nama_toko,
            'slug' => $slug,
            'deskripsi' => $request->deskripsi,
            'kontak' => $request->kontak,
            'is_active' => true,
            'is_deleted' => false,
            'created_by' => $user->id_user,
            'updated_by' => $user->id_user
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Toko berhasil dibuat',
            'data' => $toko
        ], 201);
    }

    /**
     * Update user's store
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        // Get the user's store
        $toko = Toko::where('id_user', $user->id_user)
                    ->where('is_deleted', false)
                    ->first();

        if (!$toko) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_toko' => 'sometimes|string|max:255',
            'deskripsi' => 'sometimes|string',
            'kontak' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Update slug if name changes
        if ($request->has('nama_toko') && $request->nama_toko !== $toko->nama_toko) {
            $slug = Str::slug($request->nama_toko);
            $originalSlug = $slug;
            $count = 1;
            
            while (Toko::where('slug', $slug)->where('id_toko', '!=', $toko->id_toko)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }
            
            $toko->slug = $slug;
        }

        // Update store fields
        $toko->fill($request->only([
            'nama_toko',
            'deskripsi',
            'kontak'
        ]));
        
        $toko->updated_by = $user->id_user;
        $toko->save();

        return response()->json([
            'success' => true,
            'message' => 'Toko berhasil diperbarui',
            'data' => $toko
        ]);
    }

    /**
     * Delete user's store (soft delete)
     */
    public function destroy()
    {
        $user = Auth::user();
        
        // Get the user's store
        $toko = Toko::where('id_user', $user->id_user)
                    ->where('is_deleted', false)
                    ->first();

        if (!$toko) {
            return response()->json([
                'success' => false,
                'message' => 'Toko tidak ditemukan'
            ], 404);
        }

        // Soft delete
        $toko->is_deleted = true;
        $toko->is_active = false;
        $toko->updated_by = $user->id_user;
        $toko->save();

        return response()->json([
            'success' => true,
            'message' => 'Toko berhasil dihapus'
        ]);
    }
}
