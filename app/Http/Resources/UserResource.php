<?php
// ── File: app/Http/Resources/UserResource.php ──────────────

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'is_active'      => $this->is_active,
            'is_verified'    => $this->is_verified,
            'invoice_prefix' => $this->invoice_prefix,
            'role'           => $this->whenLoaded('role', fn() => [
                'id'    => $this->role->id,
                'name'  => $this->role->name,
                'label' => $this->role->label,
                'color' => $this->role->color,
            ]),
            'permissions'    => $this->when(
                $request->routeIs('auth.me'),
                fn() => $this->permissions
            ),
            'created_by'     => $this->whenLoaded('createdBy', fn() => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at'     => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}