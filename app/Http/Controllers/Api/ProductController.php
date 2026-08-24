<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductRepositoryInterface $productRepo
    ) {}

    // GET /api/products
    public function index(Request $request)
    {
        $products = $this->productRepo->getAll($request->only([
            'search', 'status', 'category_id', 'low_stock', 'out_of_stock', 'per_page'
        ]));
        return ProductResource::collection($products);
    }

    // GET /api/products/summary
    public function summary()
    {
        return response()->json([
            'data' => $this->productRepo->getSummary()
        ]);
    }

    // GET /api/products/low-stock
    public function lowStock()
    {
        return ProductResource::collection(
            $this->productRepo->getLowStock()
        );
    }

    // POST /api/products
    public function store(StoreProductRequest $request)
    {
        $product = $this->productRepo->create($request->validated());
        return response()->json([
            'message' => 'Product created successfully',
            'data'    => new ProductResource($product),
        ], 201);
    }

    // GET /api/products/{id}
    public function show(int $id)
    {
        return new ProductResource(
            $this->productRepo->getById($id)
        );
    }

    // PUT /api/products/{id}
    public function update(StoreProductRequest $request, int $id)
    {
        $product = $this->productRepo->update($id, $request->validated());
        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => new ProductResource($product),
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy(int $id)
    {
        $this->productRepo->delete($id);
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
