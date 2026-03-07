<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Invoice items ko inventory products se link karta hai.
     * Jab product select hota hai invoice form mein,
     * product_id save hota hai — taaki stock deduction ho sake.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // product_id nullable hai — manually type kiya item bhi valid hai
            $table->unsignedBigInteger('product_id')->nullable()->after('invoice_id');

            // Foreign key — products table se link (soft delete compatible)
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('set null');

            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};