<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HsnCode;

class HsnCodeController extends Controller
{
    public function index()
    {
        $hsnCodes = HsnCode::where('user_id', auth()->id())->get();
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

        $hsnCode = HsnCode::create($validated);

        return response()->json([
            'message' => 'HSN Code created successfully',
            'hsn_code' => $hsnCode
        ], 201);
    }

    public function show($id)
    {
        $hsnCode = HsnCode::where('user_id', auth()->id())->findOrFail($id);
        return response()->json(['data' => $hsnCode]);
    }

    public function update(Request $request, $id)
    {
        $hsnCode = HsnCode::where('user_id', auth()->id())->findOrFail($id);

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
        $hsnCode = HsnCode::where('user_id', auth()->id())->findOrFail($id);
        $hsnCode->delete();

        return response()->json([
            'message' => 'HSN Code deleted successfully'
        ]);
    }
}