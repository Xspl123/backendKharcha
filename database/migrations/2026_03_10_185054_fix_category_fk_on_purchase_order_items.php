<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('purchase_order_items', function (Blueprint $table) {
        // ❌ Purani FK drop karo (categories table thi)
        $table->dropForeign(['category_id']);
        
        // ✅ Sahi table pe FK lagao
        $table->foreign('category_id')
              ->references('id')
              ->on('product_categories')
              ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('purchase_order_items', function (Blueprint $table) {
        $table->dropForeign(['category_id']);
        $table->foreign('category_id')
              ->references('id')->on('categories')
              ->nullOnDelete();
    });
}
};
