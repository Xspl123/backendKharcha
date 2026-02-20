<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'color'          => $this->color,
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at?->format('d M Y'),
            
        ];
    }
}