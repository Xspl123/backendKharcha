<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'vendor_name',
        'company_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'gstin',
        'pan',
        'bank_name',
        'bank_account_no',
        'bank_ifsc',
        'bank_branch',
        'status',
        'notes',
    ];

    protected $attributes = [
        'country' => 'India',
        'status'  => 'active',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(VendorPayment::class);
    }

    // ── Helpers ────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getTotalPurchasesAttribute(): float
    {
        return $this->purchaseOrders()
            ->whereIn('status', ['approved', 'received'])
            ->sum('total_amount');
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getTotalBalanceAttribute(): float
    {
        return $this->total_purchases - $this->total_paid;
    }
}