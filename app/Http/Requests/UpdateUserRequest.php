<?php
// ── File: app/Http/Requests/UpdateUserRequest.php ──────────

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name'           => 'sometimes|required|string|max:255',
            'email'          => ['sometimes', 'required', 'email', $this->centralUnique(User::class, 'email', $userId)],
            'phone'          => 'nullable|numeric|digits_between:10,15',
            'password'       => 'nullable|string|min:8|confirmed',
            'role_id'        => ['sometimes', 'required', $this->centralExists(Role::class)],
            'is_active'      => 'boolean',
            'invoice_prefix' => 'nullable|string|max:10',
        ];
    }
}
