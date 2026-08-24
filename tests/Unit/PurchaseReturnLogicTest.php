<?php

namespace Tests\Unit;

use App\Http\Resources\PurchaseReturnItemResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class PurchaseReturnLogicTest extends TestCase
{
    public function test_purchase_order_item_exposes_remaining_quantity_for_partial_return(): void
    {
        $item = new PurchaseOrderItem([
            'qty' => 10,
            'returned_qty' => 4,
            'is_returned' => false,
            'is_return_item' => false,
        ]);

        $this->assertSame(6.0, $item->remaining_qty);
        $this->assertFalse($item->is_returned);
    }

    public function test_purchase_order_can_return_only_when_original_items_have_remaining_qty(): void
    {
        $relation = new class
        {
            public array $calls = [];

            public function where(string $column, mixed $value): self
            {
                $this->calls[] = ['where', $column, $value];
                return $this;
            }

            public function whereRaw(string $sql): self
            {
                $this->calls[] = ['whereRaw', $sql];
                return $this;
            }

            public function exists(): bool
            {
                return true;
            }
        };

        $po = Mockery::mock(PurchaseOrder::class)->makePartial();
        $po->status = 'received';
        $po->shouldReceive('items')->andReturn($relation);

        $this->assertTrue($po->can_return);
        $this->assertSame([
            ['where', 'is_return_item', false],
            ['whereRaw', 'returned_qty < qty'],
        ], $relation->calls);
    }

    public function test_purchase_return_item_resource_returns_flat_item_payload(): void
    {
        $item = new PurchaseOrderItem();
        $item->forceFill([
            'product_id' => 7,
            'original_item_id' => 5,
            'item_name' => 'Widget',
            'description' => 'damaged batch',
            'hsn_code' => '1234',
            'qty' => 4,
            'unit' => 'pcs',
            'rate' => 100,
            'amount' => 400,
            'tax_rate' => 18,
            'tax_amount' => 72,
        ]);
        $item->id = 11;

        $resource = new PurchaseReturnItemResource($item);

        $payload = $resource->toArray(Request::create('/api/purchase-returns/1', 'GET'));

        $this->assertSame(11, $payload['id']);
        $this->assertSame(7, $payload['product_id']);
        $this->assertSame(5, $payload['original_item_id']);
        $this->assertSame(4.0, $payload['qty']);
        $this->assertSame(100.0, $payload['rate']);
        $this->assertSame(400.0, $payload['amount']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
