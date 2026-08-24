<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $poRepo
    ) {}

    // GET /api/purchase-orders
    public function index(Request $request)
    {
        $orders = $this->poRepo->getAll($request->only([
            'status', 'vendor_id', 'from_date', 'to_date', 'search', 'per_page'
        ]));

        return PurchaseOrderResource::collection($orders);
    }

    // GET /api/purchase-orders/summary
    public function summary()
    {
        return response()->json([
            'data' => $this->poRepo->getSummary()
        ]);
    }

    // POST /api/purchase-orders
    public function store(StorePurchaseOrderRequest $request)
    {
        $po = $this->poRepo->create($request->validated());

        return response()->json([
            'message' => 'Purchase Order created successfully',
            'data'    => new PurchaseOrderResource($po),
        ], 201);
    }

    // GET /api/purchase-orders/{id}
    public function show(int $id)
    {
        $po = $this->poRepo->getById($id);

        return new PurchaseOrderResource($po);
    }

    // PUT /api/purchase-orders/{id}
    public function update(StorePurchaseOrderRequest $request, int $id)
    {
        $po = $this->poRepo->update($id, $request->validated());

        return response()->json([
            'message' => 'Purchase Order updated successfully',
            'data'    => new PurchaseOrderResource($po),
        ]);
    }

    // DELETE /api/purchase-orders/{id}
    public function destroy(int $id)
    {
        $this->poRepo->delete($id);

        return response()->json([
            'message' => 'Purchase Order deleted successfully',
        ]);
    }

    // POST /api/purchase-orders/{id}/status
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:approved,received,cancelled',
        ]);

        $po = $this->poRepo->updateStatus($id, $request->status);

        return response()->json([
            'message' => "Purchase Order marked as {$request->status}",
            'data'    => new PurchaseOrderResource($po),
        ]);
    }
}
