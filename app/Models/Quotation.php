<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'org_id',
        'lead_id',
        'client_id',
        'quotation_no',
        'quotation_date',
        'expiry_date',
        'status',
        'sub_total',
        'cgst',
        'sgst',
        'igst',
        'total_amount',
        'notes',
        'terms_conditions',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'sub_total' => 'float',
        'cgst' => 'float',
        'sgst' => 'float',
        'igst' => 'float',
        'total_amount' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function calculateTotals(): void
    {
        $subTotal = $this->items->sum(fn ($item) => (float) $item->amount);
        $totalTax = $this->items->sum(fn ($item) => (float) $item->tax_amount);

        $this->update([
            'sub_total' => round($subTotal, 2),
            'cgst' => round($totalTax / 2, 2),
            'sgst' => round($totalTax / 2, 2),
            'igst' => 0,
            'total_amount' => round($subTotal + $totalTax, 2),
        ]);
    }
}
