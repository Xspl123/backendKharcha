<?php

namespace App\Repositories;

use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Services\OrganisationTenantProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class OrganisationRepository
{
    public function __construct(private OrganisationTenantProvisioner $tenantProvisioner) {}

    // ── Create Organisation ───────────────────────────────
    public function create(array $data): Organisation
    {
        $user = Auth::user();
        $previousOrgId = $user->org_id;
        $previousUserType = $user->user_type;

        $org = Organisation::create([
            'owner_id'   => $user->id,
            'name'       => $data['name'],
            'slug'       => Str::slug($data['name']) . '-' . Str::random(6),
            'email'      => $data['email']      ?? null,
            'phone'      => $data['phone']      ?? null,
            'address'    => $data['address']    ?? null,
            'city'       => $data['city']       ?? null,
            'country'    => $data['country']    ?? 'India',
            'gst_number' => $data['gst_number'] ?? null,
            'pan_number' => $data['pan_number'] ?? null,
            'plan'       => 'free',
            'is_active'  => true,
        ]);

        // Owner ko org mein add karo
        OrganisationUser::create([
            'org_id'    => $org->id,
            'user_id'   => $user->id,
            'role_id'   => null, // owner ka role = super_admin
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // User ko org_owner mark karo
        $user->update([
            'org_id'    => $org->id,
            'user_type' => 'org_owner',
        ]);

        try {
            $this->tenantProvisioner->provision($org);
        } catch (Throwable $e) {
            OrganisationUser::where('org_id', $org->id)
                ->where('user_id', $user->id)
                ->delete();

            $user->update([
                'org_id' => $previousOrgId,
                'user_type' => $previousUserType,
            ]);

            $org->delete();

            throw $e;
        }

        return $org->fresh(['owner', 'members', 'tenant']);
    }

    // ── Get current org ───────────────────────────────────
    public function getCurrent(): ?Organisation
    {
        $user = Auth::user();
        if (!$user->org_id) return null;
        return Organisation::with(['owner', 'organisationUsers.user', 'organisationUsers.role'])
            ->find($user->org_id);
    }

    // ── Update org ────────────────────────────────────────
    public function update(array $data): Organisation
    {
        $user = Auth::user();
        $org  = Organisation::findOrFail($user->org_id);

        $org->update(array_filter([
            'name'       => $data['name']       ?? null,
            'email'      => $data['email']       ?? null,
            'phone'      => $data['phone']       ?? null,
            'address'    => $data['address']     ?? null,
            'city'       => $data['city']        ?? null,
            'country'    => $data['country']     ?? null,
            'gst_number' => $data['gst_number']  ?? null,
            'pan_number' => $data['pan_number']  ?? null,
        ], fn($v) => !is_null($v)));

        return $org->fresh();
    }

    // ── Invite / Add Member ───────────────────────────────
    public function addMember(array $data): User
    {
        $orgId = Auth::user()->org_id;

        $member = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'phone'       => $data['phone']  ?? null,
            'password'    => Hash::make($data['password']),
            'role_id'     => $data['role_id'],
            'org_id'      => $orgId,
            'user_type'   => 'org_member',
            'is_active'   => true,
            'is_verified' => true,
            'created_by'  => Auth::id(),
        ]);

        // Org member table mein add karo
        OrganisationUser::create([
            'org_id'    => $orgId,
            'user_id'   => $member->id,
            'role_id'   => $data['role_id'],
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return $member->fresh(['role']);
    }

    // ── Get Members ───────────────────────────────────────
    public function getMembers(): \Illuminate\Support\Collection
    {
        $orgId = Auth::user()->org_id;
        return OrganisationUser::with(['user', 'role'])
            ->where('org_id', $orgId)
            ->get();
    }

    // ── Update Member Role ────────────────────────────────
    public function updateMemberRole(int $userId, int $roleId): OrganisationUser
    {
        $orgId = Auth::user()->org_id;
        $ou    = OrganisationUser::where('org_id', $orgId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $ou->update(['role_id' => $roleId]);

        // User table mein bhi update karo
        User::find($userId)?->update(['role_id' => $roleId]);

        return $ou->fresh(['user', 'role']);
    }

    // ── Toggle Member Active ──────────────────────────────
    public function toggleMember(int $userId): OrganisationUser
    {
        $orgId = Auth::user()->org_id;
        $ou    = OrganisationUser::where('org_id', $orgId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $isActive = !$ou->is_active;

        $ou->update(['is_active' => $isActive]);
        User::find($userId)?->update(['is_active' => $isActive]);

        return $ou->fresh();
    }

    // ── Remove Member ─────────────────────────────────────
    public function removeMember(int $userId): bool
    {
        $orgId = Auth::user()->org_id;

        OrganisationUser::where('org_id', $orgId)
            ->where('user_id', $userId)
            ->delete();

        // User ka org_id clear karo
        User::find($userId)?->update([
            'org_id'    => null,
            'user_type' => 'personal',
        ]);

        return true;
    }
}
