<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GstReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'return_type'   => $this->return_type,
            'period'        => $this->period,
            'status'        => $this->status,
            'is_filed'      => $this->isFiled(),
            'data_snapshot' => $this->data_snapshot,
            'filed_at'      => $this->filed_at?->format('d-m-Y H:i'),
            'created_at'    => $this->created_at->format('d-m-Y H:i'),
        ];
    }
}