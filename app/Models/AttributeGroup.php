<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttributeGroup extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'org_id', 'category_id', 'name', 'description', 'sort_order',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function attributes()
    {
        return $this->hasMany(Attribute::class, 'group_id')->orderBy('sort_order');
    }
}
