<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('clients', ['org_id', 'company_name'], 'clients_org_company_index');
        $this->addIndex('vendors', ['org_id', 'status'], 'vendors_org_status_index');
        $this->addIndex('products', ['org_id', 'status'], 'products_org_status_index');
        $this->addIndex('products', ['org_id', 'name'], 'products_org_name_index');
        $this->addIndex('product_categories', ['org_id', 'name'], 'product_categories_org_name_index');
        $this->addIndex('invoices', ['org_id', 'status', 'invoice_date'], 'invoices_org_status_date_index');
        $this->addIndex('invoice_payments', ['org_id', 'payment_date'], 'invoice_payments_org_date_index');
        $this->addIndex('purchase_orders', ['org_id', 'status', 'po_date'], 'purchase_orders_org_status_date_index');
        $this->addIndex('vendor_payments', ['org_id', 'payment_date'], 'vendor_payments_org_date_index');
        $this->addIndex('stock_movements', ['org_id', 'movement_date'], 'stock_movements_org_date_index');
        $this->addIndex('stock_movements', ['org_id', 'product_id'], 'stock_movements_org_product_index');
        $this->addIndex('leads', ['org_id', 'status'], 'leads_org_status_index');
        $this->addIndex('leads', ['org_id', 'owner_id'], 'leads_org_owner_index');
        $this->addIndex('campaigns', ['org_id', 'status'], 'campaigns_org_status_index');
        $this->addIndex('hsn_codes', ['org_id', 'hsn_code'], 'hsn_codes_org_code_index');
        $this->addIndex('attribute_groups', ['org_id', 'category_id'], 'attribute_groups_org_category_index');
    }

    public function down(): void
    {
        $this->dropIndex('clients', 'clients_org_company_index');
        $this->dropIndex('vendors', 'vendors_org_status_index');
        $this->dropIndex('products', 'products_org_status_index');
        $this->dropIndex('products', 'products_org_name_index');
        $this->dropIndex('product_categories', 'product_categories_org_name_index');
        $this->dropIndex('invoices', 'invoices_org_status_date_index');
        $this->dropIndex('invoice_payments', 'invoice_payments_org_date_index');
        $this->dropIndex('purchase_orders', 'purchase_orders_org_status_date_index');
        $this->dropIndex('vendor_payments', 'vendor_payments_org_date_index');
        $this->dropIndex('stock_movements', 'stock_movements_org_date_index');
        $this->dropIndex('stock_movements', 'stock_movements_org_product_index');
        $this->dropIndex('leads', 'leads_org_status_index');
        $this->dropIndex('leads', 'leads_org_owner_index');
        $this->dropIndex('campaigns', 'campaigns_org_status_index');
        $this->dropIndex('hsn_codes', 'hsn_codes_org_code_index');
        $this->dropIndex('attribute_groups', 'attribute_groups_org_category_index');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
