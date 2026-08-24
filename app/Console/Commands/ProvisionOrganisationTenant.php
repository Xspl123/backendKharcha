<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Services\OrganisationTenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProvisionOrganisationTenant extends Command
{
    public function __construct(private OrganisationTenantProvisioner $tenantProvisioner)
    {
        parent::__construct();
    }

    protected $signature = 'organisation:provision-tenant
                            {org_id? : Central organisation ID}
                            {--all : Provision all organisations that do not yet have a tenant}
                            {--database= : Explicit tenant database name}
                            {--id= : Explicit tenancy tenant ID}';

    protected $description = 'Create and attach a stancl/tenancy tenant for an organisation.';

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->provisionAll();
        }

        $organisationId = $this->argument('org_id');

        if (! $organisationId) {
            $this->error('Pass an organisation ID or use --all.');

            return self::INVALID;
        }

        $organisation = Organisation::find($organisationId);

        if (! $organisation) {
            $this->error('Organisation not found.');

            return self::FAILURE;
        }

        return $this->provisionOrganisation($organisation);
    }

    private function provisionAll(): int
    {
        $organisations = Organisation::query()
            ->whereNull('tenant_id')
            ->orderBy('id')
            ->get();

        if ($organisations->isEmpty()) {
            $this->info('No organisations pending tenant provisioning.');

            return self::SUCCESS;
        }

        foreach ($organisations as $organisation) {
            $result = $this->provisionOrganisation($organisation, false);

            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        return self::SUCCESS;
    }

    private function provisionOrganisation(Organisation $organisation, bool $showNextStep = true): int
    {
        if ($organisation->tenant_id) {
            $this->warn("Organisation already linked to tenant [{$organisation->tenant_id}].");

            return self::SUCCESS;
        }

        $tenantId = $showNextStep ? ($this->option('id') ?: (string) Str::uuid()) : (string) Str::uuid();
        $databaseName = $showNextStep && $this->option('database')
            ? $this->option('database')
            : $this->tenantProvisioner->resolveDatabaseName($organisation, $tenantId);

        $tenant = $this->tenantProvisioner->provision($organisation, $tenantId, $databaseName);

        $this->info("Tenant [{$tenant->getTenantKey()}] created for organisation [{$organisation->id}] using database [{$databaseName}].");

        if ($showNextStep) {
            $this->line('Next step: run tenants:migrate to create business tables inside tenant databases.');
        }

        return self::SUCCESS;
    }
}
