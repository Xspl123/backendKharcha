<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Public counterpart to InitializeTenancyFromOrganisation. There is no
// logged-in user on the public lead-capture form, so we can't read
// $user->org_id — instead the org is identified by its public `slug` in
// the URL (e.g. /public/leads/{orgSlug}). Only non-sensitive existence /
// active-state checks happen here; org.access / permission middleware are
// never applied to this group since there is no user to check them against.
class InitializeTenancyFromSlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('orgSlug');
        $org  = Organisation::with('tenant')->where('slug', $slug)->first();

        if (! $org || ! $org->is_active) {
            return response()->json([
                'message' => 'This form is not available.',
                'type'    => 'org_not_found',
            ], 404);
        }

        if (! $org->tenant) {
            return response()->json([
                'message' => 'This form is not available right now.',
                'type'    => 'tenant_not_provisioned',
            ], 409);
        }

        app()->instance('currentOrg', $org);
        tenancy()->initialize($org->tenant);

        try {
            return $next($request);
        } finally {
            tenancy()->end();
        }
    }
}