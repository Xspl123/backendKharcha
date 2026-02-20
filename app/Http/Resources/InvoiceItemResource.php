<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item_name,
            'description' => $this->description,
            'hsn_code' => $this->hsn_code,
            'qty' => number_format($this->qty, 2),
            'unit' => $this->unit,
            'rate' => number_format($this->rate, 2),
            'amount' => number_format($this->amount, 2),
            'tax_rate' => number_format($this->tax_rate, 2),
            'tax_amount' => number_format($this->tax_amount, 2),
        ];
    }
}
?>