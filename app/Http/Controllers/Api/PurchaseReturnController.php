<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Http\Resources\PurchaseReturnResource;
use App\Repositories\Interfaces\PurchaseReturnRepositoryInterface;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private PurchaseReturnRepositoryInterface $returnRepo
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'vendor_id' => 'nullable|exists:vendors,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $returns = $this->returnRepo->getAll($filters);
        
        return PurchaseReturnResource::collection($returns);
    }

    public function store(StorePurchaseReturnRequest $request)
    {
        try {
            $return = $this->returnRepo->create($request->validated());
            
            return response()->json([
                'message' => 'Purchase return processed successfully! Stock updated.',
                'data' => new PurchaseReturnResource($return)
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Purchase return failed!',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(int $id)
    {
        try {
            $return = $this->returnRepo->find($id);
            return new PurchaseReturnResource($return);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Return not found'
            ], 404);
        }
    }

    public function getByPO(int $poId)
    {
        $returns = $this->returnRepo->getByPO($poId);
        return PurchaseReturnResource::collection($returns);
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:completed,cancelled,processing'
        ]);

        try {
            $return = $this->returnRepo->updateStatus($id, $request->status);
            
            return response()->json([
                'message' => 'Return status updated successfully',
                'data' => new PurchaseReturnResource($return)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Status update failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}