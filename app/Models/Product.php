<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'org_id',
        'product_category_id',
        'name',
        'sku',
        'hsn_code',
        'description',
        'unit',
        'purchase_price',
        'selling_price',
        'tax_rate',
        'opening_stock',
        'current_stock',
        'low_stock_alert',
        'avg_cost',
        'status',
        'notes',
    ];
    

    protected $attributes = [
        'unit'            => 'pcs',
        'purchase_price'  => 0,
        'selling_price'   => 0,
        'tax_rate'        => 0,
        'opening_stock'   => 0,
        'current_stock'   => 0,
        'low_stock_alert' => 0,
        'avg_cost'        => 0,
        'status'          => 'active',
    ];

    protected $casts = [
        'purchase_price'  => 'float',
        'selling_price'   => 'float',
        'tax_rate'        => 'float',
        'opening_stock'   => 'float',
        'current_stock'   => 'float',
        'low_stock_alert' => 'float',
        'avg_cost'        => 'float',
    ];

    // ── Relationships ─────────────────────────────────────

     protected $appends = ['is_low_stock', 'is_out_of_stock'];
     
    public function getIsLowStockAttribute(): bool
    {
        return $this->current_stock > 0 && $this->current_stock <= $this->low_stock_alert;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->current_stock <= 0;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function attributeValues()
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function leadProducts()
    {
        return $this->hasMany(LeadProduct::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
            ->withPivot('value');
    }

    // ── Stock Helpers ─────────────────────────────────────

    public function isLowStock(): bool
    {
        return $this->low_stock_alert > 0
            && $this->current_stock <= $this->low_stock_alert;
    }

    public function isOutOfStock(): bool
    {
        return $this->current_stock <= 0;
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->isOutOfStock())  return 'out_of_stock';
        if ($this->isLowStock())    return 'low_stock';
        return 'in_stock';
    }

    // ── Valuation ─────────────────────────────────────────

    public function getStockValueAttribute(): float
    {
        return round($this->current_stock * $this->avg_cost, 2);
    }

    // ── Average Cost Update (Weighted Average) ────────────

    public function updateAvgCost(float $newQty, float $newRate): void
    {
        $existingValue = $this->current_stock * $this->avg_cost;
        $newValue      = $newQty * $newRate;
        $totalQty      = $this->current_stock + $newQty;

        if ($totalQty > 0) {
            $this->avg_cost = round(($existingValue + $newValue) / $totalQty, 4);
            $this->save();
        }
    }

    // ── Stock Update ──────────────────────────────────────

    public function addStock(float $qty): void
    {
        $this->increment('current_stock', $qty);
    }

    public function removeStock(float $qty): void
    {
        $this->decrement('current_stock', $qty);
    }
}
