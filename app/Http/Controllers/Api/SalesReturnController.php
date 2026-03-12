<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; 
use App\Http\Requests\StoreSalesReturnRequest;
use App\Http\Resources\SalesReturnResource;
use App\Repositories\Interfaces\SalesReturnRepositoryInterface;
use Illuminate\Http\Request;

class SalesReturnController extends Controller
{
    public function __construct(
        private SalesReturnRepositoryInterface $returnRepo
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $returns = $this->returnRepo->getAll($filters);
        
        return SalesReturnResource::collection($returns);
    }

    public function store(StoreSalesReturnRequest $request)
    {
        try {
            $return = $this->returnRepo->create($request->validated());
            
            return response()->json([
                'message' => 'Sales return processed successfully! Stock updated.',
                'data' => new SalesReturnResource($return)
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Sales return failed!',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(int $id)
    {
        try {
            $return = $this->returnRepo->find($id);
            return new SalesReturnResource($return);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Return not found'
            ], 404);
        }
    }

    public function getByInvoice(int $invoiceId)
    {
        $returns = $this->returnRepo->getByInvoice($invoiceId);
        return SalesReturnResource::collection($returns);
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
                'data' => new SalesReturnResource($return)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Status update failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}