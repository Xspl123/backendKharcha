<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgAccount extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'org_id',
        'account_name',
        'account_type',
        'bank_name',
        'account_number',
        'ifsc_code',
        'upi_id',
        'opening_balance',
        'current_balance',
        'is_active',
        'is_default',
        'notes',
    ];

    protected $attributes = [
        'account_type'    => 'bank',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_default'      => false,
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
        'is_default'      => 'boolean',
    ];

    // Accessor & Mutator for compatibility with 'name'
    public function getNameAttribute(): ?string
    {
        return $this->attributes['account_name'] ?? null;
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['account_name'] = $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(OrgTransaction::class, 'account_id')->orderByDesc('transaction_date')->orderByDesc('id');
    }

    public function invoicePayments()
    {
        return $this->hasMany(InvoicePayment::class, 'account_id');
    }

    public function vendorPayments()
    {
        return $this->hasMany(VendorPayment::class, 'account_id');
    }
}