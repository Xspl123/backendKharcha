<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ProductCategory extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'name',
        'slug',
        'description',
        'color',
    ];

    protected $attributes = [
        'color' => '#667eea',
    ];

    // ── Boot ──────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });

        static::updating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }

    // ── Relationships ─────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // ── Helpers ───────────────────────────────────────────

    public function getProductsCountAttribute(): int
    {
        return $this->products()->where('status', 'active')->count();
    }
}
