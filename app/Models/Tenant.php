<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function organisations()
    {
        return $this->hasMany(Organisation::class);
    }
}