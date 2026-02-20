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
        Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('client_id')->constrained()->onDelete('cascade');
                $table->string('invoice_no')->unique();
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->decimal('sub_total', 12, 2)->default(0);
                $table->decimal('cgst', 12, 2)->default(0);
                $table->decimal('sgst', 12, 2)->default(0);
                $table->decimal('igst', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0); // NEW: track payments
                $table->decimal('balance_amount', 12, 2)->default(0); // NEW: remaining balance
                $table->enum('status', ['paid', 'unpaid', 'partial', 'cancelled'])->default('unpaid');
                $table->text('notes')->nullable();
                $table->timestamps();
                
                // Indexes
                $table->index('user_id');
                $table->index('client_id');
                $table->index('invoice_date');
                $table->index('status');
         });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
