<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('org_transactions') && !Schema::hasColumn('org_transactions', 'voucher_no')) {
            Schema::table('org_transactions', function (Blueprint $table) {
                $table->string('voucher_no', 50)->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('org_transactions') && Schema::hasColumn('org_transactions', 'voucher_no')) {
            Schema::table('org_transactions', function (Blueprint $table) {
                $table->dropIndex(['voucher_no']);
                $table->dropColumn('voucher_no');
            });
        }
    }
};