<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id,
                'invoice_id' => $this->invoice_id,
                'invoice_no' => $this->invoice?->invoice_no,
                'client_name' => $this->invoice?->client?->company_name,
                'amount' => number_format($this->amount, 2),
                'payment_date' => optional($this->payment_date)->format('d M Y'),
                'payment_method' => $this->payment_method,
                'transaction_id' => $this->transaction_id,
                'notes' => $this->notes,
                'created_at' => optional($this->created_at)->format('d M Y'),
            ];

    }
}
?>