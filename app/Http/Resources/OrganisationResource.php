<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'address'     => $this->address,
            'city'        => $this->city,
            'country'     => $this->country,
            'gst_number'  => $this->gst_number,
            'pan_number'  => $this->pan_number,
            'logo'        => $this->logo,
            'plan'        => $this->plan,
            'is_active'   => $this->is_active,
            'owner'       => $this->whenLoaded('owner', fn() => [
                'id'   => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'members_count' => $this->whenCounted('members'),
            'members'       => $this->whenLoaded('organisationUsers', fn() =>
                $this->organisationUsers->map(fn($ou) => [
                    'id'        => $ou->user->id,
                    'name'      => $ou->user->name,
                    'email'     => $ou->user->email,
                    'role'      => $ou->role ? ['id' => $ou->role->id, 'label' => $ou->role->label, 'color' => $ou->role->color] : null,
                    'is_active' => $ou->is_active,
                    'joined_at' => $ou->joined_at?->format('d M Y'),
                ])
            ),
            'created_at' => $this->created_at?->format('d M Y'),
        ];
    }
}