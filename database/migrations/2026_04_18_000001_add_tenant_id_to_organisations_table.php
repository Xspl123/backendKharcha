<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organisations')) {
            return;
        }

        Schema::table('organisations', function (Blueprint $table) {
            if (! Schema::hasColumn('organisations', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
                $table->unique('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organisations') || ! Schema::hasColumn('organisations', 'tenant_id')) {
            return;
        }

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique('organisations_tenant_id_unique');
            $table->dropColumn('tenant_id');
        });
    }
};
