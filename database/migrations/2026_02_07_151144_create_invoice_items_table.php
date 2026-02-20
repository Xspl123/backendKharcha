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
        Schema::create('invoice_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
        $table->string('item_name');
        $table->text('description')->nullable(); // NEW: item description
        $table->string('hsn_code')->nullable(); // NEW: for GST
        $table->decimal('qty', 10, 2);
        $table->string('unit')->default('pcs'); // NEW: kg, pcs, hrs, etc
        $table->decimal('rate', 10, 2);
        $table->decimal('amount', 12, 2);
        $table->decimal('tax_rate', 5, 2)->default(0); // NEW: 18%, 12%, etc
        $table->decimal('tax_amount', 12, 2)->default(0); // NEW: calculated tax
        $table->timestamps();
        
        $table->index('invoice_id');
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
