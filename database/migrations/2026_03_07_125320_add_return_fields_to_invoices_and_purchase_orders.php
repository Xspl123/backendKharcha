<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'is_return')) {
                $table->boolean('is_return')->default(false);
            }

            if (!Schema::hasColumn('invoices', 'original_invoice_id')) {
                $table->unsignedBigInteger('original_invoice_id')->nullable()->after('is_return');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'is_return')) {
                $table->boolean('is_return')->default(false);
            }

            if (!Schema::hasColumn('purchase_orders', 'original_po_id')) {
                $table->unsignedBigInteger('original_po_id')->nullable()->after('is_return');
            }
        });

        // Invoice Items mein return tracking
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->boolean('is_returned')->default(false);
            $table->decimal('returned_qty', 10, 2)->default(0)->after('is_returned');
        });

        // PO Items mein return tracking
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->boolean('is_returned')->default(false);
            $table->decimal('returned_qty', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'is_return') || Schema::hasColumn('invoices', 'original_invoice_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn(['is_return', 'original_invoice_id']);
            });
        }

        if (Schema::hasColumn('purchase_orders', 'is_return') || Schema::hasColumn('purchase_orders', 'original_po_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn(['is_return', 'original_po_id']);
            });
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['is_returned', 'returned_qty']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['is_returned', 'returned_qty']);
        });
    }
};
