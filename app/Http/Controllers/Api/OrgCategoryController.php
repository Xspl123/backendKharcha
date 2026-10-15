<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrgCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = OrgCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name');

        if ($request->has('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('type', $request->type);
            return response()->json(['status' => 'success', 'data' => $query->get()]);
        }

        $all = $query->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'income'  => $all->where('type', 'income')->values(),
                'expense' => $all->where('type', 'expense')->values(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'type'  => 'required|in:income,expense',
            'color' => 'nullable|string|max:50',
            'icon'  => 'nullable|string|max:50',
        ]);

        $slug = Str::slug($validated['name'], '_');
        $count = OrgCategory::where('slug', 'like', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '_' . ($count + 1);
        }

        $category = OrgCategory::create([
            'user_id'    => auth()->id(),
            'org_id'     => auth()->user()?->org_id,
            'name'       => $validated['name'],
            'slug'       => $slug,
            'type'       => $validated['type'],
            'color'      => $validated['color'] ?? ($validated['type'] === 'income' ? '#10b981' : '#f59e0b'),
            'icon'       => $validated['icon'] ?? ($validated['type'] === 'income' ? '➕' : '📌'),
            'is_system'  => false,
            'is_active'  => true,
            'sort_order' => 100,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Custom category created successfully!',
            'data'    => $category,
        ], 201);
    }

    public function destroy($id)
    {
        $category = OrgCategory::findOrFail($id);

        if ($category->is_system) {
            return response()->json([
                'status'  => 'error',
                'message' => 'System default categories cannot be deleted.',
            ], 403);
        }

        $category->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Category deleted successfully.',
        ]);
    }
}