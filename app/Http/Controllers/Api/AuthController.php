<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Organisation;
use App\Models\OrganisationUser;

class AuthController extends Controller
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(RegisterRequest $request)
    {
        $response = $this->userRepository->register($request->validated());

        if ($response === 'otp_sent') {
            return response()->json(['message' => 'OTP sent to your email']);
        }

        if ($response === 'already_registered') {
            return response()->json(['message' => 'User already registered and verified.'], 422);
        }

        return response()->json(['message' => 'Something went wrong'], 400);
    }

    // public function verifyOtp(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'otp'   => 'required'
    //     ]);

    //     $user = User::where('email', $request->email)->first();

    //     if (!$user) {
    //         return response()->json(['message' => 'User not found'], 404);
    //     }

    //     if ($user->is_verified) {
    //         return response()->json(['message' => 'Account already verified'], 400);
    //     }

    //     if ($user->otp != $request->otp) {
    //         return response()->json(['message' => 'Invalid OTP'], 400);
    //     }

    //     if (Carbon::now()->greaterThan($user->otp_expires_at)) {
    //         return response()->json(['message' => 'OTP expired'], 400);
    //     }

    //     $user->is_verified    = 1;
    //     $user->otp            = null;
    //     $user->otp_expires_at = null;
    //     $user->save();

    //     $token = $user->createToken('auth_token')->plainTextToken;

    //     return response()->json([
    //         'message' => 'OTP verified successfully',
    //         'token'   => $token,
    //         'user'    => $this->formatUser($user->fresh('role.permissions')),
    //     ]);
    // }

    // ── Verify OTP — org auto-create here ─────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required',
        ]);
 
        $user = User::where('email', $request->email)->first();
 
        if (!$user)                                        return response()->json(['message' => 'User not found'], 404);
        if ($user->is_verified)                            return response()->json(['message' => 'Account already verified'], 400);
        if ($user->otp != $request->otp)                  return response()->json(['message' => 'Invalid OTP'], 400);
        if (Carbon::now()->greaterThan($user->otp_expires_at)) return response()->json(['message' => 'OTP expired'], 400);
 
        $user->is_verified    = 1;
        $user->otp            = null;
        $user->otp_expires_at = null;
        $user->save();
 
        // ── Auto-create Organisation if user_type = pending_org ──
        if ($user->user_type === 'pending_org') {
            $orgName = $user->org_name ?? ($user->name . "'s Organisation");
            $plan    = $user->plan    ?? 'basic';
 
            $org = Organisation::create([
                'owner_id'  => $user->id,
                'name'      => $orgName,
                'slug'      => Str::slug($orgName) . '-' . Str::random(6),
                'plan'      => $plan,
                'is_active' => true,
                'country'   => 'India',
            ]);
 
            // Organisation_users mein add karo
            OrganisationUser::create([
                'org_id'    => $org->id,
                'user_id'   => $user->id,
                'is_active' => true,
                'joined_at' => now(),
            ]);
 
            // User update karo
            $user->update([
                'org_id'    => $org->id,
                'user_type' => 'org_owner',
            ]);
        }
 
        $token = $user->createToken('auth_token')->plainTextToken;

 
        return response()->json([
            'message' => 'OTP verified successfully',
            'token'   => $token,
            'user'    => $this->formatUser($user->fresh('role.permissions', 'organisation')),
        ]);
    }

    public function login(LoginRequest $request)
    {
        $user = $this->userRepository->findByEmail($request->email);

        if (!$user) {
            return response()->json(['message' => 'User with this email does not exist'], 404);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Password does not match'], 401);
        }

        // ✅ Check if user is active
        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated. Contact admin.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->formatUser($user->load('role.permissions','organisation')),
        ]);
    }

    public function logout()
    {
        $user = Auth::user();

        $this->userRepository->logout($user);

        return response()->json(['message' => 'Logged out successfully']);
    }

    // ✅ UPDATED — role + permissions return karta hai
    public function userProfile(Request $request)
    {
        $user = $request->user()->load('role.permissions');
        return response()->json([
            'data' => $this->formatUser($user),
        ]);
    }

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully.'])
            : response()->json(['message' => __($status)], 400);
    }

    public function getAllUsers(Request $request)
    {
        if (!$request->user()->hasPermission('users.view')) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $users = $this->userRepository->getAll();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        return $this->sendPasswordResetLink($request);
    }

    // ── Private Helper ────────────────────────────────────
    private function formatUser(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'is_active'      => $user->is_active,
            'is_verified'    => $user->is_verified,
            'invoice_prefix' => $user->invoice_prefix,
            'user_type'      => $user->user_type,
            'org_id'         => $user->org_id,
            'organisation'   => $user->org_id ? [
                'id'   => $user->organisation?->id,
                'name' => $user->organisation?->name,
                'slug' => $user->organisation?->slug,
                'plan' => $user->organisation?->plan,
            ] : null,
            'role'        => $user->role ? [
                'id'    => $user->role->id,
                'name'  => $user->role->name,
                'label' => $user->role->label,
                'color' => $user->role->color,
            ] : null,
            'permissions' => $user->role?->permissions->pluck('name')->toArray() ?? [],
        ];
    }

}
