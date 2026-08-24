<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\LeadResource;

class CampaignResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'type'        => $this->type,
            'status'      => $this->status,
            'description' => $this->description,
            'leads' => LeadResource::collection($this->whenLoaded('leads')),
            'start_date'  => $this->start_date?->format('Y-m-d'),
            'end_date'    => $this->end_date?->format('Y-m-d'),
            'leads_count' => $this->whenCounted('leads'),
            'created_at'  => $this->created_at?->format('d M Y'),
        ];
    }
}