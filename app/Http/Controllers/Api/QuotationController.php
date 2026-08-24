<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Repositories\Interfaces\QuotationRepositoryInterface;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function __construct(private QuotationRepositoryInterface $repo) {}

    public function index(Request $request)
    {
        $quotations = $this->repo->getAll($request->only([
            'search', 'status', 'lead_id', 'client_id', 'from_date', 'to_date', 'per_page',
        ]));

        return QuotationResource::collection($quotations);
    }

    public function store(StoreQuotationRequest $request)
    {
        $quotation = $this->repo->create($request->validated());

        return response()->json([
            'message' => 'Quotation created successfully.',
            'data' => new QuotationResource($quotation),
        ], 201);
    }

    public function storeFromLead(StoreQuotationRequest $request, int $leadId)
    {
        $payload = $request->validated();
        $payload['lead_id'] = $leadId;

        $quotation = $this->repo->create($payload);

        return response()->json([
            'message' => 'Quotation created from lead successfully.',
            'data' => new QuotationResource($quotation),
        ], 201);
    }

    public function show(int $id)
    {
        return new QuotationResource($this->repo->getById($id));
    }

    public function update(StoreQuotationRequest $request, int $id)
    {
        $quotation = $this->repo->update($id, $request->validated());

        return response()->json([
            'message' => 'Quotation updated successfully.',
            'data' => new QuotationResource($quotation),
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:draft,sent,approved,rejected,expired',
        ]);

        $quotation = $this->repo->updateStatus($id, $data['status']);

        return response()->json([
            'message' => 'Quotation status updated successfully.',
            'data' => new QuotationResource($quotation),
        ]);
    }

    public function destroy(int $id)
    {
        $this->repo->delete($id);

        return response()->json(['message' => 'Quotation deleted successfully.']);
    }
}
