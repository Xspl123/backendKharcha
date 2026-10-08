<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\LoginHistory;
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

    // ── Verify OTP — org auto-create here ─────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user)                                        return response()->json(['message' => 'Invalid email or OTP'], 400);
        if ($user->is_verified)                            return response()->json(['message' => 'Account already verified'], 400);
        if ($user->otp != $request->otp)                  return response()->json(['message' => 'Invalid or expired OTP'], 400);
        if (Carbon::now()->greaterThan($user->otp_expires_at)) return response()->json(['message' => 'Invalid or expired OTP'], 400);

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

            // ── Preset roles clone karo is org ke liye ──
            $orgAdminRoleId = null;
            foreach (\App\Models\Role::whereNull('org_id')->where('name', '!=', 'super_admin')->get() as $preset) {
                $cloned = \App\Models\Role::create([
                    'org_id'      => $org->id,
                    'name'        => $preset->name,
                    'label'       => $preset->label,
                    'color'       => $preset->color,
                    'description' => $preset->description,
                ]);
                $cloned->permissions()->sync($preset->permissions->pluck('id'));

                if ($preset->name === 'org_admin') {
                    $orgAdminRoleId = $cloned->id;
                }
            }

            // Organisation_users mein add karo
            OrganisationUser::create([
                'org_id'    => $org->id,
                'user_id'   => $user->id,
                'role_id'   => $orgAdminRoleId,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            // User update karo
            $user->update([
                'org_id'    => $org->id,
                'user_type' => 'org_owner',
                'role_id'   => $orgAdminRoleId,
            ]);
        }

        $deviceId = $request->input('device_id');
        $tokenName = $deviceId ? 'auth_device:' . $deviceId : 'auth_token';
        $token = $user->createToken($tokenName)->plainTextToken;
        $this->recordLoginHistory($request, $user, $deviceId);

        return response()->json([
            'message' => 'OTP verified successfully',
            'token'   => $token,
            'user'    => $this->formatUser($user->fresh('role.permissions', 'organisation')),
        ]);
    }

    public function login(LoginRequest $request)
    {
        $user = $this->userRepository->findByEmail($request->email);

        if (!$user || !Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        if (!$user->is_verified) {
            return response()->json(['message' => 'Please verify your account via OTP before logging in.'], 403);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated. Contact admin.'], 403);
        }

        $deviceId = $request->input('device_id');
        $replaceExistingSession = $request->boolean('replace_existing_session');

        $token = DB::transaction(function () use ($user, $deviceId, $replaceExistingSession) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $existingTokens = $lockedUser->tokens()->get();
            $deviceTokenName = $deviceId ? 'auth_device:' . $deviceId : null;
            $hasOtherDevice = $existingTokens->contains(
                fn ($existingToken) => $existingToken->name !== $deviceTokenName
            );

            if ($hasOtherDevice && !$replaceExistingSession) {
                return null;
            }

            if ($existingTokens->isNotEmpty()) {
                $lockedUser->tokens()->delete();
            }

            return $lockedUser->createToken($deviceTokenName ?: 'auth_token')->plainTextToken;
        });

        if (!$token) {
            return response()->json([
                'code' => 'active_session_exists',
                'message' => 'This account is active on another device.',
            ], 409);
        }

        $this->recordLoginHistory($request, $user, $deviceId);

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->formatUser($user->load('role.permissions','organisation')),
        ]);
    }

    public function loginHistory(Request $request)
    {
        $request->validate(['per_page' => 'nullable|integer|min:1|max:100']);
        $this->ensureCurrentSessionLoginHistory($request);

        $histories = LoginHistory::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('logged_in_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => $histories->items(),
            'pagination' => [
                'total' => $histories->total(),
            ],
        ]);
    }

    private function recordLoginHistory(Request $request, User $user, ?string $deviceId, ?Carbon $loggedInAt = null): void
    {
        $userAgent = substr((string) $request->userAgent(), 0, 2000);
        $browser = $this->browserFromUserAgent($userAgent);
        $platform = $this->platformFromUserAgent($userAgent);
        $ipAddress = $request->ip();
        $geo = [];

        if ($ipAddress && filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            try {
                $geo = Cache::remember('login-ip-location:' . $ipAddress, now()->addDays(30), function () use ($ipAddress) {
                    $response = Http::timeout(1)->get('https://ipwho.is/' . rawurlencode($ipAddress));
                    return $response->successful() && $response->json('success')
                        ? $response->json()
                        : [];
                });
            } catch (\Throwable $exception) {
                $geo = [];
            }
        }

        try {
            LoginHistory::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_name' => trim(implode(' on ', array_filter([$browser, $platform]))) ?: 'Unknown device',
                'browser' => $browser ?: null,
                'platform' => $platform ?: null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ?: null,
                'city' => $geo['city'] ?? null,
                'region' => $geo['region'] ?? null,
                'country' => $geo['country'] ?? null,
                'country_code' => $geo['country_code'] ?? null,
                'timezone' => data_get($geo, 'timezone.id'),
                'logged_in_at' => $loggedInAt ?? now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function ensureCurrentSessionLoginHistory(Request $request): void
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$token instanceof \Laravel\Sanctum\PersonalAccessToken || !$token->created_at) {
            return;
        }

        $tokenName = (string) $token->name;
        $deviceId = str_starts_with($tokenName, 'auth_device:')
            ? substr($tokenName, strlen('auth_device:'))
            : null;
        $loginTime = $token->created_at;

        $historyExists = LoginHistory::query()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->where('logged_in_at', '>=', $loginTime)
            ->where('logged_in_at', '<=', $loginTime->copy()->addMinutes(2))
            ->exists();

        if (!$historyExists) {
            $this->recordLoginHistory($request, $user, $deviceId, $loginTime);
        }
    }

    private function browserFromUserAgent(string $userAgent): ?string
    {
        return match (true) {
            preg_match('/Edg\//i', $userAgent) === 1 => 'Edge',
            preg_match('/OPR\//i', $userAgent) === 1 => 'Opera',
            preg_match('/SamsungBrowser/i', $userAgent) === 1 => 'Samsung Internet',
            preg_match('/Firefox\//i', $userAgent) === 1 => 'Firefox',
            preg_match('/Chrome\//i', $userAgent) === 1 => 'Chrome',
            preg_match('/Safari\//i', $userAgent) === 1 => 'Safari',
            default => null,
        };
    }

    private function platformFromUserAgent(string $userAgent): ?string
    {
        return match (true) {
            preg_match('/Windows/i', $userAgent) === 1 => 'Windows',
            preg_match('/Android/i', $userAgent) === 1 => 'Android',
            preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1 => 'iOS',
            preg_match('/Mac OS X/i', $userAgent) === 1 => 'macOS',
            preg_match('/Linux/i', $userAgent) === 1 => 'Linux',
            default => null,
        };
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
        $this->ensureCurrentSessionLoginHistory($request);
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
