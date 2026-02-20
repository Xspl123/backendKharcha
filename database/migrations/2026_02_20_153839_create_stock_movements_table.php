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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Movement Type
            $table->enum('type', [
                'opening',       // Opening stock entry
                'purchase_in',   // Stock in from Purchase Order
                'sale_out',      // Stock out from Invoice/Sale
                'manual_in',     // Manual stock addition
                'manual_out',    // Manual stock removal
                'adjustment',    // Stock adjustment/correction
                'return_in',     // Purchase return received
                'return_out',    // Sale return given
            ]);

            $table->decimal('qty', 10, 2);           // +ve = in, -ve = out
            $table->decimal('rate', 12, 2)->default(0);  // Rate at which movement happened
            $table->decimal('value', 12, 2)->default(0); // qty * rate

            // Stock before & after
            $table->decimal('stock_before', 10, 2)->default(0);
            $table->decimal('stock_after', 10, 2)->default(0);

            // Reference
            $table->string('reference_type')->nullable();  // purchase_order, invoice etc
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no')->nullable();    // PO number / Invoice number
            $table->text('notes')->nullable();

            $table->date('movement_date');
            $table->timestamps();

            $table->index(['user_id', 'product_id']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};