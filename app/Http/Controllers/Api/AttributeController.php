<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeGroup;
use App\Models\Attribute;
use App\Models\ProductAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttributeController extends Controller
{
    // ══ ATTRIBUTE GROUPS ══════════════════════════════════

    // GET /api/attribute-groups
    public function indexGroups(Request $request)
    {
        $query = AttributeGroup::where('user_id', auth()->id())
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
            'category_id' => 'nullable|exists:product_categories,id',
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
            $group = AttributeGroup::create([
                'user_id'     => auth()->id(),
                'category_id' => $data['category_id'] ?? null,
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
        $group = AttributeGroup::where('user_id', auth()->id())->findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'nullable|exists:product_categories,id',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
        ]);

        $group->update($data);

        return response()->json([
            'data'    => $group->load('attributes'),
            'message' => 'Group updated!',
        ]);
    }

    // DELETE /api/attribute-groups/{id}
    public function destroyGroup(int $id)
    {
        $group = AttributeGroup::where('user_id', auth()->id())->findOrFail($id);
        $group->delete(); // Cascade — attributes bhi delete honge
        return response()->json(['message' => 'Group deleted!']);
    }

    // ══ ATTRIBUTES ════════════════════════════════════════

    // POST /api/attribute-groups/{groupId}/attributes
    public function storeAttribute(Request $request, int $groupId)
    {
        $group = AttributeGroup::where('user_id', auth()->id())->findOrFail($groupId);

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
        $attr = Attribute::whereHas('group', fn($q) =>
            $q->where('user_id', auth()->id())
        )->findOrFail($id);

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
        $attr = Attribute::whereHas('group', fn($q) =>
            $q->where('user_id', auth()->id())
        )->findOrFail($id);

        $attr->delete();
        return response()->json(['message' => 'Attribute deleted!']);
    }

    // ══ PRODUCT ATTRIBUTE VALUES ══════════════════════════

    // GET /api/products/{productId}/attributes
    public function getProductAttributes(int $productId)
    {
        $values = ProductAttribute::where('product_id', $productId)
            ->with('attribute.group')
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
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
            'attributes.*.value'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $productId) {
            foreach ($request->attributes as $item) {
                ProductAttribute::updateOrCreate(
                    [
                        'product_id'   => $productId,
                        'attribute_id' => $item['attribute_id'],
                    ],
                    ['value' => $item['value'] ?? null]
                );
            }
        });

        return response()->json(['message' => 'Product attributes saved!']);
    }
}