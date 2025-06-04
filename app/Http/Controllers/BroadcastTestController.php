<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class BroadcastTestController extends Controller
{
    /**
     * Test broadcasting functionality
     */
    public function testBroadcast(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Broadcasting is configured correctly',
            'user' => [
                'id' => $user->id_user,
                'name' => $user->nama
            ],
            'auth_endpoint' => url('/api/broadcasting/auth')
        ]);
    }
}
