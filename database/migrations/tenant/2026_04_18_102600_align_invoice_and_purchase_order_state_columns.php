<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            DB::statement("
                ALTER TABLE invoices
                MODIFY place_of_supply VARCHAR(100) NULL,
                MODIFY status ENUM('paid', 'unpaid', 'partial', 'cancelled', 'return') NOT NULL DEFAULT 'unpaid'
            ");
        }

        if (Schema::hasTable('purchase_orders')) {
            DB::statement("
                ALTER TABLE purchase_orders
                MODIFY place_of_supply VARCHAR(100) NULL,
                MODIFY status ENUM('pending', 'approved', 'received', 'cancelled', 'return') NOT NULL DEFAULT 'pending'
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            DB::statement("
                ALTER TABLE invoices
                MODIFY place_of_supply VARCHAR(2) NULL,
                MODIFY status ENUM('paid', 'unpaid', 'partial', 'cancelled') NOT NULL DEFAULT 'unpaid'
            ");
        }

        if (Schema::hasTable('purchase_orders')) {
            DB::statement("
                ALTER TABLE purchase_orders
                MODIFY place_of_supply VARCHAR(2) NULL,
                MODIFY status ENUM('pending', 'approved', 'received', 'cancelled', 'return') NOT NULL DEFAULT 'pending'
            ");
        }
    }
};
