<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'sku'                 => $this->sku,
            'hsn_code'            => $this->hsn_code,
            'description'         => $this->description,
            'unit'                => $this->unit,
            'purchase_price'      => (float) $this->purchase_price,
            'selling_price'       => (float) $this->selling_price,
            'tax_rate'            => (float) $this->tax_rate,
            'opening_stock'       => (float) $this->opening_stock,
            'current_stock'       => (float) $this->current_stock,
            'low_stock_alert'     => (float) $this->low_stock_alert,
            'avg_cost'            => (float) $this->avg_cost,
            'stock_value'         => (float) $this->stock_value,
            'stock_status'        => $this->stock_status,
            'is_low_stock'        => $this->isLowStock(),
            'is_out_of_stock'     => $this->isOutOfStock(),
            'attribute_count'     => $this->whenCounted('attributeValues'),
            'status'              => $this->status,
            'notes'               => $this->notes,

            // Relations
            'product_category_id' => $this->product_category_id,
            'category'            => new ProductCategoryResource(
                                        $this->whenLoaded('category')
                                     ),
            'stock_movements'     => StockMovementResource::collection(
                                        $this->whenLoaded('stockMovements')
                                     ),
            'attribute_values'    => $this->whenLoaded('attributeValues', fn () =>
                $this->attributeValues->map(fn ($value) => [
                    'id' => $value->id,
                    'attribute_id' => $value->attribute_id,
                    'value' => $value->value,
                    'attribute' => $value->relationLoaded('attribute') && $value->attribute ? [
                        'id' => $value->attribute->id,
                        'name' => $value->attribute->name,
                        'type' => $value->attribute->type,
                        'unit' => $value->attribute->unit,
                        'group_id' => $value->attribute->group_id,
                    ] : null,
                ])
            ),
            'attribute_summary'   => $this->whenLoaded('attributeValues', fn () =>
                $this->attributeValues
                    ->take(5)
                    ->map(fn ($value) => [
                        'attribute_id' => $value->attribute_id,
                        'attribute_name' => $value->attribute?->name,
                        'group_id' => $value->attribute?->group_id,
                        'value' => $value->value,
                    ])
                    ->values()
            ),
            'attributes'          => $this->whenLoaded('attributes', fn () =>
                $this->attributes->map(fn ($attribute) => [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'type' => $attribute->type,
                    'unit' => $attribute->unit,
                    'group_id' => $attribute->group_id,
                    'value' => $attribute->pivot?->value,
                ])
            ),

            'created_at'          => $this->created_at->format('d M Y'),
            'updated_at'          => $this->updated_at->format('d M Y'),
        ];
    }
}
