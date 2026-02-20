<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'po_number',
        'po_date',
        'expected_delivery_date',
        'received_date',
        'supply_type',
        'place_of_supply',
        'is_reverse_charge',
        'sub_total',
        'cgst',
        'sgst',
        'igst',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'notes',
        'terms_conditions',
    ];

    protected $attributes = [
        'supply_type'       => 'intra',
        'is_reverse_charge' => false,
        'sub_total'         => 0,
        'cgst'              => 0,
        'sgst'              => 0,
        'igst'              => 0,
        'total_amount'      => 0,
        'paid_amount'       => 0,
        'balance_amount'    => 0,
        'status'            => 'pending',
    ];

    protected $casts = [
        'po_date'                => 'date',
        'expected_delivery_date' => 'date',
        'received_date'          => 'date',
        'is_reverse_charge'      => 'boolean',
        'sub_total'              => 'float',
        'cgst'                   => 'float',
        'sgst'                   => 'float',
        'igst'                   => 'float',
        'total_amount'           => 'float',
        'paid_amount'            => 'float',
        'balance_amount'         => 'float',
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

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(VendorPayment::class);
    }

    // ── Status Helpers ─────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeReceived(): bool
    {
        return $this->status === 'approved';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    // ── Tax Helpers ────────────────────────────────────────

    public function getTotalTaxAttribute(): float
    {
        return round($this->cgst + $this->sgst + $this->igst, 2);
    }

    // ── Calculations ───────────────────────────────────────

    public function calculateTotals(): void
    {
        $subTotal = $this->items->sum(fn($item) => (float) $item->amount);
        $isInter  = $this->supply_type === 'inter';

        $totalTax = $this->items->sum(fn($item) =>
            ((float) $item->amount * (float) $item->tax_rate) / 100
        );

        $cgst = $isInter ? 0 : round($totalTax / 2, 2);
        $sgst = $isInter ? 0 : round($totalTax / 2, 2);
        $igst = $isInter ? round($totalTax, 2) : 0;

        $total = $subTotal + $cgst + $sgst + $igst;

        $this->update([
            'sub_total'      => round($subTotal, 2),
            'cgst'           => $cgst,
            'sgst'           => $sgst,
            'igst'           => $igst,
            'total_amount'   => round($total, 2),
            'balance_amount' => round($total - $this->paid_amount, 2),
        ]);
    }

    // ── Payment Status Update ──────────────────────────────

    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->payments()->sum('amount');
        $balance   = $this->total_amount - $totalPaid;

        $this->update([
            'paid_amount'    => round($totalPaid, 2),
            'balance_amount' => round($balance, 2),
        ]);
    }
}