<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    // ── All Organisations ─────────────────────────────────
    public function organisations(Request $request)
    {
        $orgs = Organisation::with(['owner:id,name,email'])
            ->withCount('members')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data'  => $orgs,
            'stats' => [
                'total'   => $orgs->count(),
                'active'  => $orgs->where('is_active', true)->count(),
                'paid'    => $orgs->whereIn('plan', ['basic','premium'])->count(),
            ],
        ]);
    }

    // ── Organisation Members ──────────────────────────────
    public function orgUsers(int $orgId)
    {
        $users = User::with('role')
            ->where('org_id', $orgId)
            ->get();

        return response()->json(['data' => $users]);
    }

    // ── Toggle Org Active ─────────────────────────────────
    public function toggleOrg(int $orgId)
    {
        $org = Organisation::findOrFail($orgId);
        $org->update(['is_active' => !$org->is_active]);

        return response()->json([
            'message'   => $org->is_active ? 'Organisation activated' : 'Organisation deactivated',
            'is_active' => $org->is_active,
        ]);
    }

    // ── Change Plan ───────────────────────────────────────
    public function changePlan(Request $request, int $orgId)
    {
        $request->validate(['plan' => 'required|in:free,basic,premium']);
        $org = Organisation::findOrFail($orgId);
        $org->update(['plan' => $request->plan]);

        return response()->json([
            'message' => 'Plan updated to ' . $request->plan,
            'plan'    => $org->plan,
        ]);
    }

    public function loginHistory(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = LoginHistory::with('user:id,name,email')
            ->orderByDesc('logged_in_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $paginator = $query->paginate($request->integer('per_page', 25));
        $histories = $paginator->getCollection()->map(fn (LoginHistory $history) => [
            'id' => $history->id,
            'user_id' => $history->user_id,
            'user' => $history->user ? [
                'name' => $history->user->name,
                'email' => $history->user->email,
            ] : null,
            'device_name' => $history->device_name,
            'ip_address' => $history->ip_address,
            'city' => $history->city,
            'region' => $history->region,
            'country' => $history->country,
            'logged_in_at' => $history->logged_in_at,
        ]);

        return response()->json([
            'data' => $histories,
            'pagination' => [
                'total' => $paginator->total(),
            ],
        ]);
    }

    // ── All Users ─────────────────────────────────────────
    public function users(Request $request)
    {
        $users = User::with([
            'role',
            'organisation:id,name',
            'lastLoginHistory',
        ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $users]);
    }
}