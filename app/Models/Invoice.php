<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class Invoice extends TenantModel
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
        'org_id',
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
        $subTotal    = 0;
        $totalTax    = 0;
        $cgstTotal   = 0;
        $sgstTotal   = 0;
        $igstTotal   = 0;

        $isInterState = ($this->supply_type === 'inter');

        foreach ($this->items as $item) {
            $gross        = round((float)$item->qty * (float)$item->rate, 2);
            $discount     = round((float)($item->discount_amount ?? 0), 2);
            $taxable      = max(0, $gross - $discount);
            $taxRate      = (float)($item->tax_rate ?? 0);
            $taxAmount    = round(($taxable * $taxRate) / 100, 2);

            $item->updateQuietly([
                'amount'         => $gross,
                'taxable_amount' => $taxable,
                'tax_amount'     => $taxAmount,
            ]);

            $subTotal += $taxable;

            if ($isInterState) {
                $igstTotal += $taxAmount;
            } else {
                // Intra-state me 50-50 split hota hai (CGST + SGST)
                $halfTax = round($taxAmount / 2, 2);
                $cgstTotal += $halfTax;
                $sgstTotal += ($taxAmount - $halfTax); // 1 paisa rounding safety
            }
        }

        // Invoice level discount
        $invoiceDiscount = round((float)($this->discount_amount ?? 0), 2);
        $taxableWithDiscount = max(0, $subTotal - $invoiceDiscount);

        // Shipping charges
        $shipping = round((float)($this->shipping_charges ?? 0), 2);

        $rawTotal = $taxableWithDiscount + $cgstTotal + $sgstTotal + $igstTotal + $shipping;

        // Professional Indian Round-off (Nearest Rupee: e.g. 1249.65 -> 1250.00, round_off = +0.35)
        $roundedTotal = round($rawTotal);
        $roundOff     = round($roundedTotal - $rawTotal, 2);

        $this->sub_total      = $subTotal;
        $this->cgst          = $cgstTotal;
        $this->sgst          = $sgstTotal;
        $this->igst          = $igstTotal;
        $this->round_off      = $roundOff;
        $this->total_amount   = $roundedTotal;
        $this->balance_amount = max(0, $roundedTotal - (float)$this->paid_amount);

        // Status auto-update
        if ($this->balance_amount <= 0 && $this->total_amount > 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } elseif ($this->status !== 'draft' && $this->status !== 'cancelled') {
            $this->status = 'unpaid';
        }

        $this->saveQuietly();
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
