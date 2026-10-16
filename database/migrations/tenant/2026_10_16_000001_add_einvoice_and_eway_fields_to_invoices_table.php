<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // E-Invoice (IRP) Fields
            if (!Schema::hasColumn('invoices', 'irn')) {
                $table->string('irn', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('invoices', 'ack_no')) {
                $table->string('ack_no', 50)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'ack_date')) {
                $table->dateTime('ack_date')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'signed_qr_data')) {
                $table->text('signed_qr_data')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'einvoice_status')) {
                $table->string('einvoice_status', 20)->default('not_generated');
            }

            // E-Way Bill (NIC) Fields
            if (!Schema::hasColumn('invoices', 'eway_bill_no')) {
                $table->string('eway_bill_no', 20)->nullable()->index();
            }
            if (!Schema::hasColumn('invoices', 'eway_bill_date')) {
                $table->dateTime('eway_bill_date')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'eway_valid_upto')) {
                $table->dateTime('eway_valid_upto')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'eway_status')) {
                $table->string('eway_status', 20)->default('not_generated');
            }

            // Transportation / Part-B Details
            if (!Schema::hasColumn('invoices', 'transporter_id')) {
                $table->string('transporter_id', 15)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'transporter_name')) {
                $table->string('transporter_name', 150)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'trans_doc_no')) {
                $table->string('trans_doc_no', 50)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'trans_doc_date')) {
                $table->date('trans_doc_date')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'trans_mode')) {
                $table->string('trans_mode', 10)->default('1');
            }
            if (!Schema::hasColumn('invoices', 'vehicle_no')) {
                $table->string('vehicle_no', 20)->nullable();
            }
            if (!Schema::hasColumn('invoices', 'vehicle_type')) {
                $table->string('vehicle_type', 5)->default('R');
            }
            if (!Schema::hasColumn('invoices', 'distance_km')) {
                $table->integer('distance_km')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $cols = [
                'irn', 'ack_no', 'ack_date', 'signed_qr_data', 'einvoice_status',
                'eway_bill_no', 'eway_bill_date', 'eway_valid_upto', 'eway_status',
                'transporter_id', 'transporter_name', 'trans_doc_no', 'trans_doc_date',
                'trans_mode', 'vehicle_no', 'vehicle_type', 'distance_km',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};