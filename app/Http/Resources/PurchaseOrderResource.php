<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'po_number'              => $this->po_number,
            'po_date'                => $this->po_date?->format('Y-m-d'),
            'po_date_formatted'      => $this->po_date?->format('d M Y'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'received_date'          => $this->received_date?->format('Y-m-d'),

            // GST
            'supply_type'            => $this->supply_type,
            'place_of_supply'        => $this->place_of_supply,
            'is_reverse_charge'      => $this->is_reverse_charge,

            // Amounts
            'sub_total'              => round($this->sub_total, 2),
            'cgst'                   => round($this->cgst, 2),
            'sgst'                   => round($this->sgst, 2),
            'igst'                   => round($this->igst, 2),
            'total_tax'              => round($this->cgst + $this->sgst + $this->igst, 2),
            'total_amount'           => round($this->total_amount, 2),
            'paid_amount'            => round($this->paid_amount, 2),
            'balance_amount'         => round($this->balance_amount, 2),

            // Status
            'status'                 => $this->status,
            'can_approve'            => $this->canBeApproved(),
            'can_receive'            => $this->canBeReceived(),
            'can_cancel'             => $this->canBeCancelled(),

            // Extra
            'notes'                  => $this->notes,
            'terms_conditions'       => $this->terms_conditions,
            'items_count'            => $this->whenCounted('items'),

            // Relations
            'vendor'  => new VendorResource($this->whenLoaded('vendor')),
            'items'   => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'payments'=> VendorPaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_at->format('d M Y'),
            'updated_at' => $this->updated_at->format('d M Y'),
        ];
    }
}