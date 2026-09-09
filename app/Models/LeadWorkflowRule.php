<?php

namespace App\Models;

class LeadWorkflowRule extends TenantModel
{
    protected $fillable = [
        'org_id', 'user_id', 'name',
        'trigger_type', 'trigger_status',
        'action_type', 'action_message',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}