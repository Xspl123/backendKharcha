<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateOrganisationDataToTenant extends Command
{
    protected $signature = 'tenancy:migrate-org-data
                            {--org_id=* : One or more organisation IDs}
                            {--all : Migrate all organisations that have a tenant_id}
                            {--truncate : Clear tenant tables before copying data}';

    protected $description = 'Copy central org-scoped business data into each organisation tenant database.';

    public function handle(): int
    {
        $organisations = $this->resolveOrganisations();

        if ($organisations->isEmpty()) {
            $this->warn('No organisations matched the given options.');

            return self::SUCCESS;
        }

        $central = DB::connection(config('tenancy.database.central_connection'));

        foreach ($organisations as $organisation) {
            if (! $organisation->tenant) {
                $this->warn("Organisation [{$organisation->id}] has no tenant linked. Skipping.");
                continue;
            }

            $this->line("Migrating organisation [{$organisation->id}] {$organisation->name} -> {$organisation->tenant->database()->getName()}");

            $organisation->tenant->run(function () use ($organisation, $central) {
                $tenant = DB::connection('tenant');

                if ($this->option('truncate')) {
                    $this->truncateTenantTables($tenant);
                }

                $this->copyOrganisationData($organisation->id, $central, $tenant);
            });
        }

        $this->info('Organisation data migration finished.');

        return self::SUCCESS;
    }

    private function resolveOrganisations(): Collection
    {
        $query = Organisation::with('tenant')->whereNotNull('tenant_id')->orderBy('id');

        if ($this->option('all')) {
            return $query->get();
        }

        $orgIds = array_filter(array_map('intval', (array) $this->option('org_id')));

        if (empty($orgIds)) {
            return collect();
        }

        return $query->whereIn('id', $orgIds)->get();
    }

    private function copyOrganisationData(int $orgId, ConnectionInterface $central, ConnectionInterface $tenant): void
    {
        $directTables = [
            'companies',
            'clients',
            'vendors',
            'product_categories',
            'products',
            'invoices',
            'invoice_payments',
            'purchase_orders',
            'vendor_payments',
            'stock_movements',
            'leads',
            'campaigns',
            'hsn_codes',
            'attribute_groups',
            'gst_returns',
            'quotations',
        ];

        foreach ($directTables as $table) {
            $rows = $this->fetchByOrg($central, $table, $orgId);
            $this->upsertRows($tenant, $table, $rows);
        }

        $invoiceIds = $this->fetchIds($central, 'invoices', $orgId);
        $purchaseOrderIds = $this->fetchIds($central, 'purchase_orders', $orgId);
        $leadIds = $this->fetchIds($central, 'leads', $orgId);
        $campaignIds = $this->fetchIds($central, 'campaigns', $orgId);
        $attributeGroupIds = $this->fetchIds($central, 'attribute_groups', $orgId);
        $quotationIds = $this->fetchIds($central, 'quotations', $orgId);
        $productIds = $this->fetchIds($central, 'products', $orgId);

        $this->upsertRows($tenant, 'invoice_items', $this->fetchByForeignKey($central, 'invoice_items', 'invoice_id', $invoiceIds));
        $this->upsertRows($tenant, 'purchase_order_items', $this->fetchByForeignKey($central, 'purchase_order_items', 'purchase_order_id', $purchaseOrderIds));
        $this->upsertRows($tenant, 'lead_activities', $this->fetchByForeignKey($central, 'lead_activities', 'lead_id', $leadIds));
        $this->upsertRows($tenant, 'lead_follow_ups', $this->fetchByForeignKey($central, 'lead_follow_ups', 'lead_id', $leadIds));
        $this->upsertRows($tenant, 'lead_products', $this->fetchByForeignKey($central, 'lead_products', 'lead_id', $leadIds));
        $this->upsertRows($tenant, 'campaign_leads', $this->fetchCampaignLeads($central, $campaignIds, $leadIds));
        $this->upsertRows($tenant, 'attributes', $this->fetchByForeignKey($central, 'attributes', 'group_id', $attributeGroupIds));

        $attributeIds = $this->fetchByForeignKey($central, 'attributes', 'group_id', $attributeGroupIds)->pluck('id')->all();
        $this->upsertRows($tenant, 'product_attributes', $this->fetchProductAttributes($central, $productIds, $attributeIds));
        $this->upsertRows($tenant, 'quotation_items', $this->fetchByForeignKey($central, 'quotation_items', 'quotation_id', $quotationIds));

        $this->syncInvoiceSequences($orgId, $tenant);
    }

    private function fetchByOrg(ConnectionInterface $central, string $table, int $orgId): Collection
    {
        if (! Schema::connection($central->getName())->hasTable($table)) {
            return collect();
        }

        return collect(
            $central->table($table)
                ->where('org_id', $orgId)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all()
        );
    }

    private function fetchIds(ConnectionInterface $central, string $table, int $orgId): array
    {
        if (! Schema::connection($central->getName())->hasTable($table)) {
            return [];
        }

        return $central->table($table)
            ->where('org_id', $orgId)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    private function fetchByForeignKey(ConnectionInterface $central, string $table, string $foreignKey, array $ids): Collection
    {
        if (empty($ids) || ! Schema::connection($central->getName())->hasTable($table)) {
            return collect();
        }

        return collect(
            $central->table($table)
                ->whereIn($foreignKey, $ids)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all()
        );
    }

    private function fetchCampaignLeads(ConnectionInterface $central, array $campaignIds, array $leadIds): Collection
    {
        if (empty($campaignIds) || empty($leadIds) || ! Schema::connection($central->getName())->hasTable('campaign_leads')) {
            return collect();
        }

        return collect(
            $central->table('campaign_leads')
                ->whereIn('campaign_id', $campaignIds)
                ->whereIn('lead_id', $leadIds)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all()
        );
    }

    private function fetchProductAttributes(ConnectionInterface $central, array $productIds, array $attributeIds): Collection
    {
        if (empty($productIds) || empty($attributeIds) || ! Schema::connection($central->getName())->hasTable('product_attributes')) {
            return collect();
        }

        return collect(
            $central->table('product_attributes')
                ->whereIn('product_id', $productIds)
                ->whereIn('attribute_id', $attributeIds)
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all()
        );
    }

    private function upsertRows(ConnectionInterface $tenant, string $table, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $columns = array_keys($rows->first());

        $tenant->table($table)->upsert(
            $rows->all(),
            ['id'],
            array_values(array_filter($columns, fn ($column) => $column !== 'id'))
        );

        $this->line("  {$table}: {$rows->count()} rows");
    }

    private function syncInvoiceSequences(int $orgId, ConnectionInterface $tenant): void
    {
        $existingMax = (int) $tenant->table('invoices')->count();

        if ($existingMax === 0) {
            return;
        }

        $year = (int) now()->year;

        $tenant->table('invoice_sequences')->updateOrInsert(
            ['tenant_id' => $orgId, 'year' => $year],
            ['current_no' => $existingMax, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function truncateTenantTables(ConnectionInterface $tenant): void
    {
        $tables = [
            'quotation_items',
            'quotations',
            'lead_products',
            'campaign_leads',
            'campaigns',
            'lead_follow_ups',
            'lead_activities',
            'leads',
            'product_attributes',
            'attributes',
            'attribute_groups',
            'gst_returns',
            'hsn_codes',
            'stock_movements',
            'vendor_payments',
            'purchase_order_items',
            'purchase_orders',
            'invoice_payments',
            'invoice_items',
            'invoices',
            'products',
            'product_categories',
            'vendors',
            'clients',
            'companies',
            'invoice_sequences',
        ];

        $tenant->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                if (Schema::connection('tenant')->hasTable($table)) {
                    $tenant->table($table)->truncate();
                }
            }
        } finally {
            $tenant->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
