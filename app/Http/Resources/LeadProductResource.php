<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'quantity' => (float) $this->quantity,
            'expected_price' => $this->expected_price === null ? null : (float) $this->expected_price,
            'note' => $this->note,
        ];
    }
}
