<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('quotation_no');
            $table->unsignedBigInteger('parent_quotation_id')->nullable()->after('version');

            $table->foreign('parent_quotation_id')->references('id')->on('quotations')->nullOnDelete();
            $table->index('parent_quotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['parent_quotation_id']);
            $table->dropColumn(['version', 'parent_quotation_id']);
        });
    }
};