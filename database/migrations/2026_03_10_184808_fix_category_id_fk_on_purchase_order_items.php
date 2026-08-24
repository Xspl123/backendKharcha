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
            // FK may already be absent on fresh installs.
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('purchase_order_items', 'category_id')) {
            return;
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->nullOnDelete();
        });
    }
};
