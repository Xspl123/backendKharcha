<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadFollowUpResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'due_date'   => $this->due_date?->format('Y-m-d H:i'),
            'note'       => $this->note,
            'is_done'    => $this->is_done,
            'is_overdue' => $this->isOverdue(),
            'done_at'    => $this->done_at?->format('d M Y H:i'),
            'user' => $this->whenLoaded('user', fn() => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->format('d M Y H:i'),
        ];
    }
}