<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'contact_person',
        'phone',
        'email',
        'address',
        'gstin',
        'opening_balance',
        'is_active',
        'org_id'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function getTotalOutstandingAttribute()
    {
        return $this->invoices()
            ->whereIn('status', ['unpaid', 'partial'])
            ->sum('balance_amount');
    }
}
