<?php

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organisation extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'owner_id', 'name', 'slug', 'email', 'phone',
        'address', 'city', 'country', 'gst_number',
        'pan_number', 'logo', 'plan', 'is_active', 'settings', 'tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    // ── Auto slug generate ────────────────────────────────
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($org) {
            if (empty($org->slug)) {
                $org->slug = Str::slug($org->name) . '-' . Str::random(6);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->belongsToMany(
            User::class,
            'organisation_users',
            'org_id',
            'user_id'
        )->withPivot(['role_id', 'is_active', 'joined_at'])
         ->withTimestamps();
    }

    public function organisationUsers()
    {
        return $this->hasMany(OrganisationUser::class, 'org_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function products()       { return $this->hasMany(Product::class, 'org_id'); }
    public function clients()        { return $this->hasMany(Client::class, 'org_id'); }
    public function vendors()        { return $this->hasMany(Vendor::class, 'org_id'); }
    public function invoices()       { return $this->hasMany(Invoice::class, 'org_id'); }
    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class, 'org_id'); }
    public function leads()          { return $this->hasMany(Lead::class, 'org_id'); }
    public function campaigns()      { return $this->hasMany(Campaign::class, 'org_id'); }

    // ── Helper ───────────────────────────────────────────
    public function hasMember(int $userId): bool
    {
        return $this->organisationUsers()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();
    }
}
