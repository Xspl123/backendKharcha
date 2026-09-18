<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\SendQuotationEmailRequest;
use App\Http\Resources\QuotationResource;
use App\Mail\QuotationEmailMail;
use App\Models\LeadActivity;
use App\Repositories\Interfaces\QuotationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

    // POST /api/quotations/{id}/revise
    // Creates the next version (e.g. V2) linked back to this quotation —
    // the original row is never modified, so both stay on record.
    public function revise(StoreQuotationRequest $request, int $id)
    {
        $revised = $this->repo->reviseQuotation($id, $request->validated());

        return response()->json([
            'message' => 'New quotation version created.',
            'data' => new QuotationResource($revised),
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

    // POST /api/quotations/{id}/send-email
    // The PDF itself is generated client-side (same html2pdf approach the
    // existing Invoice feature already uses) and sent up as base64 —
    // this endpoint just attaches it and delivers the email.
    public function sendEmail(SendQuotationEmailRequest $request, int $id)
    {
        $quotation = $this->repo->getById($id);
        $sender = $request->user();

        $mail = Mail::to($request->validated('to'));
        if ($request->validated('cc')) {
            $mail->cc($request->validated('cc'));
        }

        $mailable = new QuotationEmailMail(
            $sender->name,
            $request->validated('subject'),
            $request->validated('body'),
            $quotation->quotation_no,
            $request->validated('pdf_base64'),
            "Quotation_{$quotation->quotation_no}.pdf"
        );
        if ($sender->email) {
            $mailable->replyTo($sender->email, $sender->name);
        }
        $mail->send($mailable);

        // Same activity-trail convention as the lead-level "Send Email"
        // feature (LeadController::sendLeadEmail) — visible on the lead's
        // Activities tab, so there's a record of exactly which quotation
        // went out, to whom, and when, not just a silent status flip.
        if ($quotation->lead_id) {
            LeadActivity::create([
                'lead_id' => $quotation->lead_id,
                'user_id' => $sender->id,
                'type'    => 'email',
                'note'    => "Quotation {$quotation->quotation_no} emailed to {$request->validated('to')}: \"{$request->validated('subject')}\"",
            ]);
        }

        // Emailing the quotation to the customer is, by definition, the
        // "Sent" step — auto-advance status so the table reflects reality
        // without the user having to also remember to change it manually.
        // Only moves it forward from Draft; never overrides an already
        // Approved/Rejected/Expired quotation just because it was re-sent.
        if ($quotation->status === 'draft') {
            $this->repo->updateStatus($id, 'sent');
        }

        return response()->json(['message' => 'Quotation email bhej di gayi.']);
    }
}