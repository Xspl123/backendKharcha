<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'cheque',
                'upi',
                'other'
            ])->default('bank_transfer');

            $table->string('reference_no')->nullable(); 
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'vendor_id']);
            $table->index(['user_id', 'purchase_order_id']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};