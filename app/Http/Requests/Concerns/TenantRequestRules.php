<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

trait TenantRequestRules
{
    protected function tenantScopedExists(string $table, string $column = 'id')
    {
        $user = $this->user();

        $rule = Rule::exists($table, $column);

        if ($user && ! $user->hasOrg()) {
            $rule->where('user_id', $user->id);
        }

        return $rule;
    }

    protected function centralExists(string $modelClass, string $column = 'id', ?callable $callback = null)
    {
        $rule = Rule::exists($modelClass, $column);

        if ($callback) {
            $rule->where($callback);
        }

        return $rule;
    }

    protected function centralUnique(string $modelClass, string $column, mixed $ignore = null, ?string $idColumn = null)
    {
        $rule = Rule::unique($modelClass, $column);

        if (! is_null($ignore)) {
            $rule->ignore($ignore, $idColumn);
        }

        return $rule;
    }

    // TenantRequestRules.php
    protected function assignableRoleExists(?\App\Models\User $user = null)
    {
        $user = $user ?? $this->user();
        $rule = Rule::exists('roles', 'id');

        if (! $user?->isSuperAdmin()) {
            $rule->where(fn ($q) => $q
                ->where('name', '!=', 'super_admin')
                ->where('org_id', $user?->org_id)
            );
        }

        return $rule;
    }
}
