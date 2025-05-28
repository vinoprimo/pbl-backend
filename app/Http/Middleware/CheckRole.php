<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            Log::error('CheckRole middleware: User is not authenticated');
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
                'data' => null
            ], 401);
        }
        
        $user = $request->user();
        Log::info('CheckRole middleware: User', [
            'user_id' => $user->id_user,
            'username' => $user->username,
            'role' => $user->role,
            'role_name' => $user->role_name
        ]);
        
        // Handle case with no roles specified (allow any authenticated user)
        if (empty($roles)) {
            Log::info('CheckRole middleware: No roles specified, allowing authenticated user');
            return $next($request);
        }
        
        // Check if any of the specified roles matches the user's role
        foreach ($roles as $role) {
            // If the role is a string (e.g., 'admin'), we need to convert it to the corresponding ID
            if (!is_numeric($role)) {
                $role = strtolower($role); // Convert to lowercase for case-insensitive comparison
                
                // Find the role ID corresponding to the role name
                $roleId = array_search($role, array_map('strtolower', User::$roles));
                
                if ($roleId !== false && $user->role === $roleId) {
                    Log::info("CheckRole middleware: User has role '{$role}'");
                    return $next($request);
                }
            }
            // If the role is numeric, we can compare it directly to the user's role
            else if ((int)$user->role === (int)$role) {
                Log::info("CheckRole middleware: User has role ID {$role}");
                return $next($request);
            }
        }
        
        // If we reach here, the user doesn't have any of the required roles
        $allowedRoles = array_map(function($role) {
            return is_numeric($role) ? (User::$roles[(int)$role] ?? "Role {$role}") : $role;
        }, $roles);
        
        Log::warning('CheckRole middleware: Access denied', [
            'user_id' => $user->id_user,
            'user_role' => $user->role,
            'user_role_name' => User::$roles[$user->role] ?? 'unknown',
            'allowed_roles' => $allowedRoles
        ]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized. You do not have the required role to access this resource.',
            'required_roles' => $allowedRoles,
            'data' => null
        ], 403);
    }
}
