<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'amount'            => round($this->amount, 2),
            'payment_date'      => $this->payment_date?->format('Y-m-d'),
            'payment_date_formatted' => $this->payment_date?->format('d M Y'),
            'payment_method'    => $this->payment_method,
            'payment_method_label' => $this->payment_method_label,
            'reference_no'      => $this->reference_no,
            'notes'             => $this->notes,

            'vendor_id'         => $this->vendor_id,
            'purchase_order_id' => $this->purchase_order_id,

            'purchase_order'    => $this->whenLoaded('purchaseOrder', fn() => [
                'id'           => $this->purchaseOrder->id,
                'po_number'    => $this->purchaseOrder->po_number,
                'total_amount' => $this->purchaseOrder->total_amount,
                'paid_amount'  => $this->purchaseOrder->paid_amount,
                'balance_amount'=> $this->purchaseOrder->balance_amount,
            ]),

            'created_at' => $this->created_at->format('d M Y'),
        ];
    }
}