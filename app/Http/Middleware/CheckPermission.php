<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Usage in routes:
     * Route::get('/products', ...)->middleware('permission:products.view');
     * Route::post('/products', ...)->middleware('permission:products.create');
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'message'    => 'Access denied. You do not have permission to perform this action.',
                'permission' => $permission,
                'your_role'  => $user->role?->label ?? 'No Role',
            ], 403);
        }

        return $next($request);
    }
}