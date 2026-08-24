<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReturnTrackingColumnsToPurchaseOrderItems extends Migration
{
    public function up()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // ✅ Add user_id
            if (!Schema::hasColumn('purchase_order_items', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            
            // ✅ Add org_id
            if (!Schema::hasColumn('purchase_order_items', 'org_id')) {
                $table->unsignedBigInteger('org_id')->nullable()->after('user_id');
            }
            
            // ✅ Add original_item_id
            if (!Schema::hasColumn('purchase_order_items', 'original_item_id')) {
                $afterColumn = Schema::hasColumn('purchase_order_items', 'is_returned') ? 'is_returned' : 'returned_qty';
                $table->unsignedBigInteger('original_item_id')->nullable()->after($afterColumn);
            }
            
            // ✅ Add is_return_item flag
            if (!Schema::hasColumn('purchase_order_items', 'is_return_item')) {
                $table->boolean('is_return_item')->default(false)->after('original_item_id');
            }
            
            // ✅ Add indexes
            $table->index(['original_item_id', 'is_return_item'], 'idx_return_items');
            $table->index(['purchase_order_id', 'product_id'], 'idx_po_product');
            $table->index(['purchase_order_id', 'is_return_item'], 'idx_po_return_items');
        });
    }

    public function down()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex('idx_return_items');
            $table->dropIndex('idx_po_product');
            $table->dropIndex('idx_po_return_items');
            
            $table->dropColumn([
                'user_id',
                'org_id',
                'original_item_id',
                'is_return_item'
            ]);
        });
    }
}
