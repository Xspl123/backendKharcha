<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuotationItem extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'item_name',
        'description',
        'hsn_code',
        'qty',
        'unit',
        'rate',
        'amount',
        'tax_rate',
        'tax_amount',
    ];

    protected $casts = [
        'qty' => 'float',
        'rate' => 'float',
        'amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
