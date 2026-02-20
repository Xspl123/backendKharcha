<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // b2b = client ke paas GSTIN hai
            // b2cs = no GSTIN, amount < 2.5L (intra-state)
            // b2cl = no GSTIN, amount >= 2.5L (inter-state)
            // export = export invoice
            $table->enum('invoice_type', ['b2b', 'b2cs', 'b2cl', 'export'])
                  ->default('b2b')
                  ->after('notes');

            // intra = same state → CGST + SGST
            // inter = different state → IGST
            $table->enum('supply_type', ['intra', 'inter'])
                  ->default('intra')
                  ->after('invoice_type');

            // 2-digit Indian state code: '27' = Maharashtra, '07' = Delhi
            $table->string('place_of_supply', 2)
                  ->nullable()
                  ->after('supply_type');

            // Reverse Charge Mechanism
            $table->boolean('is_reverse_charge')
                  ->default(false)
                  ->after('place_of_supply');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_type',
                'supply_type',
                'place_of_supply',
                'is_reverse_charge',
            ]);
        });
    }
};