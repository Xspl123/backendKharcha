<?php
// app/Http/Resources/PurchaseReturnResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'return_number' => $this->po_number,
            'return_date'   => $this->po_date,
            'total_amount'  => (float) $this->total_amount,
            'sub_total'     => (float) ($this->sub_total ?? 0),
            'cgst'          => (float) ($this->cgst ?? 0),
            'sgst'          => (float) ($this->sgst ?? 0),
            'igst'          => (float) ($this->igst ?? 0),
            'status'        => $this->status,
            'notes'         => $this->notes,

            // ✅ FIX: vendor null safe + correct field names
            'vendor' => $this->vendor ? [
                'id'           => $this->vendor->id,
                'name'         => $this->vendor->vendor_name,   // ✅ vendor_name (DB column)
                'company_name' => $this->vendor->company_name ?? null,
                'phone'        => $this->vendor->phone         ?? null,
                'gst_no'       => $this->vendor->gst_no        ?? null,
            ] : null,

            // ✅ FIX: originalPO null safe
            'original_po' => $this->originalPO ? [
                'id'           => $this->originalPO->id,
                'po_number'    => $this->originalPO->po_number,
                'po_date'      => $this->originalPO->po_date,
                'sub_total'    => (float) $this->originalPO->sub_total,
                'total_amount' => (float) $this->originalPO->total_amount,
                'paid_amount'  => (float) $this->originalPO->paid_amount,
                'balance_amount' => (float) $this->originalPO->balance_amount,
                'status'       => $this->originalPO->status,
                'payment_summary' => [
                    'total_amount' => (float) $this->originalPO->total_amount,
                    'paid_amount' => (float) $this->originalPO->paid_amount,
                    'balance_amount' => (float) $this->originalPO->balance_amount,
                ],
            ] : null,

            'items' => PurchaseReturnItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
