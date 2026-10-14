<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class InvoicePayment extends TenantModel
{
   use HasFactory;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'org_id',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($payment) {
            $payment->invoice->updatePaymentStatus();
        });

        static::deleted(function ($payment) {
            $payment->invoice->updatePaymentStatus();
        });
    }


    public function isRefunded(): bool
    {
        return str_starts_with($this->notes ?? '', '[REFUNDED');
    }

    public function getRefundDetailsAttribute(): ?array
    {
        if (!$this->isRefunded()) return null;
        if (preg_match('/\[REFUNDED:([^|\]]+)\|?([^\]]*)\]\s*(.*)/', $this->notes ?? '', $m)) {
            return [
                'refund_date'   => $m[1] ?: optional($this->payment_date)->format('Y-m-d'),
                'refund_method' => $m[2] ?: $this->payment_method,
                'reason'        => $m[3] ?: 'Payment refunded to client',
            ];
        }
        return [
            'refund_date'   => optional($this->payment_date)->format('Y-m-d'),
            'refund_method' => $this->payment_method,
            'reason'        => 'Payment refunded to client',
        ];
    }
}
