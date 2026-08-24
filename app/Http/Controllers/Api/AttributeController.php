<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeGroup;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttributeController extends Controller
{
    // ══ ATTRIBUTE GROUPS ══════════════════════════════════

    // GET /api/attribute-groups
    public function indexGroups(Request $request)
    {
        $query = $this->groupsQuery()
            ->with(['attributes' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('category_id', $request->category_id)
                  ->orWhereNull('category_id'); // General groups bhi load karo
            });
        }

        return response()->json(['data' => $query->get()]);
    }

    // POST /api/attribute-groups
    public function storeGroup(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
            'attributes'  => 'nullable|array',
            'attributes.*.name'        => 'required|string|max:255',
            'attributes.*.type'        => 'required|in:text,number,select,boolean',
            'attributes.*.unit'        => 'nullable|string|max:50',
            'attributes.*.options'     => 'nullable|array',
            'attributes.*.is_required' => 'nullable|boolean',
            'attributes.*.sort_order'  => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($data) {
            $categoryId = $this->validateCategoryId($data['category_id'] ?? null);

            $group = AttributeGroup::create([
                'user_id'     => auth()->id(),
                'org_id'      => $this->usesOrgScope() ? auth()->user()->org_id : null,
                'category_id' => $categoryId,
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'sort_order'  => $data['sort_order'] ?? 0,
            ]);

            // Attributes bhi create karo agar diye hain
            if (!empty($data['attributes'])) {
                foreach ($data['attributes'] as $i => $attr) {
                    $group->attributes()->create([
                        'name'        => $attr['name'],
                        'type'        => $attr['type'],
                        'unit'        => $attr['unit']        ?? null,
                        'options'     => $attr['options']     ?? null,
                        'is_required' => $attr['is_required'] ?? false,
                        'sort_order'  => $attr['sort_order']  ?? $i,
                    ]);
                }
            }

            return response()->json([
                'data'    => $group->load('attributes'),
                'message' => 'Attribute group created!',
            ], 201);
        });
    }

    // PUT /api/attribute-groups/{id}
    public function updateGroup(Request $request, int $id)
    {
        $group = $this->groupsQuery()->findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
        ]);

        $group->update([
            ...$data,
            'category_id' => $this->validateCategoryId($data['category_id'] ?? null),
        ]);

        return response()->json([
            'data'    => $group->load('attributes'),
            'message' => 'Group updated!',
        ]);
    }

    // DELETE /api/attribute-groups/{id}
    public function destroyGroup(int $id)
    {
        $group = $this->groupsQuery()->findOrFail($id);
        $group->delete(); // Cascade — attributes bhi delete honge
        return response()->json(['message' => 'Group deleted!']);
    }

    // ══ ATTRIBUTES ════════════════════════════════════════

    // POST /api/attribute-groups/{groupId}/attributes
    public function storeAttribute(Request $request, int $groupId)
    {
        $group = $this->groupsQuery()->findOrFail($groupId);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:text,number,select,boolean',
            'unit'        => 'nullable|string|max:50',
            'options'     => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer',
        ]);

        $attr = $group->attributes()->create($data);

        return response()->json(['data' => $attr, 'message' => 'Attribute added!'], 201);
    }

    // PUT /api/attributes/{id}
    public function updateAttribute(Request $request, int $id)
    {
        $attr = $this->attributesQuery()->findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:text,number,select,boolean',
            'unit'        => 'nullable|string|max:50',
            'options'     => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer',
        ]);

        $attr->update($data);
        return response()->json(['data' => $attr, 'message' => 'Attribute updated!']);
    }

    // DELETE /api/attributes/{id}
    public function destroyAttribute(int $id)
    {
        $attr = $this->attributesQuery()->findOrFail($id);

        $attr->delete();
        return response()->json(['message' => 'Attribute deleted!']);
    }

    // ══ PRODUCT ATTRIBUTE VALUES ══════════════════════════

    // GET /api/products/{productId}/attributes
    public function getProductAttributes(int $productId)
    {
        $this->productsQuery()->findOrFail($productId);

        $values = ProductAttribute::where('product_id', $productId)
            ->with('attribute.group')
            ->whereHas('attribute.group', fn ($q) => $this->applyGroupScope($q))
            ->get()
            ->map(fn($v) => [
                'id'             => $v->id,
                'attribute_id'   => $v->attribute_id,
                'attribute_name' => $v->attribute->name,
                'attribute_type' => $v->attribute->type,
                'attribute_unit' => $v->attribute->unit,
                'options'        => $v->attribute->options,
                'group_name'     => $v->attribute->group->name,
                'group_id'       => $v->attribute->group->id,
                'value'          => $v->value,
            ]);

        return response()->json(['data' => $values]);
    }

    // POST /api/products/{productId}/attributes — bulk save
    public function saveProductAttributes(Request $request, int $productId)
    {
        $request->validate([
            'attributes'                => 'required|array',
            'attributes.*.attribute_id' => 'required|integer',
            'attributes.*.value'        => 'nullable|string',
        ]);

        $this->productsQuery()->findOrFail($productId);

        DB::transaction(function () use ($request, $productId) {
            foreach ($request->attributes as $item) {
                $attribute = $this->attributesQuery()->findOrFail($item['attribute_id']);

                ProductAttribute::updateOrCreate(
                    [
                        'product_id'   => $productId,
                        'attribute_id' => $attribute->id,
                    ],
                    ['value' => $item['value'] ?? null]
                );
            }
        });

        return response()->json(['message' => 'Product attributes saved!']);
    }

    private function groupsQuery(): Builder
    {
        $query = AttributeGroup::query();

        $this->applyGroupScope($query);

        return $query;
    }

    private function attributesQuery(): Builder
    {
        return Attribute::whereHas('group', fn ($q) => $this->applyGroupScope($q));
    }

    private function productsQuery(): Builder
    {
        $query = Product::query();

        if (auth()->user()->hasOrg() && Schema::hasColumn('products', 'org_id')) {
            return $query->where('org_id', auth()->user()->org_id);
        }

        return $query->where('user_id', auth()->id());
    }

    private function validateCategoryId(?int $categoryId): ?int
    {
        if (is_null($categoryId)) {
            return null;
        }

        $query = \App\Models\ProductCategory::query()->where('id', $categoryId);

        if (auth()->user()->hasOrg() && Schema::hasColumn('product_categories', 'org_id')) {
            $query->where('org_id', auth()->user()->org_id);
        } else {
            $query->where('user_id', auth()->id());
        }

        abort_unless($query->exists(), 422, 'Selected category is invalid for your scope.');

        return $categoryId;
    }

    private function applyGroupScope($query): void
    {
        if ($this->usesOrgScope()) {
            $query->where('org_id', auth()->user()->org_id);
            return;
        }

        $query->where('user_id', auth()->id());
    }

    private function usesOrgScope(): bool
    {
        $user = auth()->user();

        return $user && $user->hasOrg() && Schema::hasColumn('attribute_groups', 'org_id');
    }
}
