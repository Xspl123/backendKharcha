<?php

namespace App\Repositories\Traits;

/**
 * OrgScope Trait
 * 
 * Har repository mein use karo.
 * Automatically org_id ya user_id se scope karta hai:
 * - Org user   → org_id se scope
 * - Personal   → user_id se scope
 */
trait OrgScope
{
    /**
     * Query mein org ya user scope add karo
     * Usage: $this->scopeQuery(Model::query())
     */
    protected function scopeQuery($query)
    {
        $user = auth()->user();

        if ($user->hasOrg()) {
            return $query->where('org_id', $user->org_id);
        }

        return $query->where('user_id', $user->id);
    }

    /**
     * Create data mein org_id ya user_id add karo
     * Usage: $data = $this->scopeData($data)
     */
    protected function scopeData(array $data): array
    {
        $user = auth()->user();

        $data['user_id'] = $user->id;

        if ($user->hasOrg()) {
            $data['org_id'] = $user->org_id;
        }

        return $data;
    }

    /**
     * Auth user ID
     */
    protected function userId(): int
    {
        return auth()->id();
    }

    /**
     * Current org ID (null if personal)
     */
    protected function orgId(): ?int
    {
        return auth()->user()->org_id;
    }
}