<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class HsnCode extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'hsn_code',
        'description',
        'gst_rate',
    ];

    protected $casts = [
        'gst_rate' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
