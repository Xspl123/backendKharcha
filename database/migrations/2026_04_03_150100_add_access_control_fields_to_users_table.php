<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->after('password');
            }

            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role_id');
            }

            if (!Schema::hasColumn('users', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('users', 'invoice_prefix')) {
                $table->string('invoice_prefix')->nullable()->after('created_by');
            }

            if (!Schema::hasColumn('users', 'org_id')) {
                $table->unsignedBigInteger('org_id')->nullable()->after('invoice_prefix');
            }

            if (!Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type')->nullable()->after('org_id');
            }

            if (!Schema::hasColumn('users', 'org_name')) {
                $table->string('org_name')->nullable()->after('user_type');
            }

            if (!Schema::hasColumn('users', 'plan')) {
                $table->string('plan')->nullable()->after('org_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['role_id', 'is_active', 'created_by', 'invoice_prefix', 'org_id', 'user_type', 'org_name', 'plan'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
