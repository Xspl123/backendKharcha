<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'type_label'     => $this->type_label,
            'direction'      => $this->direction,
            'qty'            => (float) $this->qty,
            'rate'           => (float) $this->rate,
            'value'          => (float) $this->value,
            'stock_before'   => (float) $this->stock_before,
            'stock_after'    => (float) $this->stock_after,
            'reference_type' => $this->reference_type,
            'reference_id'   => $this->reference_id,
            'reference_no'   => $this->reference_no,
            'notes'          => $this->notes,
            'movement_date'  => $this->movement_date?->format('Y-m-d'),
            'movement_date_formatted' => $this->movement_date?->format('d M Y'),

            // Relations
            'product'        => $this->whenLoaded('product', fn() => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku,
                'unit' => $this->product->unit,
            ]),

            'created_at'     => $this->created_at->format('d M Y'),
        ];
    }
}