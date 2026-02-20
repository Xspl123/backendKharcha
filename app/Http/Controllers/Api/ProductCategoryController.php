<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;

class ProductCategoryController extends Controller
{
    public function __construct(
        private ProductCategoryRepositoryInterface $categoryRepo
    ) {}

    public function index()
    {
        return ProductCategoryResource::collection(
            $this->categoryRepo->getAll()
        );
    }

    public function store(StoreProductCategoryRequest $request)
    {
        $category = $this->categoryRepo->create($request->validated());
        return response()->json([
            'message' => 'Category created successfully',
            'data'    => new ProductCategoryResource($category),
        ], 201);
    }

    public function show(int $id)
    {
        return new ProductCategoryResource(
            $this->categoryRepo->getById($id)
        );
    }

    public function update(StoreProductCategoryRequest $request, int $id)
    {
        $category = $this->categoryRepo->update($id, $request->validated());
        return response()->json([
            'message' => 'Category updated successfully',
            'data'    => new ProductCategoryResource($category),
        ]);
    }

    public function destroy(int $id)
    {
        $this->categoryRepo->delete($id);
        return response()->json(['message' => 'Category deleted successfully']);
    }
}