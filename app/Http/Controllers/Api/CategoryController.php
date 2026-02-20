<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreCategoryRequest;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getUserCategories(
            $request->user()->id,
            $request->query('search'),
            $request->query('page', 1),
            $request->query('limit', 10000)
        );
        
        return response()->json($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['user_id'] = Auth::id();
        $data['type'] = $data['type'] ?? 'Expense';

        $category = $this->categoryService->createCategory($data);
        
        return response()->json(['message','Category Create successfully',$category], 201);
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById(Auth::id(), $id);
        return $category ? response()->json($category) : response()->json(['message' => 'Not Found'], 404);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable',
            'description' => 'sometimes|string|nullable',
        ]);

        $updated = $this->categoryService->updateCategory(Auth::id(), $id, $data);
        
        return $updated ? response()->json(['message' => 'Category Updated Successfully']) : response()->json(['message' => 'Not Found'], 404);
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->categoryService->deleteCategory(Auth::id(), $id)
            ? response()->json(['message' => 'Deleted'])
            : response()->json(['message' => 'Not Found'], 404);
    }
}
