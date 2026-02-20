<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()
                  ->constrained()->nullOnDelete();

            // Identity
            $table->string('name');
            $table->string('sku')->nullable(); 
            $table->string('hsn_code')->nullable(); 
            $table->text('description')->nullable();

            // Unit & Pricing
            $table->string('unit')->default('pcs'); 
            $table->decimal('purchase_price', 12, 2)->default(0); 
            $table->decimal('selling_price', 12, 2)->default(0);  
            $table->decimal('tax_rate', 5, 2)->default(0);         

            // Stock
            $table->decimal('opening_stock', 10, 2)->default(0);
            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('low_stock_alert', 10, 2)->default(0);
            $table->decimal('avg_cost', 12, 2)->default(0);

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'product_category_id']);
            $table->unique(['user_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};