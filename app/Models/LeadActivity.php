<?php

namespace App\Models;

class LeadActivity extends TenantModel
{
    protected $fillable = [
        'lead_id', 'user_id', 'type', 'note', 'call_duration', 'outcome',
    ];

    public function lead() { return $this->belongsTo(Lead::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function getCallDurationFormattedAttribute(): ?string
    {
        if (!$this->call_duration) return null;
        $m = intdiv($this->call_duration, 60);
        $s = $this->call_duration % 60;
        return "{$m}m {$s}s";
    }
}
