<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model
{
   use HasFactory;

    protected $fillable = [
        'invoice_id',
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
        'returned_qty',
        'is_returned',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Auto calculate amount before saving
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->amount = $item->qty * $item->rate;
            $item->tax_amount = ($item->amount * $item->tax_rate) / 100;
        });
    }
}
