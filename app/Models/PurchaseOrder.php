<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrder extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'org_id',
        'vendor_id',
        'po_number',
        'product_id',
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
        'original_po_id',
        'is_return',
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

    protected $appends = ['can_approve','can_receive','can_cancel','can_return'];

    public function getCanReturnAttribute(): bool
    {
        if ($this->status !== 'received') return false;
        return $this->items()
            ->where('is_return_item', false)
            ->whereRaw('returned_qty < qty')
            ->exists();
    }

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

    public function originalPO()
    {
        return $this->belongsTo(self::class, 'original_po_id');
    }

    public function returnOrders()
    {
        return $this->hasMany(self::class, 'original_po_id');
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

    public function recalculateAmountsFromRemainingItems(): void
    {
        $items = $this->items()
            ->where('is_return_item', false)
            ->get(['qty', 'rate', 'tax_rate', 'returned_qty']);

        $subTotal = $items->sum(function ($item) {
            $remainingQty = max(0, round((float) $item->qty - (float) ($item->returned_qty ?? 0), 2));
            return round($remainingQty * (float) $item->rate, 2);
        });

        $totalTax = $items->sum(function ($item) {
            $remainingQty = max(0, round((float) $item->qty - (float) ($item->returned_qty ?? 0), 2));
            $remainingAmount = round($remainingQty * (float) $item->rate, 2);
            return round($remainingAmount * (float) $item->tax_rate / 100, 2);
        });

        $isInter = $this->supply_type === 'inter';
        $cgst = $isInter ? 0 : round($totalTax / 2, 2);
        $sgst = $isInter ? 0 : round($totalTax / 2, 2);
        $igst = $isInter ? round($totalTax, 2) : 0;
        $total = round($subTotal + $cgst + $sgst + $igst, 2);

        $actualPaid = round((float) $this->payments()->sum('amount'), 2);
        $appliedPaid = round(min($actualPaid, $total), 2);
        $balance = round(max($total - $appliedPaid, 0), 2);

        $this->update([
            'sub_total' => round($subTotal, 2),
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'total_amount' => $total,
            'paid_amount' => $appliedPaid,
            'balance_amount' => $balance,
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
