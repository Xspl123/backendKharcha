<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            // PO Info
            $table->string('po_number')->unique();
            $table->date('po_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('received_date')->nullable();

            // GST
            $table->enum('supply_type', ['intra', 'inter'])->default('intra');
            $table->string('place_of_supply', 2)->nullable();
            $table->boolean('is_reverse_charge')->default(false);

            // Amounts
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);

            // Status
            $table->enum('status', [
                'pending',
                'approved',
                'received',
                'cancelled',
                'return',
            ])->default('pending');

            // Extra
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'vendor_id']);
            $table->index(['user_id', 'po_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
