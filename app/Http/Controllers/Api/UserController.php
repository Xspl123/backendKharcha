<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserRepository $repo) {}

    // GET /api/users
    public function index(Request $request)
    {
        $this->authorize('users.view', $request);

        $users = $this->repo->getAll($request->only([
            'search', 'role_id', 'is_active', 'per_page'
        ]));

        return UserResource::collection($users);
    }

    // GET /api/users/{id}
    public function show(Request $request, int $id)
    {
        $this->authorize('users.view', $request);
        $user = $this->repo->findById($id);
        return response()->json(['data' => new UserResource($user)]);
    }

    // POST /api/users
    public function store(StoreUserRequest $request)
    {
        $this->authorize('users.create', $request);

        $user = $this->repo->createUser([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'data'    => new UserResource($user->load('role')),
        ], 201);
    }

    // PUT /api/users/{id}
    public function update(UpdateUserRequest $request, int $id)
    {
        $this->authorize('users.edit', $request);

        // Prevent non-super-admin from changing their own role
        $authUser = $request->user();
        if ($authUser->id === $id && !$authUser->isSuperAdmin()) {
            return response()->json([
                'message' => 'You cannot change your own role.',
            ], 403);
        }

        $user = $this->repo->updateUser($id, $request->validated());

        return response()->json([
            'message' => 'User updated successfully.',
            'data'    => new UserResource($user),
        ]);
    }

    // PATCH /api/users/{id}/toggle-active
    public function toggleActive(Request $request, int $id)
    {
        $this->authorize('users.edit', $request);

        // Cannot deactivate yourself
        if ($request->user()->id === $id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 403);
        }

        $user = $this->repo->toggleActive($id);

        return response()->json([
            'message' => $user->is_active ? 'User activated.' : 'User deactivated.',
            'data'    => new UserResource($user),
        ]);
    }

    // DELETE /api/users/{id}
    public function destroy(Request $request, int $id)
    {
        $this->authorize('users.delete', $request);

        if ($request->user()->id === $id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $this->repo->deleteUser($id);

        return response()->json(['message' => 'User deleted successfully.']);
    }

    // ── Helper: authorize with permission check ───────────
    private function authorize(string $permission, Request $request): void
    {
        if (!$request->user()->hasPermission($permission)) {
            abort(403, 'Access denied.');
        }
    }
}
