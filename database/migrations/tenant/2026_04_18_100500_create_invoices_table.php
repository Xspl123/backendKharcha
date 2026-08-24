<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('org_id')->nullable();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);
            $table->enum('status', ['paid', 'unpaid', 'partial', 'cancelled'])->default('unpaid');
            $table->string('invoice_type')->default('b2b');
            $table->enum('supply_type', ['intra', 'inter'])->default('intra');
            $table->string('place_of_supply', 2)->nullable();
            $table->boolean('is_reverse_charge')->default(false);
            $table->boolean('is_return')->default(false);
            $table->unsignedBigInteger('original_invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('client_id');
            $table->index('invoice_date');
            $table->index('status');
            $table->index(['org_id', 'status', 'invoice_date'], 'invoices_org_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
