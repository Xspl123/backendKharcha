<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Invoice extends Model
{
     use HasFactory;


    protected $attributes = [
        'status' => 'unpaid',
        'paid_amount' => 0,
        'balance_amount' => 0,
        'is_reverse_charge' => false,
        'supply_type'       => 'intra',
        'invoice_type'      => 'b2b',
    ];

    protected $fillable = [
        'company_id',
        'user_id',
        'client_id',
        'invoice_no',
        'invoice_date',
        'due_date',
        'sub_total',
        'cgst',
        'sgst',
        'igst',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'notes',
        'invoice_type',
        'supply_type',
        'place_of_supply',
        'is_reverse_charge',
        'original_invoice_id',
        'is_return',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sub_total' => 'decimal:2',
        'cgst' => 'decimal:2',
        'sgst' => 'decimal:2',
        'igst' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'is_reverse_charge' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    // ✅ Relationship for Return Invoices (Self-referential)
    public function originalInvoice()
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    // ✅ Relationship for Returned Invoices (Child returns)
    public function returnInvoices()
    {
        return $this->hasMany(Invoice::class, 'original_invoice_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function updatePaymentStatus()
    {
        $totalPaid = $this->payments()->sum('amount');
        
        $this->paid_amount = $totalPaid;
        $this->balance_amount = $this->total_amount - $totalPaid;

        if ($this->balance_amount <= 0) {
            $this->status = 'paid';
        } elseif ($totalPaid > 0) {
            $this->status = 'partial';
        } else {
            $this->status = 'unpaid';
        }

        $this->save();
    }

    public function calculateTotals()
    {
        $subTotal = $this->items()->sum('amount');
        
        $this->sub_total = $subTotal;
        $this->total_amount = $subTotal + $this->cgst + $this->sgst + $this->igst;
        $this->balance_amount = $this->total_amount - $this->paid_amount;
        
        $this->save();
    }

    public function getTotalTaxAttribute(): float
    {
        return (float)($this->cgst + $this->sgst + $this->igst);
    }

    public function isB2B(): bool
    {
        return $this->invoice_type === 'b2b';
    }
}
