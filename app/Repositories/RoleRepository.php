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
        $query = Role::with('permissions')->withCount('users')->orderBy('id');

        if (! Auth::user()->isSuperAdmin()) {
            $query->where('name', '!=', 'super_admin');
        }

        return $query->get();
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
        $query = Role::with('permissions');

        if (! Auth::user()->isSuperAdmin()) {
            $query->where('name', '!=', 'super_admin');
        }

        return $query->findOrFail($id);
    }

    // ── Update role permissions (sync) ────────────────────
    public function updatePermissions(int $roleId, array $permissionIds): Role
    {
        $role = Role::findOrFail($roleId);

        // Prevent modifying super_admin permissions from API
        if ($role->name === 'super_admin') {
            abort(403, 'Super Admin permissions cannot be modified.');
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