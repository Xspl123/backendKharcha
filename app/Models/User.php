<?php

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, UsesCentralConnection;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'otp',
        'otp_expires_at',
        'is_verified',
        'password',
        'role_id',        
        'is_active',      
        'created_by',     
        'invoice_prefix',
        'org_id', 
        'user_type',
        'org_name',
        'plan'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'is_verified'       => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function organisation() 
    { 
        return $this->belongsTo(Organisation::class, 'org_id'); 
    }
    public function ownedOrgs()
    { 
        return $this->hasMany(Organisation::class, 'owner_id'); 
    }

    // ── Permission Helpers ────────────────────────────────

    public function hasPermission(string $permission): bool
    {
        if (!$this->role) return false;
        if ($this->role->name === 'super_admin') return true;
        return $this->role->permissions?->pluck('name')->contains($permission) ?? false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) return true;
        }
        return false;
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function getPermissionsAttribute(): array
    {
        return $this->relationLoaded('role') && $this->role->relationLoaded('permissions')
        ? $this->role->permissions->pluck('name')->toArray()
        : [];
    }

    public function scopeVisibleTo($query, $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isOrgOwner()) {
            return $query->where('org_id', $user->org_id ?? 0);
        }

        return $query->where('id', $user->id);
    }

    public function currentOrgId(): ?int
    {
        return $this->org_id;
    }

    public function isPersonal(): bool   { return $this->user_type === 'personal'; }
    public function isOrgOwner(): bool   { return $this->user_type === 'org_owner'; }
    public function isOrgMember(): bool  { return $this->user_type === 'org_member'; }
    public function hasOrg(): bool       { return !is_null($this->org_id); }
}
