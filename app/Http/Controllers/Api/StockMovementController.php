<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Repositories\Interfaces\StockMovementRepositoryInterface;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function __construct(
        private StockMovementRepositoryInterface $stockRepo
    ) {}

    // GET /api/stock-movements
    public function index(Request $request)
    {
        $movements = $this->stockRepo->getAll($request->only([
            'product_id', 'type', 'from_date', 'to_date'
        ]));
        return StockMovementResource::collection($movements);
    }

    // GET /api/stock-movements/report
    public function report(Request $request)
    {
        return response()->json([
            'data' => $this->stockRepo->getReport($request->only(['category_id']))
        ]);
    }

    // GET /api/stock-movements/by-product/{productId}
    public function byProduct(int $productId, Request $request)
    {
        $movements = $this->stockRepo->getByProduct(
            $productId,
            $request->only(['type', 'from_date', 'to_date'])
        );
        return StockMovementResource::collection($movements);
    }

    // POST /api/stock-movements
    public function store(StoreStockMovementRequest $request)
    {
        $movement = $this->stockRepo->create($request->validated());
        return response()->json([
            'message' => 'Stock movement recorded successfully',
            'data'    => new StockMovementResource($movement),
        ], 201);
    }

    // DELETE /api/stock-movements/{id}
    public function destroy(int $id)
    {
        $this->stockRepo->delete($id);
        return response()->json(['message' => 'Movement deleted and stock reversed']);
    }
}