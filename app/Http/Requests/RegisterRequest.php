<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Set true to allow all users to use this request
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'phone' => 'required|string|max:15|unique:users,phone',
            'otp' => 'nullable|digits:6',
            'otp_expires_at' => 'nullable|date',
            'is_verified' => 'nullable|boolean',
            'password' => 'required|string|min:6',
        ];
    }
}
