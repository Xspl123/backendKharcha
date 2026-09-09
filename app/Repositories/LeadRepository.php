<?php

namespace App\Repositories;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadFollowUp;
use App\Models\LeadProduct;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Repositories\Interfaces\LeadRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Facades\Auth;
use App\Models\LeadScoreRule;
use App\Models\LeadCustomField;
use App\Models\LeadWorkflowRule;
use App\Services\NotificationService;

class LeadRepository implements LeadRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function __construct(private NotificationService $push) {}


    public function getAll(array $filters = [])
    {
        $query = $this->scopeQuery(Lead::query())
            ->with(['owner:id,name', 'client:id,company_name']);

        $user = Auth::user();
        // ✅ Sales agent — sirf assigned leads
        if ($user->hasRole('sales_agent')) {
            $query->where('owner_id', $user->id);
        }

        if (!empty($filters['status']))    $query->where('status', $filters['status']);
        if (!empty($filters['owner_id']))  $query->where('owner_id', $filters['owner_id']);
        if (!empty($filters['source']))    $query->where('source', $filters['source']);
        if (!empty($filters['country']))   $query->where('country', $filters['country']);
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn($q) => $q
                ->where('company_name',    'like', "%{$s}%")
                ->orWhere('contact_person','like', "%{$s}%")
                ->orWhere('phone',         'like', "%{$s}%")
                ->orWhere('email',         'like', "%{$s}%")
            );
        }

        return $query->withCount(['activities','followUps','pendingFollowUps'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->resolvePerPage($filters));
    }

    public function findById(int $id): Lead
    {
        return $this->scopeQuery(Lead::query())
            ->with([
                'owner:id,name,email',
                'client:id,company_name,email,phone',
                'leadProducts.product:id,name,sku',
                'quotations:id,lead_id,quotation_no,status,total_amount',
                'activities.user:id,name',
                'followUps.user:id,name',
                'purchaseOrder:id,po_number,total_amount,status',
                'invoice:id,invoice_no,total_amount,status',
            ])->findOrFail($id);
    }

    public function create(array $data): Lead
    {
        $d = $this->scopeData($data);

        if (empty($d['owner_id'])) {
            $d['owner_id'] = Auth::id();
        }

        $lead = Lead::create($d);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type'    => 'note',
            'note'    => 'Lead created',
        ]);

        $duplicates = $this->findDuplicateLeads($lead, $lead->org_id);
        if (!empty($duplicates)) {
            $names = collect($duplicates)->pluck('company_name')->implode(', ');
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => Auth::id(),
                'type'    => 'note',
                'note'    => "Possible duplicate detected — matches: {$names}",
            ]);
        }

        $this->bumpScopedCache(['leads', 'campaigns']);

        $fresh = $lead->fresh(['owner:id,name']);
        // Transient, non-persisted attribute — not a DB column. Only set
        // when duplicates were found, so LeadResource can surface it in
        // the create-response for an immediate inline warning, without
        // this ever being part of the lead's normal stored/read data.
        if (!empty($duplicates)) {
            $fresh->setAttribute('possible_duplicates', $duplicates);
        }

        return $fresh;
    }

    // Matches by normalized phone, normalized email, or normalized company
    // name — same normalization LeadList.jsx's existing client-side
    // "duplicate signal" banner already uses (see normalizePhone /
    // normalizeComparable there), so this stays consistent with what the
    // UI already surfaces; it just now also fires the moment a lead is
    // created (both the authenticated Add Lead form and the public web
    // form) instead of only being noticed later when someone browses the
    // Leads list.
    private function findDuplicateLeads(Lead $lead, ?int $orgId): array
    {
        if (!$orgId) return [];

        $normalizePhone = fn($v) => preg_replace('/\D/', '', (string) $v);
        $normalize = fn($v) => strtolower(trim((string) $v));

        $phone = $normalizePhone($lead->phone);
        $email = $normalize($lead->email);
        $company = $normalize($lead->company_name);

        if ($phone === '' && $email === '' && $company === '') return [];

        return Lead::where('org_id', $orgId)
            ->where('id', '!=', $lead->id)
            ->get(['id', 'company_name', 'phone', 'email'])
            ->filter(function ($candidate) use ($phone, $email, $company, $normalizePhone, $normalize) {
                $cPhone = $normalizePhone($candidate->phone);
                $cEmail = $normalize($candidate->email);
                $cCompany = $normalize($candidate->company_name);
                return ($phone !== '' && strlen($phone) >= 7 && $cPhone === $phone)
                    || ($email !== '' && $cEmail === $email)
                    || ($company !== '' && $cCompany === $company);
            })
            ->take(5)
            ->values()
            ->map(fn($c) => ['id' => $c->id, 'company_name' => $c->company_name])
            ->all();
    }

    // Public wrapper so PublicLeadController (which has no Auth context and
    // doesn't otherwise touch the repository) can reuse the exact same
    // matching logic for web-form submissions.
    public function findDuplicatesForOrg(Lead $lead, int $orgId): array
    {
        return $this->findDuplicateLeads($lead, $orgId);
    }

    public function update(int $id, array $data): Lead
    {
        $lead      = $this->scopeQuery(Lead::query())->findOrFail($id);
        $oldStatus = $lead->status;
        $lead->update($data);
        $this->bumpScopedCache(['leads', 'campaigns']);

        if (array_key_exists('status', $data) && $data['status'] !== $oldStatus) {
            $this->applyWorkflowRules($lead, $data['status']);
        }

        return $lead->fresh(['owner:id,name', 'client:id,company_name']);
    }

    public function delete(int $id): bool
    {
        $deleted = $this->scopeQuery(Lead::query())->findOrFail($id)->delete();
        $this->bumpScopedCache(['leads', 'campaigns']);
        return $deleted;
    }

    public function updateStatus(int $id, string $status, ?string $lostReason = null): Lead
    {
        $lead      = $this->scopeQuery(Lead::query())->findOrFail($id);
        $oldStatus = $lead->status;

        $lead->update(['status' => $status, 'lost_reason' => $lostReason]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type'    => 'status_change',
            'note'    => "Status changed: {$oldStatus} → {$status}" .
                         ($lostReason ? " | Reason: {$lostReason}" : ''),
        ]);

        if ($status === 'closed_won' && !$lead->client_id) {
            $this->autoCreateClient($lead);
        }

        $this->applyWorkflowRules($lead, $status);

        $this->bumpScopedCache(['leads', 'campaigns', 'clients']);

        return $lead->fresh();
    }

    public function addActivity(int $leadId, array $data)
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);
        return LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => $data['type'],
            'note'          => $data['note']          ?? null,
            'call_duration' => $data['call_duration'] ?? null,
            'outcome'       => $data['outcome']       ?? null,
        ]);
    }

    public function addFollowUp(int $leadId, array $data)
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);
        return LeadFollowUp::create([
            'lead_id'  => $lead->id,
            'user_id'  => Auth::id(),
            'due_date' => $data['due_date'],
            'note'     => $data['note'] ?? null,
        ]);
    }

    public function markFollowUpDone(int $followUpId)
    {
        $followUp = LeadFollowUp::whereHas('lead', fn ($query) => $this->scopeQuery($query))
            ->findOrFail($followUpId);

        $followUp->update(['is_done' => true, 'done_at' => now()]);
        return $followUp;
    }

    public function getLeadProducts(int $leadId)
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);

        return $lead->leadProducts()->with('product:id,name,sku')->orderBy('id')->get();
    }

    public function addLeadProduct(int $leadId, array $data)
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);
        $product = $this->scopeQuery(\App\Models\Product::query())->findOrFail($data['product_id']);

        $leadProduct = LeadProduct::updateOrCreate(
            [
                'lead_id' => $lead->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => (float) ($data['quantity'] ?? 1),
                'expected_price' => array_key_exists('expected_price', $data) ? $data['expected_price'] : null,
                'note' => $data['note'] ?? null,
            ]
        );

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type' => 'note',
            'note' => "Product linked: {$product->name}",
        ]);

        $this->bumpScopedCache(['leads', 'products']);

        return $leadProduct->fresh('product:id,name,sku');
    }

    public function updateLeadProduct(int $leadId, int $leadProductId, array $data)
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);
        $leadProduct = LeadProduct::where('lead_id', $lead->id)->findOrFail($leadProductId);

        if (!empty($data['product_id']) && (int) $data['product_id'] !== (int) $leadProduct->product_id) {
            $product = $this->scopeQuery(\App\Models\Product::query())->findOrFail($data['product_id']);
            $leadProduct->product_id = $product->id;
        }

        $leadProduct->fill([
            'quantity' => array_key_exists('quantity', $data) ? (float) $data['quantity'] : $leadProduct->quantity,
            'expected_price' => array_key_exists('expected_price', $data) ? $data['expected_price'] : $leadProduct->expected_price,
            'note' => array_key_exists('note', $data) ? $data['note'] : $leadProduct->note,
        ])->save();

        $this->bumpScopedCache(['leads', 'products']);

        return $leadProduct->fresh('product:id,name,sku');
    }

    public function deleteLeadProduct(int $leadId, int $leadProductId): bool
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);
        $deleted = LeadProduct::where('lead_id', $lead->id)->findOrFail($leadProductId)->delete();
        $this->bumpScopedCache(['leads', 'products']);
        return $deleted;
    }

    public function linkPurchaseOrder(int $leadId, int $purchaseOrderId): Lead
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);
        $po = $this->scopeQuery(PurchaseOrder::query())
            ->where('is_return', false)
            ->findOrFail($purchaseOrderId);

        $lead->update([
            'po_id' => $po->id,
            'status' => 'po_received',
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type' => 'status_change',
            'note' => "PO linked: {$po->po_number}",
        ]);

        $this->bumpScopedCache(['leads', 'purchase_orders']);

        return $lead->fresh(['purchaseOrder', 'invoice', 'owner:id,name', 'client:id,company_name']);
    }

    public function linkInvoice(int $leadId, int $invoiceId): Lead
    {
        $lead = $this->scopeQuery(Lead::query())->findOrFail($leadId);
        $invoice = $this->scopeQuery(Invoice::query())
            ->where(function ($query) {
                $query->whereNull('is_return')->orWhere('is_return', 0);
            })
            ->findOrFail($invoiceId);

        $lead->update([
            'invoice_id' => $invoice->id,
            'status' => 'invoice_generated',
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type' => 'status_change',
            'note' => "Invoice linked: {$invoice->invoice_no}",
        ]);

        $this->bumpScopedCache(['leads', 'invoices']);

        return $lead->fresh(['purchaseOrder', 'invoice', 'owner:id,name', 'client:id,company_name']);
    }

    public function getSummary(): array
    {
        return $this->rememberScoped('leads', 'summary', 180, function () {
            $base = $this->scopeQuery(Lead::query());
            $statusCounts = (clone $base)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $total = (int) $statusCounts->sum();
            $closedWon = (int) ($statusCounts['closed_won'] ?? 0);
            $closedLost = (int) ($statusCounts['closed_lost'] ?? 0);
            $new = (int) ($statusCounts['new'] ?? 0);

            return [
                'total'       => $total,
                'new'         => $new,
                'in_progress' => $total - $new - $closedWon - $closedLost,
                'closed_won'  => $closedWon,
                'closed_lost' => $closedLost,
                'overdue_followups' => LeadFollowUp::whereHas('lead',
                    fn($q) => $this->scopeQuery($q)
                )->where('is_done', false)->where('due_date', '<', now())->count(),
            ];
        });
    }

    public function getPipelineStats(): array
    {
        return $this->rememberScoped('leads', 'pipeline', 180, function () {
            $statuses = [
                'new','contact_attempted','connected','requirement_discussion',
                'quotation_sent','negotiation','positive_response',
                'po_received','invoice_generated','closed_won','closed_lost',
            ];
            $counts = $this->scopeQuery(Lead::query())
                ->whereIn('status', $statuses)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $result = [];
            foreach ($statuses as $status) {
                $result[$status] = (int) ($counts[$status] ?? 0);
            }
            return $result;
        });
    }

    /**
     * Due-today + overdue open follow-ups, for the Navbar reminder bell.
     * Lightweight by design — this is polled every few minutes app-wide, so
     * it must never pull full lead payloads, only the fields the bell needs.
     * NOT cached via ScopedCache: due/overdue status is time-sensitive
     * (a follow-up becomes overdue purely by the clock ticking past
     * midnight, with no write happening to bump the cache), so a cached
     * value would go stale without any event to invalidate it.
     */
    public function getDueFollowUps(): array
    {
        $user = Auth::user();

        $query = LeadFollowUp::query()
            ->whereHas('lead', fn ($q) => $this->scopeQuery($q))
            ->where('is_done', false)
            ->whereDate('due_date', '<=', now())
            ->with('lead:id,company_name');

        // ✅ Sales agent — sirf apne assigned leads ke follow-ups
        if ($user->hasRole('sales_agent')) {
            $query->whereHas('lead', fn ($q) => $this->scopeQuery($q)->where('owner_id', $user->id));
        }

        return $query->orderBy('due_date')
            ->get()
            ->map(fn ($followUp) => [
                'follow_up_id' => $followUp->id,
                'lead_id'      => $followUp->lead_id,
                'company_name' => $followUp->lead?->company_name,
                'due_date'     => $followUp->due_date?->format('Y-m-d'),
                'note'         => $followUp->note,
                'is_overdue'   => $followUp->due_date?->lt(now()->startOfDay()) ?? false,
            ])
            ->values()
            ->all();
    }


        /**
     * Web leads nobody has actioned yet, for the Navbar reminder bell.
     * "Unactioned" is defined as source=website AND status still at the
     * default 'new' stage — no extra column needed: the moment a staff
     * member changes the lead's status (even just to acknowledge it), it
     * naturally drops off this list. Capped and most-recent-first so a
     * long-unattended backlog doesn't flood the bell.
     */
    public function getNewWebLeads(): array
    {
        $user = Auth::user();
 
        $query = $this->scopeQuery(Lead::query())
            ->where('source', 'website')
            ->where('status', 'new');
 
        // ✅ Sales agent — sirf apne assigned leads
        if ($user->hasRole('sales_agent')) {
            $query->where('owner_id', $user->id);
        }
 
        return $query->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'company_name', 'contact_person', 'phone', 'email', 'created_at'])
            ->map(fn ($lead) => [
                'lead_id'        => $lead->id,
                'company_name'   => $lead->company_name,
                'contact_person' => $lead->contact_person,
                'phone'          => $lead->phone,
                'email'          => $lead->email,
                'created_at'     => $lead->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Open follow-ups due within the next N days (default 7 = this week),
     * for the Dashboard's "Upcoming Follow-ups" widget. Unlike
     * getDueFollowUps() (today + overdue, for the Navbar bell), this looks
     * forward across the whole week so nothing due Wednesday gets missed.
     */
    public function getUpcomingFollowUps(int $days = 7): array
    {
        $user = Auth::user();

        $query = LeadFollowUp::query()
            ->whereHas('lead', fn ($q) => $this->scopeQuery($q))
            ->where('is_done', false)
            ->whereDate('due_date', '>=', now())
            ->whereDate('due_date', '<=', now()->addDays($days))
            ->with('lead:id,company_name');

        if ($user->hasRole('sales_agent')) {
            $query->whereHas('lead', fn ($q) => $this->scopeQuery($q)->where('owner_id', $user->id));
        }

        return $query->orderBy('due_date')
            ->get()
            ->map(fn ($followUp) => [
                'follow_up_id' => $followUp->id,
                'lead_id'      => $followUp->lead_id,
                'company_name' => $followUp->lead?->company_name,
                'due_date'     => $followUp->due_date?->format('Y-m-d'),
                'note'         => $followUp->note,
            ])
            ->values()
            ->all();
    }

    // ── Auto Client Create ────────────────────────────────
    private function autoCreateClient(Lead $lead): void
    {
        try {
            // Already exist check
            $existing = Client::where('org_id', $lead->org_id)
                ->where(function ($q) use ($lead) {
                    if ($lead->email) $q->where('email', $lead->email);
                    else $q->where('company_name', $lead->company_name);
                })->first();

            if ($existing) {
                $lead->update(['client_id' => $existing->id]);
                return;
            }

            $client = Client::create([
                'user_id'         => $lead->user_id,
                'org_id'          => $lead->org_id,
                'company_name'    => $lead->company_name,
                'name'            => $lead->contact_person ?? $lead->company_name,
                'email'           => $lead->email,
                'phone'           => $lead->phone,
                'website'         => $lead->website,
                'country'         => $lead->country,
                'city'            => $lead->city,
                'opening_balance' => 0,
            ]);

            $lead->update(['client_id' => $client->id]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => Auth::id(),
                'type'    => 'note',
                'note'    => "✅ Client auto-created: {$client->company_name}",
            ]);

        } catch (\Exception $e) {
            \Log::warning('Auto client creation failed: ' . $e->getMessage());
        }
    }

    public function getScoreRules(): array
    {
        $user = Auth::user();

        $rule = $this->usesOrgScope()
            ? LeadScoreRule::where('org_id', $user->org_id)->first()
            : LeadScoreRule::where('user_id', $user->id)->first();

        return $rule?->rules ?? [];
    }

    public function saveScoreRules(array $rules): array
    {
        $user = Auth::user();

        $rule = $this->usesOrgScope()
            ? LeadScoreRule::updateOrCreate(['org_id' => $user->org_id], ['rules' => $rules])
            : LeadScoreRule::updateOrCreate(['user_id' => $user->id], ['rules' => $rules]);

        return $rule->rules;
    }

    private function usesOrgScope(): bool
    {
        return Auth::user()->hasOrg();
    }

    private function scopedCustomFieldQuery()
    {
        $user = Auth::user();
        return $this->usesOrgScope()
            ? LeadCustomField::where('org_id', $user->org_id)
            : LeadCustomField::where('user_id', $user->id);
    }

    public function getCustomFieldDefinitions(): array
    {
        return $this->scopedCustomFieldQuery()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function createCustomFieldDefinition(array $data): LeadCustomField
    {
        $user = Auth::user();

        $data['field_key'] = $data['field_key'] ?? \Illuminate\Support\Str::slug($data['label'], '_');

        if ($this->usesOrgScope()) {
            $data['org_id'] = $user->org_id;
            $data['user_id'] = null;
        } else {
            $data['user_id'] = $user->id;
            $data['org_id'] = null;
        }

        return LeadCustomField::create($data);
    }

    public function updateCustomFieldDefinition(int $id, array $data): LeadCustomField
    {
        $field = $this->scopedCustomFieldQuery()->findOrFail($id);
        $field->update($data);
        return $field->fresh();
    }

    public function deleteCustomFieldDefinition(int $id): bool
    {
        return (bool) $this->scopedCustomFieldQuery()->findOrFail($id)->delete();
    }

    // ── Workflow Rules ───────────────────────────────────
    private function scopedWorkflowRuleQuery()
    {
        $user = Auth::user();
        return $this->usesOrgScope()
            ? LeadWorkflowRule::where('org_id', $user->org_id)
            : LeadWorkflowRule::where('user_id', $user->id);
    }

    public function getWorkflowRules(): array
    {
        return $this->scopedWorkflowRuleQuery()->orderBy('id')->get()->all();
    }

    public function createWorkflowRule(array $data): LeadWorkflowRule
    {
        $user = Auth::user();

        if ($this->usesOrgScope()) {
            $data['org_id'] = $user->org_id;
            $data['user_id'] = null;
        } else {
            $data['user_id'] = $user->id;
            $data['org_id'] = null;
        }

        return LeadWorkflowRule::create($data);
    }

    public function updateWorkflowRule(int $id, array $data): LeadWorkflowRule
    {
        $rule = $this->scopedWorkflowRuleQuery()->findOrFail($id);
        $rule->update($data);
        return $rule->fresh();
    }

    public function deleteWorkflowRule(int $id): bool
    {
        return (bool) $this->scopedWorkflowRuleQuery()->findOrFail($id)->delete();
    }

    // Fires from update()/updateStatus() whenever a lead's status actually
    // changes. v1 only supports 'status_change' triggers + 'notify_owner'
    // actions (a push to the lead's current owner via the same
    // NotificationService (push + email) the follow-up/new-lead reminders use), but
    // trigger_type/action_type are stored as free strings so new kinds can
    // be added later without touching the schema.
    private function applyWorkflowRules(Lead $lead, string $newStatus): void
    {
        if (!$lead->owner_id) return;

        $rules = $this->scopedWorkflowRuleQuery()
            ->where('is_active', true)
            ->where('trigger_type', 'status_change')
            ->where('trigger_status', $newStatus)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->action_type !== 'notify_owner') continue;

            $this->push->sendToUser($lead->owner_id, [
                'title' => $rule->name,
                'body'  => $rule->action_message
                    ?: "{$lead->company_name} is now \"{$newStatus}\"",
                'data'  => ['leadId' => $lead->id],
            ]);
        }
    }
}