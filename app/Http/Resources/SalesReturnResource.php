<?php

namespace App\Http\Resources;
use App\Http\Resources\SalesReturnItemResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'return_number' => $this->invoice_no,  
            'return_date'   => $this->invoice_date,
            'total_amount'  => (float) $this->total_amount,
            'sub_total'     => (float) $this->sub_total,
            'cgst'          => (float) $this->cgst,
            'sgst'          => (float) $this->sgst,
            'igst'          => (float) $this->igst,
            'status'        => $this->status,
            'notes'         => $this->notes,

            //?-> use karo — null safe
            'customer' => $this->client ? [
                'id'    => $this->client->id,
                'name'  => $this->client->name,
                'phone' => $this->client->phone ?? null,
                'email' => $this->client->email ?? null,
            ] : null,

            'original_invoice' => $this->originalInvoice ? [
                'id'             => $this->originalInvoice->id,
                'invoice_number' => $this->originalInvoice->invoice_no, 
                'invoice_date'   => $this->originalInvoice->invoice_date,
                'total_amount'   => (float) $this->originalInvoice->total_amount,
            ] : null,

            'items' => SalesReturnItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}