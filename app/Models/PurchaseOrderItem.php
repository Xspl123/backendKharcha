<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'org_id',
        'user_id',
        'item_name',
        'description',
        'hsn_code',
        'qty',
        'unit',
        'rate',
        'amount',
        'tax_rate',
        'tax_amount',
        'product_id',
        'category_id',
        'returned_qty',
        'is_returned',
        'original_item_id',
        'is_return_item',
    ];

    protected $casts = [
        'qty'        => 'float',
        'rate'       => 'float',
        'amount'     => 'float',
        'tax_rate'   => 'float',
        'tax_amount' => 'float',
        'returned_qty' => 'float',
        'is_returned' => 'boolean',
        'is_return_item' => 'boolean',
    ];

    protected $attributes = [
        'qty'      => 1,
        'unit'     => 'pcs',
        'tax_rate' => 0,
    ];

    // ── Relationships ──────────────────────────────────────

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function originalItem()
    {
        return $this->belongsTo(self::class, 'original_item_id');
    }

    public function returnItems()
    {
        return $this->hasMany(self::class, 'original_item_id');
    }

    public function getRemainingQtyAttribute(): float
    {
        return max(0, round((float) $this->qty - (float) ($this->returned_qty ?? 0), 2));
    }

    // ── Helpers ────────────────────────────────────────────

    public function calculateAmounts(): void
    {
        $amount    = round((float) $this->qty * (float) $this->rate, 2);
        $taxAmount = round($amount * (float) $this->tax_rate / 100, 2);

        $this->update([
            'amount'     => $amount,
            'tax_amount' => $taxAmount,
        ]);
    }
}
