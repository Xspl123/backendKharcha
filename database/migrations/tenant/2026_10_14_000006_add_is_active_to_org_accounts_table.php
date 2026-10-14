<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('org_accounts', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('current_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('org_accounts', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};