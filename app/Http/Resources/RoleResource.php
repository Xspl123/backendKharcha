<?php
// ── File: app/Http/Resources/RoleResource.php ──────────────

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'label'       => $this->label,
            'description' => $this->description,
            'color'       => $this->color,
            'permissions' => $this->whenLoaded('permissions',
                fn() => $this->permissions->map(fn($p) => [
                    'id'     => $p->id,
                    'name'   => $p->name,
                    'label'  => $p->label,
                    'module' => $p->module,
                ])
            ),
            'users_count' => $this->whenCounted('users'),
            'created_at'  => $this->created_at?->format('Y-m-d'),
        ];
    }
}