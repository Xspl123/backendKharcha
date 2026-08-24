<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Resources\VendorResource;
use App\Repositories\Interfaces\VendorRepositoryInterface;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct(
        private VendorRepositoryInterface $vendorRepo
    ) {}

    // GET /api/vendors
    public function index(Request $request)
    {
        $vendors = $this->vendorRepo->getAll($request->only([
            'search', 'status', 'per_page'
        ]));

        return VendorResource::collection($vendors);
    }

    // GET /api/vendors/summary
    public function summary()
    {
        return response()->json([
            'data' => $this->vendorRepo->getSummary()
        ]);
    }

    // POST /api/vendors
    public function store(StoreVendorRequest $request)
    {
        $vendor = $this->vendorRepo->create($request->validated());

        return response()->json([
            'message' => 'Vendor created successfully',
            'data'    => new VendorResource($vendor),
        ], 201);
    }

    // GET /api/vendors/{id}
    public function show(int $id)
    {
        $vendor = $this->vendorRepo->getById($id);

        return new VendorResource($vendor);
    }

    // PUT /api/vendors/{id}
    public function update(StoreVendorRequest $request, int $id)
    {
        $vendor = $this->vendorRepo->update($id, $request->validated());

        return response()->json([
            'message' => 'Vendor updated successfully',
            'data'    => new VendorResource($vendor),
        ]);
    }

    // DELETE /api/vendors/{id}
    public function destroy(int $id)
    {
        $this->vendorRepo->delete($id);

        return response()->json([
            'message' => 'Vendor deleted successfully',
        ]);
    }
}
