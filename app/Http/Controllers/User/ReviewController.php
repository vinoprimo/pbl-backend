<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Pembelian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Store a newly created review
     */
    public function store(Request $request, $id_pembelian)
    {
        // Get authenticated user
        $user = Auth::user();

        // Find the purchase with its review and ensure it belongs to the user
        $pembelian = Pembelian::with('review')
                            ->where('id_pembelian', $id_pembelian)
                            ->where('id_pembeli', $user->id_user)
                            ->where('status_pembelian', 'Selesai')
                            ->first();

        if (!$pembelian) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pembelian tidak ditemukan atau belum selesai'
            ], 404);
        }

        // Check if review already exists using the relationship
        if ($pembelian->review) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah memberikan review untuk pembelian ini'
            ], 400);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'komentar' => 'required|string|max:1000',
            'image_review' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle image upload if present
        $imageUrl = null;
        if ($request->hasFile('image_review')) {
            $file = $request->file('image_review');
            $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $directoryPath = 'reviews/' . $id_pembelian;
            $path = $file->storeAs($directoryPath, $fileName, 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        // Create review
        $review = new Review();
        $review->id_user = $user->id_user;
        $review->id_pembelian = $id_pembelian;
        $review->rating = $request->rating;
        $review->komentar = $request->komentar;
        $review->image_review = $imageUrl;
        $review->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Review berhasil ditambahkan',
            'data' => $review
        ], 201);
    }

    /**
     * Display the specified review
     */
    public function show($id_pembelian)
    {
        $user = Auth::user();

        $review = Review::where('id_pembelian', $id_pembelian)
                       ->with(['user:id_user,name,username,foto_profil'])
                       ->first();

        if (!$review) {
            return response()->json([
                'status' => 'error',
                'message' => 'Review tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $review
        ]);
    }

    /**
     * Remove the specified review
     */
    public function destroy($id_review)
    {
        $user = Auth::user();

        $review = Review::where('id_review', $id_review)
                       ->where('id_user', $user->id_user)
                       ->first();

        if (!$review) {
            return response()->json([
                'status' => 'error',
                'message' => 'Review tidak ditemukan'
            ], 404);
        }

        // Delete image if exists
        if ($review->image_review) {
            $path = str_replace('/storage/', '', parse_url($review->image_review, PHP_URL_PATH));
            Storage::disk('public')->delete($path);
        }

        $review->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Review berhasil dihapus'
        ]);
    }

    /**
     * Get all reviews for a purchase
     */
    public function getByPembelian($id_pembelian)
    {
        $reviews = Review::where('id_pembelian', $id_pembelian)
                        ->with(['user:id_user,name,username,foto_profil'])
                        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $reviews
        ]);
    }
}
