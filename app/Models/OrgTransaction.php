<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgTransaction extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'org_id',
        'account_id',
        'transaction_date',
        'type',
        'category',
        'amount',
        'balance_after',
        'payment_method',
        'reference_no',
        'description',
        'invoice_payment_id',
        'vendor_payment_id',
        'transfer_account_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
        'balance_after'    => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(OrgAccount::class, 'account_id');
    }

    public function transferAccount()
    {
        return $this->belongsTo(OrgAccount::class, 'transfer_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoicePayment()
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function vendorPayment()
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id');
    }
}