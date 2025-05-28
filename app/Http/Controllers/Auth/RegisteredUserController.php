<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'no_hp' => ['required', 'string', 'max:20'],
            'tanggal_lahir' => ['required', 'date'],
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'no_hp' => $request->no_hp,
            'tanggal_lahir' => $request->tanggal_lahir,
            'role' => User::ROLE_USER, // Using constant from User model
            'is_verified' => false,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please login to continue.',
            'data' => [
                'user' => [
                    'id_user' => $user->id_user,
                    'username' => $user->username,
                    'name' => $user->name,
                    'email' => $user->email,
                    'no_hp' => $user->no_hp,
                    'tanggal_lahir' => $user->tanggal_lahir,
                    'role' => $user->role,
                    'role_name' => $user->role_name
                ]
            ]
        ], 201);
    }
}
