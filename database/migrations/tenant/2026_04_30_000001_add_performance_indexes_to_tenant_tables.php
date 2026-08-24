<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('purchase_orders', ['org_id', 'is_return', 'po_date'], 'po_org_return_date_idx');
        $this->addIndex('purchase_orders', ['org_id', 'vendor_id', 'po_date'], 'po_org_vendor_date_idx');
        $this->addIndex('purchase_orders', ['org_id', 'is_return', 'status'], 'po_org_return_status_idx');

        $this->addIndex('vendors', ['org_id', 'created_at'], 'vendors_org_created_idx');
        $this->addIndex('products', ['org_id', 'product_category_id', 'name'], 'products_org_cat_name_idx');

        $this->addIndex('stock_movements', ['org_id', 'type', 'reference_type'], 'stock_org_type_ref_idx');
        $this->addIndex('stock_movements', ['org_id', 'product_id', 'movement_date'], 'stock_org_product_date_idx');

        $this->addIndex('leads', ['org_id', 'status', 'created_at'], 'leads_org_status_created_idx');
        $this->addIndex('leads', ['org_id', 'owner_id', 'created_at'], 'leads_org_owner_created_idx');
        $this->addIndex('lead_follow_ups', ['is_done', 'due_date', 'lead_id'], 'followups_done_due_lead_idx');

        $this->addIndex('vendor_payments', ['org_id', 'vendor_id', 'payment_date'], 'vendor_pay_org_vendor_date_idx');
        $this->addIndex('vendor_payments', ['org_id', 'purchase_order_id', 'payment_date'], 'vendor_pay_org_po_date_idx');
    }

    public function down(): void
    {
        $this->dropIndex('vendor_payments', 'vendor_pay_org_po_date_idx');
        $this->dropIndex('vendor_payments', 'vendor_pay_org_vendor_date_idx');

        $this->dropIndex('lead_follow_ups', 'followups_done_due_lead_idx');
        $this->dropIndex('leads', 'leads_org_owner_created_idx');
        $this->dropIndex('leads', 'leads_org_status_created_idx');

        $this->dropIndex('stock_movements', 'stock_org_product_date_idx');
        $this->dropIndex('stock_movements', 'stock_org_type_ref_idx');

        $this->dropIndex('products', 'products_org_cat_name_idx');
        $this->dropIndex('vendors', 'vendors_org_created_idx');

        $this->dropIndex('purchase_orders', 'po_org_return_status_idx');
        $this->dropIndex('purchase_orders', 'po_org_vendor_date_idx');
        $this->dropIndex('purchase_orders', 'po_org_return_date_idx');
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
