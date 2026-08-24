<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AddMemberRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', $this->centralUnique(User::class, 'email')],
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role_id'  => ['required', $this->centralExists(Role::class)],
        ];
    }
}
