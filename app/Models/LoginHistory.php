<?php

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'user_id', 'device_id', 'device_name', 'browser', 'platform', 'ip_address',
        'user_agent', 'city', 'region', 'country', 'country_code', 'timezone', 'logged_in_at',
    ];

    protected function casts(): array
    {
        return ['logged_in_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
