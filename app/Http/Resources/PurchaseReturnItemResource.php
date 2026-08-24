<?php
// app/Http/Resources/PurchaseReturnResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'original_item_id' => $this->original_item_id,
            'item_name' => $this->item_name,
            'description' => $this->description,
            'hsn_code' => $this->hsn_code,
            'qty' => (float) $this->qty,
            'unit' => $this->unit,
            'rate' => (float) $this->rate,
            'amount' => (float) $this->amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
        ];
    }
}
