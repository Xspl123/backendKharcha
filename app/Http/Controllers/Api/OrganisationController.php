<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrganisationRequest;
use App\Http\Requests\AddMemberRequest;
use App\Http\Resources\OrganisationResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Repositories\OrganisationRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganisationController extends Controller
{
    public function __construct(private OrganisationRepository $repo) {}

    // POST /api/organisation/create
    // Personal user -> org_owner banta hai
    public function create(CreateOrganisationRequest $request)
    {
        $user = $request->user();

        if ($user->hasOrg()) {
            return response()->json([
                'message' => 'You already belong to an organisation.',
            ], 422);
        }

        $org = $this->repo->create($request->validated());

        return response()->json([
            'message' => 'Organisation created successfully!',
            'data'    => new OrganisationResource($org),
        ], 201);
    }

    // GET /api/organisation
    public function show(Request $request)
    {
        $org = $this->repo->getCurrent();

        if (!$org) {
            return response()->json(['message' => 'No organisation found.'], 404);
        }

        return response()->json(['data' => new OrganisationResource($org)]);
    }

    // PUT /api/organisation
    public function update(Request $request)
    {
        $this->checkOwner($request);
        $org = $this->repo->update($request->all());
        return response()->json([
            'message' => 'Organisation updated.',
            'data'    => new OrganisationResource($org),
        ]);
    }

    // GET /api/organisation/members
    public function members(Request $request)
    {
        $members = $this->repo->getMembers();
        return response()->json(['data' => $members]);
    }

    // POST /api/organisation/members
    public function addMember(AddMemberRequest $request)
    {
        $this->checkOwner($request);
        $member = $this->repo->addMember($request->validated());
        return response()->json([
            'message' => 'Member added successfully.',
            'data'    => new UserResource($member),
        ], 201);
    }

    // PUT /api/organisation/members/{userId}/role
    public function updateMemberRole(Request $request, int $userId)
    {
        $this->checkOwner($request);
        $request->validate(['role_id' => ['required', Rule::exists(Role::class, 'id')]]);
        $ou = $this->repo->updateMemberRole($userId, $request->role_id);
        return response()->json(['message' => 'Role updated.', 'data' => $ou]);
    }

    // PATCH /api/organisation/members/{userId}/toggle
    public function toggleMember(Request $request, int $userId)
    {
        $this->checkOwner($request);

        if ($request->user()->id === $userId) {
            return response()->json(['message' => 'You cannot deactivate yourself.'], 422);
        }

        $ou = $this->repo->toggleMember($userId);
        return response()->json([
            'message' => $ou->is_active ? 'Member activated.' : 'Member deactivated.',
            'data'    => $ou,
        ]);
    }

    // DELETE /api/organisation/members/{userId}
    public function removeMember(Request $request, int $userId)
    {
        $this->checkOwner($request);

        if ($request->user()->id === $userId) {
            return response()->json(['message' => 'You cannot remove yourself.'], 422);
        }

        $this->repo->removeMember($userId);
        return response()->json(['message' => 'Member removed.']);
    }

    // ── Helper ────────────────────────────────────────────
    private function checkOwner(Request $request): void
    {
        if (!$request->user()->isOrgOwner() && !$request->user()->isSuperAdmin()) {
            abort(403, 'Only organisation owner can perform this action.');
        }
    }
}
