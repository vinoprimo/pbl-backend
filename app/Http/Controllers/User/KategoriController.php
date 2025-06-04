<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use Illuminate\Http\Request;

class KategoriController extends Controller
{
    public function index()
    {
        try {
            $kategori = Kategori::where('is_deleted', false)
                               ->where('is_active', true)
                               ->get();
            
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
}
