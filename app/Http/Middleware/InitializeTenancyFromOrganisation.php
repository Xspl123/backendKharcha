<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyFromOrganisation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->org_id) {
            return $next($request);
        }

        $org = Organisation::with('tenant')->find($user->org_id);

        if (! $org) {
            return response()->json([
                'message' => 'Organisation not found.',
                'type' => 'org_not_found',
            ], 404);
        }

        app()->instance('currentOrg', $org);

        if (! $org->tenant) {
            return response()->json([
                'message' => 'Tenant database is not provisioned for this organisation.',
                'type' => 'tenant_not_provisioned',
            ], 409);
        }

        tenancy()->initialize($org->tenant);

        try {
            return $next($request);
        } finally {
            tenancy()->end();
        }
    }
}
