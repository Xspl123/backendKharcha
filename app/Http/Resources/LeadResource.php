<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'company_name'         => $this->company_name,
            'contact_person'       => $this->contact_person,
            'phone'                => $this->phone,
            'email'                => $this->email,
            'website'              => $this->website,
            'country'              => $this->country,
            'city'                 => $this->city,
            'source'               => $this->source,
            'product_interest'     => $this->product_interest,
            'budget'               => $this->budget,
            'currency'             => $this->currency,
            'notes'                => $this->notes,
            'custom_fields'        => $this->custom_fields,
            'status'               => $this->status,
            'lost_reason'          => $this->lost_reason,
            'expected_close_date'  => $this->expected_close_date?->format('Y-m-d'),
            'is_won'               => $this->isWon(),
            'is_lost'              => $this->isLost(),
            'is_open'              => $this->isOpen(),

            // Relations
            'owner'    => $this->whenLoaded('owner', fn() => [
                'id'   => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'client'   => $this->whenLoaded('client', fn() => [
                'id'   => $this->client->id,
                'name' => $this->client->name,
            ]),
            'activities'    => $this->whenLoaded('activities',
                fn() => LeadActivityResource::collection($this->activities)
            ),
            'follow_ups'    => $this->whenLoaded('followUps',
                fn() => LeadFollowUpResource::collection($this->followUps)
            ),
            'lead_products' => $this->whenLoaded('leadProducts',
                fn() => LeadProductResource::collection($this->leadProducts)
            ),
            'quotations' => $this->whenLoaded('quotations',
                fn() => QuotationResource::collection($this->quotations)
            ),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn() => [
                'id'        => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'total'     => $this->purchaseOrder->total_amount,
            ]),
            'invoice' => $this->whenLoaded('invoice', fn() => [
                'id'             => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_no,
                'total'          => $this->invoice->total_amount,
            ]),

            // Counts
            'activities_count'      => $this->whenCounted('activities'),
            'follow_ups_count'      => $this->whenCounted('followUps'),
            'pending_follow_ups'    => $this->whenCounted('pendingFollowUps'),

            'po_id'         => $this->po_id,
            'invoice_id'    => $this->invoice_id,
            'created_at'    => $this->created_at?->format('d M Y'),
            'updated_at'    => $this->updated_at?->format('d M Y H:i'),
        ];
    }
}