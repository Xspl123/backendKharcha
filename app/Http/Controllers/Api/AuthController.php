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
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\User;



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
            return response()->json([
                'message' => 'OTP sent to your email'
            ]);
        }

        return response()->json([
            'message' => 'Something went wrong'
        ], 400);
    }



    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Already verified check
        if ($user->is_verified) {
            return response()->json([
                'message' => 'Account already verified'
            ], 400);
        }

        // OTP match check
        if ($user->otp != $request->otp) {
            return response()->json([
                'message' => 'Invalid OTP'
            ], 400);
        }

        // Expiry check
        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json([
                'message' => 'OTP expired'
            ], 400);
        }

        // ✅ Success
        $user->is_verified = 1;
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'OTP verified successfully',
            'token' => $token,
            'user' => $user
        ]);
    }


    public function login(LoginRequest $request)
    {
        // Validate email and password format
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $user = $this->userRepository->findByEmail($request->email);

        if (!$user) {
            return response()->json(['message' => 'User with this email does not exist'], 404);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Password does not match'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout()
    {
        $user = Auth::user();
        $this->userRepository->logout($user);

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function userProfile(Request $request)
    {
        return response()->json($request->user());
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
        $status = $this->userRepository->resetPassword($request->only('email', 'password', 'password_confirmation'));

        return $status === 'password_reset_success'
            ? response()->json(['message' => 'Password reset successfully.'])
            : response()->json(['message' => 'User not found.'], 400);
    }
    public function getAllUsers()
    {
        $users = $this->userRepository->getAllUsers();
        return response()->json($users);
    }

    public function forgotPassword(Request $request)
    {
        // Fetch the authenticated user's email
        $user = $request->user(); // Assuming the user is authenticated via Sanctum

        if (!$user || !$user->email) {
            return response()->json(['error' => 'User not found or email not available.'], 404);
        }

        // Call the UserRepository's sendPasswordResetLink method
        $result = $this->userRepository->sendPasswordResetLink($user->email);

        if ($result === 'reset_link_sent') {
            return response()->json(['message' => 'Password reset link sent successfully.']);
        } elseif ($result === 'user_not_found') {
            return response()->json(['error' => 'User not found.'], 404);
        } else {
            return response()->json(['error' => 'Failed to send reset link.'], 500);
        }
    }
    
}
