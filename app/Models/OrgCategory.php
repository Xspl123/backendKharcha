<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgCategory extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'org_categories';

    protected $fillable = [
        'user_id',
        'org_id',
        'name',
        'slug',
        'type',
        'color',
        'icon',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];
}