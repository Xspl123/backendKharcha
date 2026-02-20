<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
         return [
        'id' => $this->id,
        'user_id' => $this->user_id,
        'company_id' => $this->company_id,
        'client_id' => $this->client_id,
        'invoice_no' => $this->invoice_no,
        'invoice_date' => $this->invoice_date,
        'due_date' => $this->due_date,
        'sub_total' => $this->sub_total,
        'cgst' => $this->cgst,
        'sgst' => $this->sgst,
        'igst' => $this->igst,
        'total_amount' => $this->total_amount,
        'paid_amount' => $this->paid_amount,
        'balance_amount' => $this->balance_amount,
        'status' => $this->status,
        'notes' => $this->notes,
        'invoice_type' => $this->invoice_type,
        'supply_type' => $this->supply_type,
        'place_of_supply' => $this->place_of_supply,
        'is_reverse_charge' => $this->is_reverse_charge,
        
        'client' => new ClientResource($this->whenLoaded('client')),
        'company' => $this->whenLoaded('company'), // ADD THIS LINE
        'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
        
        'created_at' => $this->created_at->format('d M Y'),
        'updated_at' => $this->updated_at->format('d M Y'),
    ];
    }
}
?>