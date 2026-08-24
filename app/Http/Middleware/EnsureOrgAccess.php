<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOrgAccess
{
    
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isPersonal()) {
            return response()->json([
                'message' => 'This feature is only available for organisation accounts.',
                'type'    => 'org_required',
            ], 403);
        }

        if (!$user->org_id) {
            return response()->json([
                'message' => 'You are not associated with any organisation.',
                'type'    => 'no_org',
            ], 403);
        }

        return $next($request);
    }
}