<?php

namespace App\Repositories\Traits;

trait PaginatesResults
{
    protected function resolvePerPage(array $filters = [], int $default = 25, int $max = 100): int
    {
        $perPage = (int) ($filters['per_page'] ?? $default);

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }
}
