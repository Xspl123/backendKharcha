<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'vendor_name'      => $this->vendor_name,
            'company_name'     => $this->company_name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'address'          => $this->address,
            'city'             => $this->city,
            'state'            => $this->state,
            'pincode'          => $this->pincode,
            'country'          => $this->country,
            'gstin'            => $this->gstin,
            'pan'              => $this->pan,
            'bank_name'        => $this->bank_name,
            'bank_account_no'  => $this->bank_account_no,
            'bank_ifsc'        => $this->bank_ifsc,
            'bank_branch'      => $this->bank_branch,
            'status'           => $this->status,
            'notes'            => $this->notes,

            // Counts
            'purchase_orders_count' => $this->whenCounted('purchaseOrders'),

            // Relations
            'purchase_orders'  => PurchaseOrderResource::collection(
                                    $this->whenLoaded('purchaseOrders')
                                  ),
            'payments'         => VendorPaymentResource::collection(
                                    $this->whenLoaded('payments')
                                  ),

            'created_at' => $this->created_at?->format('d M Y'),
            'updated_at' => $this->updated_at?->format('d M Y'),

        ];
    }
}