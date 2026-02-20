<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'type',
        'sort_order',
        'total_amount',
        'budget',
        'user_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'budget' => 'decimal:2',
        'type' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
}
