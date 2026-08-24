<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('org_id')->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['opening', 'purchase_in', 'sale_out', 'manual_in', 'manual_out', 'adjustment', 'return_in', 'return_out']);
            $table->decimal('qty', 10, 2);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('stock_before', 10, 2)->default(0);
            $table->decimal('stock_after', 10, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->date('movement_date');
            $table->timestamps();

            $table->index(['user_id', 'product_id']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['org_id', 'movement_date'], 'stock_movements_org_date_index');
            $table->index(['org_id', 'product_id'], 'stock_movements_org_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
