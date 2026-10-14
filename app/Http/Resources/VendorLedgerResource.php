<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'vendor'          => $this['vendor'],
            'opening_balance' => $this['opening_balance'],
            'total_purchases' => $this['total_purchases'],
            'total_returned'  => $this['total_returned'],
            'total_paid'      => $this['total_paid'],
            'closing_balance' => $this['closing_balance'],
            'ledger'          => $this['ledger'],
        ];
    }
}