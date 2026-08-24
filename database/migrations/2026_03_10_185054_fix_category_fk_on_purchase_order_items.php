<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_order_items', 'category_id')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE purchase_order_items DROP FOREIGN KEY purchase_order_items_category_id_foreign');
        } catch (\Throwable $e) {
            // FK may already be absent or already recreated.
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('product_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('purchase_order_items', 'category_id')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE purchase_order_items DROP FOREIGN KEY purchase_order_items_category_id_foreign');
        } catch (\Throwable $e) {
            // Ignore if FK is already absent.
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->nullOnDelete();
        });
    }
};
