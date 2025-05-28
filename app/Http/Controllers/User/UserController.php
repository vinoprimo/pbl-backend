<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Toko;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get the authenticated user's data with store information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentUser(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('User not authenticated', [
                    'headers' => $request->headers->all(),
                    'has_session' => $request->hasSession()
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'User not authenticated',
                    'data' => null
                ], 401);
            }
            
            // Check if user has a store
            $toko = Toko::where('id_user', $user->id_user)
                       ->where('is_deleted', false)
                       ->first();
            
            // Add store info to user data
            $userData = $user->toArray();
            $userData['has_store'] = (bool)$toko;
            
            if ($toko) {
                $userData['store'] = [
                    'id_toko' => $toko->id_toko,
                    'nama_toko' => $toko->nama_toko,
                    'slug' => $toko->slug
                ];
            }
            
            Log::info('User data retrieved successfully', [
                'id_user' => $user->id_user,
                'has_store' => (bool)$toko
            ]);

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'User data retrieved successfully',
                'data' => $userData
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Failed to retrieve user data: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
