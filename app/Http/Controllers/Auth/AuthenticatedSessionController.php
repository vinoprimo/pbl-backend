<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Set HttpOnly cookie with token to prevent JavaScript access
        $cookie = Cookie::make(
            'auth_token',
            $token,
            config('session.lifetime'), // Use same lifetime as session
            null,
            null,
            config('app.env') === 'production', // Secure in production
            true, // HttpOnly
            false,
            'strict' // SameSite policy
        );

        return response()->json([
            'user' => [
                'id' => $user->id_user,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            // Include token in response for API testing
            'access_token' => $token,
            'token_type' => 'Bearer',
            'message' => 'Logged in successfully',
        ])->withCookie($cookie);
    }
    
    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }
        
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return response()->json([
            'message' => 'Logged out successfully',
        ])->withCookie(Cookie::forget('auth_token'));
    }
}
