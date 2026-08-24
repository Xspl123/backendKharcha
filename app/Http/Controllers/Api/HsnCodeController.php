<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HsnCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class HsnCodeController extends Controller
{
    public function index()
    {
        $hsnCodes = $this->scopeQuery()->get();
        return response()->json(['data' => $hsnCodes]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hsn_code' => 'required|string|max:20',
            'description' => 'required|string|max:255',
            'gst_rate' => 'required|numeric|min:0|max:100',
        ]);

        $validated['user_id'] = auth()->id();
        if ($this->usesOrgScope()) {
            $validated['org_id'] = auth()->user()->org_id;
        }

        $hsnCode = HsnCode::create($validated);

        return response()->json([
            'message' => 'HSN Code created successfully',
            'hsn_code' => $hsnCode
        ], 201);
    }

    public function show($id)
    {
        $hsnCode = $this->scopeQuery()->findOrFail($id);
        return response()->json(['data' => $hsnCode]);
    }

    public function update(Request $request, $id)
    {
        $hsnCode = $this->scopeQuery()->findOrFail($id);

        $validated = $request->validate([
            'hsn_code' => 'required|string|max:20',
            'description' => 'required|string|max:255',
            'gst_rate' => 'required|numeric|min:0|max:100',
        ]);

        $hsnCode->update($validated);

        return response()->json([
            'message' => 'HSN Code updated successfully',
            'hsn_code' => $hsnCode
        ]);
    }

    public function destroy($id)
    {
        $hsnCode = $this->scopeQuery()->findOrFail($id);
        $hsnCode->delete();

        return response()->json([
            'message' => 'HSN Code deleted successfully'
        ]);
    }

    private function scopeQuery(): Builder
    {
        $query = HsnCode::query();

        if ($this->usesOrgScope()) {
            return $query->where('org_id', auth()->user()->org_id);
        }

        return $query->where('user_id', auth()->id());
    }

    private function usesOrgScope(): bool
    {
        $user = auth()->user();

        return $user && $user->hasOrg() && Schema::hasColumn('hsn_codes', 'org_id');
    }
}
