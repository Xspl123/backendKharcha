<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('expected_price', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_products');
    }
};
