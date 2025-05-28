<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UserManagementController extends Controller
{
    /**
     * Get all users
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $users = User::where('is_deleted', false)->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'data' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve users: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Store a new user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|unique:users,username',
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users,email',
                'password' => 'required|string|min:8',
                'no_hp' => 'nullable|string|max:15',
                'tanggal_lahir' => 'nullable|date',
                'role' => 'required|integer|in:0,1,2',
                'is_verified' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $validated['password'] = Hash::make($validated['password']);
            $validated['is_verified'] = $validated['is_verified'] ?? true;
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['is_deleted'] = false;

            // Handle file upload if provided
            if ($request->hasFile('foto_profil')) {
                $file = $request->file('foto_profil');
                $path = $file->store('profile_photos', 'public');
                $validated['foto_profil'] = $path;
            }

            $user = User::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create user: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get a specific user
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $user = User::where('id_user', $id)->where('is_deleted', false)->first();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User retrieved successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Update a user
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::where('id_user', $id)->where('is_deleted', false)->first();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'data' => null
                ], 404);
            }

            $validated = $request->validate([
                'username' => ['sometimes', 'required', 'string', Rule::unique('users', 'username')->ignore($id, 'id_user')],
                'name' => 'sometimes|required|string|max:255',
                'email' => ['sometimes', 'required', 'string', 'email', Rule::unique('users', 'email')->ignore($id, 'id_user')],
                'password' => 'sometimes|required|string|min:8',
                'no_hp' => 'nullable|string|max:15',
                'tanggal_lahir' => 'nullable|date',
                'role' => 'sometimes|required|integer|in:0,1,2',
                'is_verified' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
            ]);

            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            // Handle file upload if provided
            if ($request->hasFile('foto_profil')) {
                // Delete old file if exists
                if ($user->foto_profil) {
                    Storage::disk('public')->delete($user->foto_profil);
                }
                
                $file = $request->file('foto_profil');
                $path = $file->store('profile_photos', 'public');
                $validated['foto_profil'] = $path;
            }

            $user->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Soft delete a user
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $user = User::where('id_user', $id)->where('is_deleted', false)->first();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'data' => null
                ], 404);
            }

            $user->update(['is_deleted' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully',
                'data' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
