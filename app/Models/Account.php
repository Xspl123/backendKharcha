<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'cash';
    public const TYPE_BANK = 'bank';
    public const TYPE_CREDIT_CARD = 'credit_card';

    protected $table = 'accounts';

    protected $fillable = [
        'user_id',
        'account_name',
        'account_type',
        'account_balance',
        'credit_limit',
        'billing_cycle_day',
        'payment_due_day',
    ];

    protected $casts = [
        'account_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'billing_cycle_day' => 'integer',
        'payment_due_day' => 'integer',
    ];

    protected $appends = [
        'outstanding',
        'available_limit',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function isCreditCard(): bool
    {
        return $this->account_type === self::TYPE_CREDIT_CARD;
    }

    public function getOutstandingAttribute(): float
    {
        if (!$this->isCreditCard()) {
            return 0.0;
        }

        return max(0.0, -(float) $this->account_balance);
    }

    public function getAvailableLimitAttribute(): ?float
    {
        if (!$this->isCreditCard() || $this->credit_limit === null) {
            return null;
        }

        return max(0.0, (float) $this->credit_limit - $this->outstanding);
    }
}
