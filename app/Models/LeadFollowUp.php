<?php

namespace App\Models;

class LeadFollowUp extends TenantModel
{
    protected $fillable = [
        'lead_id', 'user_id', 'due_date', 'note', 'is_done', 'done_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'done_at'  => 'datetime',
        'is_done'  => 'boolean',
    ];

    public function lead() { return $this->belongsTo(Lead::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function isOverdue(): bool { return !$this->is_done && $this->due_date->isPast(); }
}
