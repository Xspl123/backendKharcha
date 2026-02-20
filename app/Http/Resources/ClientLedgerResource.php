<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'client' => $this['client'],
            'closing_balance' => $this['closing_balance'],
            'ledger' => $this['ledger']
        ];
    }
}

