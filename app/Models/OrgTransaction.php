<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgTransaction extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'voucher_no',
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

    // Auto-generate voucher_no on create if not provided
    protected static function booted()
    {
        static::creating(function ($transaction) {
            if (empty($transaction->voucher_no)) {
                $prefix = match ($transaction->category) {
                    'transfer' => 'CV',
                    default    => ($transaction->type === 'debit' ? 'PV' : 'RV'),
                };
                $year = $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('Y') : date('Y');
                
                $latest = static::where('voucher_no', 'like', "{$prefix}-{$year}-%")
                    ->latest('id')
                    ->first();

                $nextSeq = 1;
                if ($latest && preg_match('/-(\d+)$/', $latest->voucher_no, $m)) {
                    $nextSeq = ((int) $m[1]) + 1;
                } else {
                    $nextSeq = (static::max('id') ?? 0) + 1;
                }

                $transaction->voucher_no = sprintf('%s-%s-%04d', $prefix, $year, $nextSeq);
            }
        });
    }


    public function scopeFilter($query, $filters)
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
        } elseif (isset($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        } elseif (isset($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date']);
        }
    }

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