<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('vendor_invoice_no', 100)->nullable()->after('po_number');
            $table->date('vendor_invoice_date')->nullable()->after('vendor_invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['vendor_invoice_no', 'vendor_invoice_date']);
        });
    }
};