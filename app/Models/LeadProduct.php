<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadProduct extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'product_id',
        'quantity',
        'expected_price',
        'note',
    ];

    protected $casts = [
        'quantity' => 'float',
        'expected_price' => 'float',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
