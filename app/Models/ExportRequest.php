<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExportRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'type',
        'status',
        'filters',
        'file_disk',
        'file_path',
        'error_message',
        'expires_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'expires_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
