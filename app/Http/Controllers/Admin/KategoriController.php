<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class KategoriController extends Controller
{
    public function index()
    {
        try {
            $kategori = Kategori::where('is_deleted', false)->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $kategori
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch categories: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            \Log::info('Creating category:', $request->all());
            
            $validator = Validator::make($request->all(), [
                'nama_kategori' => 'required|string|max:255',
                'is_active' => 'required|in:true,false,0,1',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate base slug
            $baseSlug = Str::slug($request->nama_kategori);
            $slug = $baseSlug;
            
            // Check if slug exists and generate a unique one
            $counter = 1;
            while (Kategori::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $kategori = new Kategori();
            $kategori->nama_kategori = $request->nama_kategori;
            $kategori->slug = $slug; // Use the unique slug
            $kategori->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                // Create unique filename with timestamp and slug
                $fileName = time() . '_' . $slug . '.' . $file->getClientOriginalExtension();
                
                // Store the file
                $path = $file->storeAs('kategori', $fileName, 'public');
                $kategori->logo = $path;
                
                \Log::info('File stored:', [
                    'path' => $path,
                    'full_url' => Storage::disk('public')->url($path)
                ]);
            }

            $kategori->created_by = Auth::id();
            $kategori->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => $kategori
            ]);

        } catch (\Exception $e) {
            \Log::error('Error creating category:', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $kategori = Kategori::find($id);

        if (!$kategori || $kategori->is_deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $kategori
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_kategori' => 'required|string|max:255',
            'is_active' => 'required|in:true,false,0,1',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $kategori = Kategori::find($id);

            if (!$kategori || $kategori->is_deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kategori tidak ditemukan'
                ], 404);
            }

            $kategori->nama_kategori = $request->nama_kategori;
            $kategori->slug = Str::slug($request->nama_kategori);
            $kategori->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);

            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($kategori->logo) {
                    Storage::delete('public/' . $kategori->logo);
                }

                $file = $request->file('logo');
                $filename = time() . '_' . Str::slug($request->nama_kategori) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('public/kategori', $filename);
                $kategori->logo = str_replace('public/', '', $path);
            }

            $kategori->updated_by = Auth::id();
            $kategori->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil diperbarui',
                'data' => $kategori
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui kategori: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy(string $id)
    {
        try {
            $kategori = Kategori::find($id);

            if (!$kategori || $kategori->is_deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kategori tidak ditemukan'
                ], 404);
            }

            // Delete logo file if exists
            if ($kategori->logo) {
                Storage::delete('public/' . $kategori->logo);
            }

            $kategori->is_deleted = true;
            $kategori->updated_by = Auth::id();
            $kategori->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus kategori: ' . $e->getMessage()
            ], 500);
        }
    }
}
