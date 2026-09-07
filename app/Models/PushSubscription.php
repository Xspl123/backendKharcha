<?php

namespace App\Models;

class PushSubscription extends TenantModel
{
    protected $fillable = [
        'org_id', 'user_id', 'endpoint', 'endpoint_hash',
        'public_key', 'auth_token', 'content_encoding',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
