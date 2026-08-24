<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('hsn_code')->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->string('unit')->nullable()->default('pcs');
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->boolean('is_returned')->default(false);
            $table->decimal('returned_qty', 10, 2)->default(0);
            $table->unsignedBigInteger('original_item_id')->nullable();
            $table->boolean('is_return_item')->default(false);
            $table->timestamps();

            $table->index(['original_item_id', 'is_return_item'], 'idx_return_items');
            $table->index(['purchase_order_id', 'product_id'], 'idx_po_product');
            $table->index(['purchase_order_id', 'is_return_item'], 'idx_po_return_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
