<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class VendorPayment extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'vendor_id',
        'purchase_order_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_no',
        'notes',
    ];

    protected $casts = [
        'amount'       => 'float',
        'payment_date' => 'date',
    ];

    protected $attributes = [
        'payment_method' => 'bank_transfer',
    ];

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // ── Helpers ────────────────────────────────────────────

    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash'          => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'cheque'        => 'Cheque',
            'upi'           => 'UPI',
            'other'         => 'Other',
            default         => ucfirst($this->payment_method),
        };
    }
}
