<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'item_name'   => $this->item_name,
            'description' => $this->description,
            'hsn_code'    => $this->hsn_code,
            'qty'         => (float) $this->qty,
            'unit'        => $this->unit,
            'rate'        => (float) $this->rate,
            'amount'      => (float) $this->amount,
            'tax_rate'    => (float) $this->tax_rate,
            'tax_amount'  => (float) $this->tax_amount,
            'product_id'  => $this->product_id,
            'returned_qty'=> (float) ($this->returned_qty ?? 0),
            'remaining_qty' => (float) $this->remaining_qty,
            'is_returned' => (bool) $this->is_returned,
            'is_return_item' => (bool) $this->is_return_item,
        ];
    }
}
