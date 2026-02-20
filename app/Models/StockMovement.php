<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'type',
        'qty',
        'rate',
        'value',
        'stock_before',
        'stock_after',
        'reference_type',
        'reference_id',
        'reference_no',
        'notes',
        'movement_date',
    ];

    protected $casts = [
        'qty'           => 'float',
        'rate'          => 'float',
        'value'         => 'float',
        'stock_before'  => 'float',
        'stock_after'   => 'float',
        'movement_date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ── Type Helpers ──────────────────────────────────────

    public function isInward(): bool
    {
        return in_array($this->type, [
            'opening', 'purchase_in', 'manual_in', 'return_in'
        ]);
    }

    public function isOutward(): bool
    {
        return in_array($this->type, [
            'sale_out', 'manual_out', 'return_out'
        ]);
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'opening'     => 'Opening Stock',
            'purchase_in' => 'Purchase In',
            'sale_out'    => 'Sale Out',
            'manual_in'   => 'Manual In',
            'manual_out'  => 'Manual Out',
            'adjustment'  => 'Adjustment',
            'return_in'   => 'Purchase Return',
            'return_out'  => 'Sale Return',
            default       => ucfirst($this->type),
        };
    }

    public function getDirectionAttribute(): string
    {
        return $this->isInward() ? 'in' : ($this->isOutward() ? 'out' : 'adjustment');
    }
}