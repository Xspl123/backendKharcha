<?php

namespace App\Models;

class Campaign extends TenantModel
{
    protected $fillable = [
        'user_id', 'org_id', 'name', 'type', 'status', 'description', 'start_date', 'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function user()  { return $this->belongsTo(User::class); }
    public function leads() {
        return $this->belongsToMany(Lead::class, 'campaign_leads')
            ->withPivot('assigned_to')->withTimestamps();
    }
}
