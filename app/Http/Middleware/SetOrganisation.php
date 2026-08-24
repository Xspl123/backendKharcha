<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Organisation;

class SetOrganisation
{
  
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) return $next($request);

        if ($user->isPersonal()) {
            app()->instance('currentOrg', null);
            return $next($request);
        }

        if ($user->org_id) {
            $org = Organisation::find($user->org_id);

            if (!$org || !$org->is_active) {
                return response()->json([
                    'message' => 'Organisation not found or inactive.',
                ], 403);
            }

            app()->instance('currentOrg', $org);
        }

        return $next($request);
    }
}