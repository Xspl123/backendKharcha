<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseReturnApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_return_api_handles_partial_return_correctly(): void
    {
        $role = Role::create([
            'name' => 'org_admin',
            'label' => 'Org Admin',
        ]);

        $permissions = Permission::query()->insert([
            [
                'name' => 'purchase_returns.create',
                'label' => 'Create Purchase Returns',
                'module' => 'purchase_returns',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'purchase_returns.view',
                'label' => 'View Purchase Returns',
                'module' => 'purchase_returns',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $role->permissions()->sync(Permission::query()->pluck('id'));

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'org_id' => 101,
            'user_type' => 'org_owner',
            'is_verified' => true,
        ]);

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'org_id' => $user->org_id,
            'vendor_name' => 'Vendor One',
            'company_name' => 'Vendor Co',
            'status' => 'active',
        ]);

        $product = Product::create([
            'user_id' => $user->id,
            'org_id' => $user->org_id,
            'name' => 'Widget',
            'unit' => 'pcs',
            'purchase_price' => 100,
            'selling_price' => 150,
            'tax_rate' => 18,
            'opening_stock' => 0,
            'current_stock' => 10,
            'avg_cost' => 100,
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'user_id' => $user->id,
            'org_id' => $user->org_id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-TEST-1001',
            'po_date' => '2026-04-02',
            'received_date' => '2026-04-02',
            'supply_type' => 'intra',
            'status' => 'received',
            'sub_total' => 1000,
            'total_amount' => 1000,
            'balance_amount' => 1000,
            'is_return' => false,
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'user_id' => $user->id,
            'org_id' => $user->org_id,
            'product_id' => $product->id,
            'item_name' => 'Widget',
            'qty' => 10,
            'unit' => 'pcs',
            'rate' => 100,
            'amount' => 1000,
            'tax_rate' => 18,
            'tax_amount' => 180,
            'returned_qty' => 0,
            'is_returned' => false,
            'is_return_item' => false,
        ]);

        VendorPayment::create([
            'user_id' => $user->id,
            'org_id' => $user->org_id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => $po->id,
            'amount' => 300,
            'payment_date' => '2026-04-02',
            'payment_method' => 'bank_transfer',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/purchase-returns', [
            'original_po_id' => $po->id,
            'return_date' => '2026-04-03',
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'product_id' => $product->id,
                'qty' => 4,
                'rate' => 100,
                'reason' => 'damaged',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.original_po.id', $po->id)
            ->assertJsonPath('data.items.0.original_item_id', $item->id)
            ->assertJsonPath('data.original_po.total_amount', 708.0)
            ->assertJsonPath('data.original_po.paid_amount', 300.0)
            ->assertJsonPath('data.original_po.balance_amount', 408.0)
            ->assertJsonPath('data.original_po.payment_summary.total_amount', 708.0)
            ->assertJsonPath('data.original_po.payment_summary.paid_amount', 300.0)
            ->assertJsonPath('data.original_po.payment_summary.balance_amount', 408.0);

        $this->assertEquals(4.0, $response->json('data.items.0.qty'));

        $item->refresh();
        $po->refresh();
        $product->refresh();

        $this->assertSame(4.0, (float) $item->returned_qty);
        $this->assertFalse((bool) $item->is_returned);
        $this->assertSame('received', $po->status);
        $this->assertSame(600.0, (float) $po->sub_total);
        $this->assertSame(708.0, (float) $po->total_amount);
        $this->assertSame(300.0, (float) $po->paid_amount);
        $this->assertSame(408.0, (float) $po->balance_amount);
        $this->assertSame(6.0, (float) $product->current_stock);

        $returnPO = PurchaseOrder::query()->where('is_return', true)->first();

        $this->assertNotNull($returnPO);
        $this->assertSame($po->id, $returnPO->original_po_id);

        $movement = StockMovement::query()
            ->where('reference_type', 'po_return')
            ->where('reference_id', $returnPO->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame('return_out', $movement->type);
        $this->assertSame(4.0, (float) $movement->qty);
        $this->assertSame(10.0, (float) $movement->stock_before);
        $this->assertSame(6.0, (float) $movement->stock_after);
    }
}
