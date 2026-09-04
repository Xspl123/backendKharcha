<?php

namespace App\Models;

class LeadCustomField extends TenantModel
{
    protected $fillable = [
        'org_id', 'user_id', 'field_key', 'label',
        'field_type', 'options', 'is_required', 'sort_order',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
    ];
}