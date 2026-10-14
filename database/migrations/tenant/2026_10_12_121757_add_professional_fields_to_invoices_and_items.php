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
        // Invoices table me:
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('status')->default('draft')->change(); // draft, unpaid, partial, paid, cancelled, overdue
                $table->decimal('discount_percent', 5, 2)->default(0)->after('sub_total');
                $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percent');
                $table->decimal('shipping_charges', 12, 2)->default(0)->after('discount_amount');
                $table->decimal('round_off', 6, 2)->default(0)->after('igst'); // e.g. +0.45 ya -0.35
                $table->string('payment_terms')->nullable()->after('due_date'); // 'immediate', 'net_15', 'net_30', 'net_60'
                $table->string('vehicle_no')->nullable()->after('notes'); // E-way / Transport
                $table->string('transporter_name')->nullable()->after('vehicle_no');
                $table->text('terms_and_conditions')->nullable()->after('notes');
            });

            // Invoice items table me:
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('rate');
                $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percent');
                $table->decimal('taxable_amount', 12, 2)->default(0)->after('discount_amount'); // (qty * rate) - discount
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            //
        });
    }
};
