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
        // FK drop karo
        $table->dropForeign(['category_id']);
        // Sirf nullable integer rakho — no FK
        $table->unsignedBigInteger('category_id')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('purchase_order_items', function (Blueprint $table) {
        $table->foreign('category_id')
              ->references('id')->on('categories')
              ->nullOnDelete();
    });
}
};
