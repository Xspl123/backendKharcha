<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'account_type')) {
                $table->string('account_type')->default('cash')->after('account_name');
            }

            if (!Schema::hasColumn('accounts', 'credit_limit')) {
                $table->decimal('credit_limit', 18, 2)->nullable()->after('account_balance');
            }

            if (!Schema::hasColumn('accounts', 'billing_cycle_day')) {
                $table->unsignedTinyInteger('billing_cycle_day')->nullable()->after('credit_limit');
            }

            if (!Schema::hasColumn('accounts', 'payment_due_day')) {
                $table->unsignedTinyInteger('payment_due_day')->nullable()->after('billing_cycle_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $columns = ['payment_due_day', 'billing_cycle_day', 'credit_limit', 'account_type'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
