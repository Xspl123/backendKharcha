<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        

        // Invoice Items mein return tracking
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->boolean('is_returned')->default(false);
            $table->decimal('returned_qty', 10, 2)->default(0)->after('is_returned');
        });

        // PO Items mein return tracking
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('returned_qty', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['original_invoice_id']);
            $table->dropColumn(['is_return', 'original_invoice_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['original_po_id']);
            $table->dropColumn(['is_return', 'original_po_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['is_returned', 'returned_qty']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['is_returned', 'returned_qty']);
        });
    }
};