<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Throwable;

class OrganisationTenantProvisioner
{
    public function provision(Organisation $organisation, ?string $tenantId = null, ?string $databaseName = null): Tenant
    {
        if ($organisation->tenant_id) {
            return $organisation->tenant()->firstOrFail();
        }

        $tenantId ??= (string) Str::uuid();
        $databaseName ??= $this->resolveDatabaseName($organisation, $tenantId);

        $tenant = Tenant::create([
            'id' => $tenantId,
            'tenancy_db_name' => $databaseName,
        ]);

        try {
            $organisation->update([
                'tenant_id' => $tenant->getTenantKey(),
            ]);
        } catch (Throwable $e) {
            $tenant->delete();

            throw $e;
        }

        return $tenant;
    }

    public function resolveDatabaseName(Organisation $organisation, string $tenantId): string
{
    // Slug generate karo
    $slug = Str::slug($organisation->slug ?: $organisation->name, '_');

    // Random suffix (6 chars) taaki unique rahe
    $random = Str::lower(Str::random(6));

    // Prefix + suffix ki length nikal lo
    $prefix = "org_{$organisation->id}_";
    $reserved = strlen($prefix) + strlen($random) + 1; // +1 underscore

    // MySQL max identifier = 64 chars
    $maxSlugLength = max(10, 64 - $reserved);

    // Slug truncate karo
    $slug = substr($slug, 0, $maxSlugLength);

    $candidate = "{$prefix}{$slug}_{$random}";

    while ($this->databaseExists($tenantId, $candidate)) {
        $random = Str::lower(Str::random(6));
        $candidate = "{$prefix}{$slug}_{$random}";
    }

    return $candidate;
}

    public function databaseExists(string $tenantId, string $databaseName): bool
    {
        $tenant = new Tenant([
            'id' => $tenantId,
        ]);

        $tenant->setInternal('db_name', $databaseName);

        return $tenant->database()->manager()->databaseExists($databaseName);
    }
}
