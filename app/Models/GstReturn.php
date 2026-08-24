<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class GstReturn extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'return_type',
        'period',
        'status',
        'data_snapshot',
        'filed_at',
    ];

    protected $casts = [
        'data_snapshot' => 'array',
        'filed_at'      => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function isFiled(): bool
    {
        return $this->status === 'filed';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
