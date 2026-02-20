<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Mail\ResetPasswordMail;
use App\Mail\OtpMail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserRepository implements UserRepositoryInterface
{
    public function register(array $data)
    {
        $otp = rand(100000, 999999);

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(10),
                'is_verified' => false
            ]
        );

        try {
            Mail::to($data['email'])->send(new OtpMail($otp));
            Log::info('OTP Mail Sent Successfully');
        } catch (\Exception $e) {
            Log::error('OTP Mail Failed', [
                'error' => $e->getMessage()
            ]);
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
        // Email ke basis par user fetch karein
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return 'user_not_found';
        }

        // Password update karein
        $user->password = Hash::make($credentials['password']);
        $user->save();

        return 'password_reset_success';
    }
    public function getAllUsers()
    {
        return User::all();
    }


    public function sendPasswordResetLink(string $email)
    {
        $user = User::where('email', $email)->first();
    
        if (!$user) {
            return 'user_not_found';
        }
    
        // Generate a password reset link
        $resetLink = url('/password/reset/' . base64_encode($email));
    
        // Send the email
        Mail::to($email)->send(new ResetPasswordMail($resetLink));
    
        return 'reset_link_sent';
    }


}
