<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('account_id')->index();

            $table->date('transaction_date');
            $table->enum('type', ['credit', 'debit']); // credit = Money In (+), debit = Money Out (-)
            $table->string('category', 100); // client_payment, vendor_payment, expense, capital, transfer
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2)->default(0); // Running live balance

            $table->string('payment_method', 50)->default('bank_transfer');
            $table->string('reference_no')->nullable();
            $table->text('description')->nullable();

            // Auto-links
            $table->unsignedBigInteger('invoice_payment_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_payment_id')->nullable()->index();
            $table->unsignedBigInteger('transfer_account_id')->nullable()->index(); // Contra transfer

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'transaction_date']);
            $table->index(['org_id', 'transaction_date']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_transactions');
    }
};