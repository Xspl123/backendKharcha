<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'type'                    => $this->type,
            'note'                    => $this->note,
            'call_duration'           => $this->call_duration,
            'call_duration_formatted' => $this->call_duration_formatted,
            'outcome'                 => $this->outcome,
            'user' => $this->whenLoaded('user', fn() => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->format('d M Y H:i'),
        ];
    }
}