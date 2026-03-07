<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
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
    ];

    protected $casts = [
        'qty'        => 'float',
        'rate'       => 'float',
        'amount'     => 'float',
        'tax_rate'   => 'float',
        'tax_amount' => 'float',
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