<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quotation_no' => $this->quotation_no,
            'quotation_date' => $this->quotation_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'status' => $this->status,
            'sub_total' => (float) $this->sub_total,
            'cgst' => (float) $this->cgst,
            'sgst' => (float) $this->sgst,
            'igst' => (float) $this->igst,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'company_name' => $this->lead->company_name,
                'contact_person' => $this->lead->contact_person,
            ]),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'company_name' => $this->client->company_name,
                'contact_person' => $this->client->contact_person,
            ]),
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->format('d M Y'),
            'updated_at' => $this->updated_at?->format('d M Y H:i'),
        ];
    }
}
