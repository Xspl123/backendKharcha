<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'org_id'          => $this->org_id,
            'name'            => $this->name,
            'account_type'    => $this->account_type,
            'bank_name'       => $this->bank_name,
            'account_number'  => $this->account_number,
            'ifsc_code'       => $this->ifsc_code,
            'upi_id'          => $this->upi_id,
            'opening_balance' => (float) ($this->opening_balance ?? 0),
            'current_balance' => (float) ($this->current_balance ?? 0),
            'is_active'       => (bool) $this->is_active,
            'is_default'      => (bool) $this->is_default,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}