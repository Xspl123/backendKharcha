<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CleanupOrphanTenants extends Command
{
    protected $signature = 'tenancy:cleanup-orphans {--delete : Delete orphan tenant records and their databases}';

    protected $description = 'List tenant records that are not linked to any organisation, with optional deletion.';

    public function handle(): int
    {
        $linkedTenantIds = Organisation::query()
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id');

        $orphans = Tenant::query()
            ->whereNotIn('id', $linkedTenantIds)
            ->orderBy('id')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphan tenants found.');

            return self::SUCCESS;
        }

        foreach ($orphans as $tenant) {
            $database = $tenant->database()->getName();

            if ($this->option('delete')) {
                $tenant->delete();
                $this->warn("Deleted orphan tenant [{$tenant->id}] and database [{$database}].");
                continue;
            }

            $this->line("Orphan tenant [{$tenant->id}] -> database [{$database}]");
        }

        if (! $this->option('delete')) {
            $this->comment('Run with --delete only after confirming these tenants are not needed.');
        }

        return self::SUCCESS;
    }
}
