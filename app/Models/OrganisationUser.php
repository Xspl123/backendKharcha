<?php

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class OrganisationUser extends Model
{
    use UsesCentralConnection;

    protected $table = 'organisation_users';

    protected $fillable = [
        'org_id', 'user_id', 'role_id', 'is_active', 'joined_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'org_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
