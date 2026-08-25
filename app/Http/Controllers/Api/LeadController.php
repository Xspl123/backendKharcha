<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Requests\UpdateLeadStatusRequest;
use App\Http\Requests\StoreLeadActivityRequest;
use App\Http\Requests\StoreLeadFollowUpRequest;
use App\Http\Resources\LeadResource;
use App\Http\Resources\LeadActivityResource;
use App\Http\Resources\LeadFollowUpResource;
use App\Http\Resources\LeadProductResource;
use App\Repositories\Interfaces\LeadRepositoryInterface;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(private LeadRepositoryInterface $repo) {}

    // GET /api/leads
    public function index(Request $request)
    {
        $this->checkPermission('leads.view', $request);
        $leads = $this->repo->getAll($request->only([
            'search', 'status', 'owner_id', 'source', 'country',
            'date_from', 'date_to', 'per_page',
        ]));
        return response()->json(['data' => LeadResource::collection($leads)]);
    }

    // GET /api/leads/summary
    public function summary(Request $request)
    {
        $this->checkPermission('leads.view', $request);
        return response()->json(['data' => $this->repo->getSummary()]);
    }

    // GET /api/leads/pipeline
    public function pipeline(Request $request)
    {
        $this->checkPermission('leads.view', $request);
        return response()->json(['data' => $this->repo->getPipelineStats()]);
    }

    // GET /api/leads/due-followups
    // Lightweight, polled-frequently endpoint for the Navbar reminder bell —
    // returns only due-today/overdue open follow-ups, not full lead payloads.
    public function dueFollowUps(Request $request)
    {
        $this->checkPermission('leads.view', $request);
        return response()->json(['data' => $this->repo->getDueFollowUps()]);
    }

    // POST /api/leads
    public function store(StoreLeadRequest $request)
    {
        $this->checkPermission('leads.create', $request);
        $lead = $this->repo->create($request->validated());
        return response()->json([
            'message' => 'Lead created successfully.',
            'data'    => new LeadResource($lead),
        ], 201);
    }

    // GET /api/leads/{id}
    public function show($id, Request $request) 
    {
        $this->checkPermission('leads.view', $request);
        
        // Enhanced validation with logging
        if (!is_numeric($id) || $id <= 0) {
            \Log::warning('Invalid lead ID attempted:', [
                'id' => $id,
                'type' => gettype($id),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Invalid lead ID. ID must be a positive number.',
                'provided_id' => $id
            ], 400);
        }
        
        try {
            $lead = $this->repo->findById((int) $id);
            return response()->json(['data' => new LeadResource($lead)]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Lead not found'
            ], 404);
        }
    }

    // PUT /api/leads/{id}
    public function update(UpdateLeadRequest $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);
        $lead = $this->repo->update($id, $request->validated());
        return response()->json([
            'message' => 'Lead updated successfully.',
            'data'    => new LeadResource($lead),
        ]);
    }

    // DELETE /api/leads/{id}
    public function destroy(Request $request, int $id)
    {
        $this->checkPermission('leads.delete', $request);
        $this->repo->delete($id);
        return response()->json(['message' => 'Lead deleted successfully.']);
    }

    // PATCH /api/leads/{id}/status
    public function updateStatus(UpdateLeadStatusRequest $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);
        $lead = $this->repo->updateStatus(
            $id,
            $request->status,
            $request->lost_reason
        );
        return response()->json([
            'message' => 'Status updated successfully.',
            'data'    => new LeadResource($lead),
        ]);
    }

    // POST /api/leads/{id}/activities
    public function addActivity(StoreLeadActivityRequest $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);
        $activity = $this->repo->addActivity($id, $request->validated());
        $activity->load('user');
        return response()->json([
            'message' => 'Activity logged.',
            'data'    => new LeadActivityResource($activity),
        ], 201);
    }

    // POST /api/leads/{id}/follow-ups
    public function addFollowUp(StoreLeadFollowUpRequest $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);
        $followUp = $this->repo->addFollowUp($id, $request->validated());
        $followUp->load('user');
        return response()->json([
            'message' => 'Follow-up scheduled.',
            'data'    => new LeadFollowUpResource($followUp),
        ], 201);
    }

    public function products(Request $request, int $id)
    {
        $this->checkPermission('leads.view', $request);

        return response()->json([
            'data' => LeadProductResource::collection($this->repo->getLeadProducts($id)),
        ]);
    }

    public function addProduct(Request $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);

        $data = $request->validate([
            'product_id' => 'required|integer|min:1',
            'quantity' => 'nullable|numeric|min:0.01',
            'expected_price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $leadProduct = $this->repo->addLeadProduct($id, $data);

        return response()->json([
            'message' => 'Lead product added successfully.',
            'data' => new LeadProductResource($leadProduct),
        ], 201);
    }

    public function updateProduct(Request $request, int $id, int $leadProductId)
    {
        $this->checkPermission('leads.edit', $request);

        $data = $request->validate([
            'product_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|numeric|min:0.01',
            'expected_price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $leadProduct = $this->repo->updateLeadProduct($id, $leadProductId, $data);

        return response()->json([
            'message' => 'Lead product updated successfully.',
            'data' => new LeadProductResource($leadProduct),
        ]);
    }

    public function deleteProduct(Request $request, int $id, int $leadProductId)
    {
        $this->checkPermission('leads.edit', $request);
        $this->repo->deleteLeadProduct($id, $leadProductId);

        return response()->json([
            'message' => 'Lead product deleted successfully.',
        ]);
    }

    // PATCH /api/follow-ups/{id}/done
    public function markFollowUpDone(Request $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);
        $followUp = $this->repo->markFollowUpDone($id);
        return response()->json([
            'message' => 'Follow-up marked as done.',
            'data'    => new LeadFollowUpResource($followUp),
        ]);
    }

    public function linkPO(Request $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);

        $data = $request->validate([
            'purchase_order_id' => 'required|integer|min:1',
        ]);

        $lead = $this->repo->linkPurchaseOrder($id, (int) $data['purchase_order_id']);

        return response()->json([
            'message' => 'Purchase order linked successfully.',
            'data' => new LeadResource($lead),
        ]);
    }

    public function linkInvoice(Request $request, int $id)
    {
        $this->checkPermission('leads.edit', $request);

        $data = $request->validate([
            'invoice_id' => 'required|integer|min:1',
        ]);

        $lead = $this->repo->linkInvoice($id, (int) $data['invoice_id']);

        return response()->json([
            'message' => 'Invoice linked successfully.',
            'data' => new LeadResource($lead),
        ]);
    }

    // ── Helper ────────────────────────────────────────────
    private function checkPermission(string $permission, Request $request): void
    {
        if (!$request->user()->hasPermission($permission)) {
            abort(403, 'Access denied.');
        }
    }
}