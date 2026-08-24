<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'company_name',
        'logo',
        'address',
        'city',
        'state',
        'pincode',
        'phone',
        'email',
        'gstin',
        'pan',
        'website',
        'bank_name',
        'bank_account_no',
        'bank_ifsc',
        'bank_branch',
    ];

    protected $appends = ['logo_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return url('storage/' . $this->logo);
        }
        return null;
    }
}
