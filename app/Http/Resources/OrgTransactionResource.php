<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'voucher_no'            => $this->voucher_no,
            'org_id'                  => $this->org_id,
            'account_id'              => $this->account_id,
            'account_name'            => $this->account?->name,
            'account_type'            => $this->account?->account_type,
            'transaction_date'        => optional($this->transaction_date)->format('Y-m-d'),
            'type'                    => $this->type,
            'category'                => $this->category,
            'amount'                  => (float) $this->amount,
            'balance_after'           => (float) $this->balance_after,
            'payment_method'          => $this->payment_method,
            'reference_no'            => $this->reference_no,
            'description'             => $this->description,
            'transfer_account_id'     => $this->transfer_account_id,
            'transfer_account_name'   => $this->transferAccount?->name,
            'transfer_transaction_id' => $this->transfer_transaction_id,
            'invoice_payment_id'      => $this->invoice_payment_id,
            'vendor_payment_id'       => $this->vendor_payment_id,
            'created_at'              => $this->created_at?->toIso8601String(),
        ];
    }
}