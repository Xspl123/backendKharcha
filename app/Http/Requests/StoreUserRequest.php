<?php
// ── File: app/Http/Requests/StoreUserRequest.php ───────────

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'email'          => ['required', 'email', $this->centralUnique(User::class, 'email')],
            'phone'          => 'nullable|numeric|digits_between:10,15',
            'password'       => 'required|string|min:8|confirmed',
            'role_id'        => ['required', $this->centralExists(Role::class)],
            'is_active'      => 'boolean',
            'invoice_prefix' => 'nullable|string|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'    => 'This email is already registered.',
            'role_id.exists'  => 'Selected role does not exist.',
            'password.min'    => 'Password must be at least 8 characters.',
        ];
    }
}
