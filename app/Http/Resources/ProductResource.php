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

            'created_at'          => $this->created_at->format('d M Y'),
            'updated_at'          => $this->updated_at->format('d M Y'),
        ];
    }
}