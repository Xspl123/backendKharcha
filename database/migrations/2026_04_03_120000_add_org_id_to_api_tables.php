<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'org_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('org_id')->nullable()->after('user_id');
                $blueprint->index('org_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'org_id')) {
                continue;
            }

            $indexName = $this->orgIndexName($table);

            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
                $blueprint->dropColumn('org_id');
            });
        }
    }

    private function tables(): array
    {
        return [
            'companies',
            'clients',
            'vendors',
            'purchase_orders',
            'vendor_payments',
            'product_categories',
            'products',
            'stock_movements',
            'invoices',
            'invoice_payments',
            'gst_returns',
            'hsn_codes',
            'campaigns',
            'attribute_groups',
        ];
    }

    private function orgIndexName(string $table): string
    {
        return "{$table}_org_id_index";
    }
};
