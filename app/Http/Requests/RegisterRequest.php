<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'email' => [
                'required',
                'string',
                'email',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('is_verified', true)),
            ],
            'phone' => [
                'required',
                'string',
                'max:15',
                Rule::unique('users', 'phone')->where(fn ($query) => $query->where('is_verified', true)),
            ],
            'otp' => 'nullable|digits:6',
            'otp_expires_at' => 'nullable|date',
            'is_verified' => 'nullable|boolean',
            'password' => 'required|string|min:6',
            'user_type' => 'nullable|in:personal,pending_org',
            'org_name' => 'nullable|string|max:255',
            'plan' => 'nullable|in:free,basic,premium',
        ];
    }
}
