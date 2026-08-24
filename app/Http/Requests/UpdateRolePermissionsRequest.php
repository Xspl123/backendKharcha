<?php
// ── File: app/Http/Requests/UpdateRolePermissionsRequest.php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'permission_ids'   => 'required|array',
            'permission_ids.*' => [$this->centralExists(Permission::class)],
        ];
    }
}
