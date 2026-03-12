<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'name', 'type', 'unit', 'options', 'is_required', 'sort_order',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(AttributeGroup::class, 'group_id');
    }

    public function productValues()
    {
        return $this->hasMany(ProductAttribute::class, 'attribute_id');
    }
}