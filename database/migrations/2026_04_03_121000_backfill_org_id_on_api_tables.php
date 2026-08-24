<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'org_id')) {
            return;
        }

        foreach ($this->tables() as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'org_id') || !Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            DB::table($table)
                ->join('users', "{$table}.user_id", '=', 'users.id')
                ->whereNull("{$table}.org_id")
                ->whereNotNull('users.org_id')
                ->update([
                    "{$table}.org_id" => DB::raw('users.org_id'),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'org_id')) {
            return;
        }

        foreach ($this->tables() as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'org_id') || !Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            DB::table($table)
                ->join('users', "{$table}.user_id", '=', 'users.id')
                ->whereColumn("{$table}.org_id", 'users.org_id')
                ->update([
                    "{$table}.org_id" => null,
                ]);
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
};
