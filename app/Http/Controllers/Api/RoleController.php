<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Repositories\RoleRepository;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private RoleRepository $repo) {}

    // GET /api/roles
    public function index(Request $request)
    {
        $roles = $this->repo->getAll();
        return response()->json([
            'data' => RoleResource::collection($roles),
        ]);
    }

    // GET /api/roles/{id}
    public function show(int $id)
    {
        $role = $this->repo->findById($id);
        return response()->json(['data' => new RoleResource($role)]);
    }

    // GET /api/permissions
    // Returns all permissions grouped by module
    public function permissions()
    {
        $grouped = $this->repo->getAllPermissions();

        $data = $grouped->map(fn($perms, $module) => [
            'module'      => $module,
            'permissions' => $perms->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'label' => $p->label,
            ])->values(),
        ])->values();

        return response()->json(['data' => $data]);
    }

    // PUT /api/roles/{id}/permissions
    public function updatePermissions(UpdateRolePermissionsRequest $request, int $id)
    {
        if (!$request->user()->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $role = $this->repo->updatePermissions($id, $request->permission_ids);

        return response()->json([
            'message' => 'Role permissions updated successfully.',
            'data'    => new RoleResource($role),
        ]);
    }
}