<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (["invoice_items", "purchase_order_items"] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, "attributes")) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->json("attributes")->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (["invoice_items", "purchase_order_items"] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, "attributes")) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn("attributes");
                });
            }
        }
    }
};
