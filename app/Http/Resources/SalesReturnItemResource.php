<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'       => $this->id,

            // ✅ FIX: product null safe + item_name fallback
            'product' => $this->product ? [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku  ?? null,
                'unit' => $this->product->unit ?? null,
            ] : [
                'id'   => null,
                'name' => $this->item_name,
                'sku'  => null,
                'unit' => $this->unit ?? 'pcs',
            ],

            'item_name'  => $this->item_name,
            'hsn_code'   => $this->hsn_code   ?? null,
            'quantity'   => (float) $this->qty,
            'unit'       => $this->unit        ?? 'pcs',
            'rate'       => (float) $this->rate,
            'amount'     => (float) $this->amount,
            'tax_rate'   => (float) ($this->tax_rate   ?? 0),
            'tax_amount' => (float) ($this->tax_amount ?? 0),
            'reason'     => $this->description ?? null,
        ];
    }
}