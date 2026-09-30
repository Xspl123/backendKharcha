<?php

namespace App\Repositories;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;

class RoleRepository
{
    // ── Get all roles with permissions ────────────────────
   public function getAll()
    {
        $user = Auth::user();
        $query = Role::with('permissions')->withCount('users')->orderBy('id');

        if ($user->isSuperAdmin()) {
            return $query->with('organisation')
                ->where(function ($q) {
                    $q->whereNotNull('org_id')
                    ->orWhere('name', 'super_admin');
                })
                ->get();
        }

        return $query->where('org_id', $user->org_id)->get();
    }

    // ── Get all permissions grouped by module ─────────────
    public function getAllPermissions()
    {
        return Permission::orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');
    }

    // ── Find role by ID ───────────────────────────────────
    public function findById(int $id): Role
    {
        $user = Auth::user();
        $query = Role::with('permissions');

        if (! $user->isSuperAdmin()) {
            $query->where('org_id', $user->org_id);
        }

        return $query->findOrFail($id);
    }

    // ── Update role permissions (sync) ────────────────────
    public function updatePermissions(int $roleId, array $permissionIds): Role
    {
        $user = Auth::user();
        $role = Role::findOrFail($roleId);

        // Super admin role kabhi API se modify nahi hoga
        if ($role->name === 'super_admin') {
            abort(403, 'Super Admin permissions cannot be modified.');
        }

        // System preset (org_id = null) directly modify nahi ho sakta
        if (is_null($role->org_id) && ! $user->isSuperAdmin()) {
            abort(403, 'Access denied.');
        }

        // Non-super-admin sirf apne org ke role modify kar sakta hai
        if (! $user->isSuperAdmin() && $role->org_id !== $user->org_id) {
            abort(403, 'Access denied.');
        }

        $role->permissions()->sync($permissionIds);
        return $role->fresh('permissions');
    }

    // ── Get role by name ──────────────────────────────────
    public function findByName(string $name): ?Role
    {
        return Role::with('permissions')->where('name', $name)->first();
    }
}