<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'gstin' => $this->gstin,
            'address' => $this->address,
            'opening_balance' => $this->opening_balance,
            'created_at' => $this->created_at->format('d M Y'),
        ];
    }
}
