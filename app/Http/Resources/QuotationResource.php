<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Company;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Same "primary company" convention used for push-notification
        // branding — the first Company row created for this org, used
        // here to print the quotation PDF with the org's name/logo/GST
        // instead of a generic placeholder.
        $company = Company::where('org_id', $this->org_id)->oldest('id')->first();

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
            'company' => $company ? [
                'company_name' => $company->company_name,
                'logo_url' => $company->logo_url,
                'address' => $company->address,
                'city' => $company->city,
                'state' => $company->state,
                'pincode' => $company->pincode,
                'phone' => $company->phone,
                'email' => $company->email,
                'gstin' => $company->gstin,
            ] : null,
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'company_name' => $this->lead->company_name,
                'contact_person' => $this->lead->contact_person,
                'email' => $this->lead->email,
                'phone' => $this->lead->phone,
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