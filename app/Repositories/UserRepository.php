<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Mail\ResetPasswordMail;
use App\Mail\OtpMail;
use App\Repositories\Traits\PaginatesResults;

class UserRepository implements UserRepositoryInterface
{
    use PaginatesResults;

    // ══════════════════════════════════════════════════════
    // AUTH METHODS
    // ══════════════════════════════════════════════════════

    public function register(array $data)
    {
        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser?->is_verified) {
            return 'already_registered';
        }

        $otp = rand(100000, 999999);
        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name'           => $data['name'],
                'phone'          => $data['phone'],
                'password'       => Hash::make($data['password']),
                'otp'            => $otp,
                'otp_expires_at' => now()->addMinutes(10),
                'is_verified'    => false,
                'user_type'      => $data['user_type'] ?? 'personal',
                'org_name'       => $data['org_name']  ?? null,
                'plan'           => $data['plan']       ?? null,
            ]
        );
        try {
            Mail::to($data['email'])->send(new OtpMail($otp));
            Log::info('OTP Mail Sent Successfully');
        } catch (\Exception $e) {
            Log::error('OTP Mail Failed', ['error' => $e->getMessage()]);
            return 'mail_failed';
        }
        return 'otp_sent';
    }

    public function findByEmail(string $email)
    {
        return User::where('email', $email)->first();
    }

    public function logout(User $user)
    {
        $user->tokens()->delete();
    }

    public function getUserById(int $id)
    {
        return User::findOrFail($id);
    }

    public function resetPassword(array $credentials)
    {
        $user = User::where('email', $credentials['email'])->first();
        if (!$user) return 'user_not_found';
        $user->password = Hash::make($credentials['password']);
        $user->save();
        return 'password_reset_success';
    }

    public function getAllUsers()
    {
        return $this->getAll();
    }

    public function sendPasswordResetLink(string $email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) return 'user_not_found';
        $resetLink = url('/password/reset/' . base64_encode($email));
        Mail::to($email)->send(new ResetPasswordMail($resetLink));
        return 'reset_link_sent';
    }

    // ══════════════════════════════════════════════════════
    // RBAC METHODS
    // ══════════════════════════════════════════════════════

   public function getAll(array $filters = [])
    {
        $authUser = Auth::user();

        $query = User::with(['role.permissions', 'createdBy'])
            ->withCount('createdUsers')
            ->visibleTo($authUser);

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')
            ->paginate($this->resolvePerPage($filters));
    }

    public function findById(int $id): User
    {
        $authUser = Auth::user();

        return User::with(['role.permissions', 'createdBy'])
            ->visibleTo($authUser)
            ->findOrFail($id);
    }

    public function createUser(array $data): User
    {
        $authUser = Auth::user();

        return User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone'          => $data['phone']          ?? null,
            'password'       => Hash::make($data['password']),
            'role_id'        => $data['role_id'],
            'is_active'      => $data['is_active']      ?? true,
            'invoice_prefix' => $data['invoice_prefix'] ?? 'INV',
            'created_by'     => $data['created_by'],
            'is_verified'    => true,
            'org_id'         => $authUser->isOrgOwner() ? $authUser->org_id : null,
            'user_type'      => $authUser->isOrgOwner() ? 'org_member' : 'personal',
        ]);
    }

    public function updateUser(int $id, array $data): User
    {
        $user   = $this->findById($id);
        $update = [];

        foreach (['name','email','phone','role_id','is_active','invoice_prefix'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $user->update($update);
        return $user->fresh(['role.permissions']);
    }

    public function toggleActive(int $id): User
    {
        $user = $this->findById($id);
        $user->update(['is_active' => !$user->is_active]);
        return $user->fresh(['role']);
    }

    public function deleteUser(int $id): bool
    {
        return $this->findById($id)->delete();
    }

    public function getByRole(string $roleName)
    {
        return User::with('role')
            ->whereHas('role', fn($q) => $q->where('name', $roleName))
            ->where('is_active', true)
            ->get();
    }
}
