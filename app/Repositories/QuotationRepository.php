<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadWorkflowRule;
use App\Models\Quotation;
use App\Repositories\Interfaces\QuotationRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class QuotationRepository implements QuotationRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function __construct(private NotificationService $notify) {}

    public function getAll(array $filters): mixed
    {
        $query = $this->scopeQuery(Quotation::query())
            ->with(['lead:id,company_name,contact_person', 'client:id,company_name,contact_person'])
            ->withCount('items')
            ->orderByDesc('quotation_date')
            ->orderByDesc('id');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['lead_id'])) $query->where('lead_id', $filters['lead_id']);
        if (!empty($filters['client_id'])) $query->where('client_id', $filters['client_id']);
        if (!empty($filters['from_date'])) $query->whereDate('quotation_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('quotation_date', '<=', $filters['to_date']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('quotation_no', 'like', "%{$search}%")
                    ->orWhereHas('lead', fn ($leadQuery) => $leadQuery->where('company_name', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('company_name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($this->resolvePerPage($filters));
    }

    public function getById(int $id): mixed
    {
        return $this->scopeQuery(Quotation::query())
            ->with(['lead:id,company_name,contact_person,email,phone', 'client:id,company_name,contact_person,email,phone', 'items.product:id,name,sku'])
            ->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $scoped = $this->scopeData([]);
            $leadId = $this->resolveLeadId($data['lead_id'] ?? null);
            $clientId = $this->resolveClientId($data['client_id'] ?? null);

            $quotation = Quotation::create([
                'user_id' => $scoped['user_id'],
                'org_id' => $scoped['org_id'] ?? null,
                'lead_id' => $leadId,
                'client_id' => $clientId,
                'quotation_no' => $this->generateQuotationNumber(),
                'quotation_date' => $data['quotation_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty'];
                $rate = (float) $item['rate'];
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $amount = round($qty * $rate, 2);
                $taxAmount = round($amount * $taxRate / 100, 2);

                $quotation->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'hsn_code' => $item['hsn_code'] ?? null,
                    'qty' => $qty,
                    'unit' => $item['unit'] ?? 'pcs',
                    'rate' => $rate,
                    'amount' => $amount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                ]);
            }

            $quotation->load('items');
            $quotation->calculateTotals();
            $quotation->load(['lead:id,company_name,owner_id', 'client:id,company_name,user_id']);
            $this->applyQuotationWorkflowRules($quotation, $quotation->status);

            if ($quotation->lead_id) {
                $lead = $this->scopeQuery(Lead::query())->find($quotation->lead_id);
                if ($lead && in_array($lead->status, ['new', 'contact_attempted', 'connected', 'requirement_discussion'], true)) {
                    $lead->update(['status' => 'quotation_sent']);
                    LeadActivity::create([
                        'lead_id' => $lead->id,
                        'user_id' => \Illuminate\Support\Facades\Auth::id(),
                        'type'    => 'status_change',
                        'note'    => "Status auto-updated to \"Quotation Sent\" — quotation {$quotation->quotation_no} created.",
                    ]);
                }
            }

            $this->bumpScopedCache(['leads', 'clients']);

            return $quotation->fresh(['lead', 'client', 'items.product']);
        });
    }

    // Creates a NEW quotation row (the next version) instead of touching
    // the original — e.g. after a price negotiation, QT-...0005 (V1) stays
    // exactly as sent, and QT-...0005-V2 is the revised one. The original
    // is left completely untouched (status, items, everything).
    public function reviseQuotation(int $id, array $data): mixed
    {
        return DB::transaction(function () use ($id, $data) {
            $original = $this->scopeQuery(Quotation::query())->findOrFail($id);

            // Walk to the root of the version chain so the next version
            // number is always correct, regardless of which existing
            // version the "Revise" button was clicked from.
            $rootId = $original->parent_quotation_id ?? $original->id;
            $maxVersion = $this->scopeQuery(Quotation::query())
                ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_quotation_id', $rootId))
                ->max('version') ?? $original->version;
            $nextVersion = max($maxVersion, $original->version) + 1;

            // Strip any existing "-VN" suffix so a revision-of-a-revision
            // reads "...-0005-V3", never "...-0005-V1-V2-V3".
            $baseNo = preg_replace('/-V\d+$/', '', $original->quotation_no);
            $newQuotationNo = "{$baseNo}-V{$nextVersion}";

            $revised = Quotation::create([
                'user_id' => $original->user_id,
                'org_id' => $original->org_id,
                'lead_id' => $original->lead_id,
                'client_id' => $original->client_id,
                'quotation_no' => $newQuotationNo,
                'version' => $nextVersion,
                'parent_quotation_id' => $original->id,
                'quotation_date' => $data['quotation_date'] ?? now()->toDateString(),
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? $original->notes,
                'terms_conditions' => $data['terms_conditions'] ?? $original->terms_conditions,
            ]);

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty'];
                $rate = (float) $item['rate'];
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $amount = round($qty * $rate, 2);
                $taxAmount = round($amount * $taxRate / 100, 2);

                $revised->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'hsn_code' => $item['hsn_code'] ?? null,
                    'qty' => $qty,
                    'unit' => $item['unit'] ?? 'pcs',
                    'rate' => $rate,
                    'amount' => $amount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                ]);
            }

            $revised->load('items');
            $revised->calculateTotals();
            $revised->load(['lead:id,company_name,owner_id', 'client:id,company_name,user_id']);
            $this->applyQuotationWorkflowRules($revised, $revised->status);

            if ($revised->lead_id) {
                LeadActivity::create([
                    'lead_id' => $revised->lead_id,
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'type'    => 'note',
                    'note'    => "Quotation revised: {$newQuotationNo} created (previous: {$original->quotation_no}).",
                ]);
            }

            $this->bumpScopedCache(['leads', 'clients']);

            return $revised->fresh(['lead', 'client', 'items.product']);
        });
    }

    public function update(int $id, array $data): mixed
    {
        return DB::transaction(function () use ($id, $data) {
            $quotation = $this->scopeQuery(Quotation::query())->with('items')->findOrFail($id);
            $leadId = array_key_exists('lead_id', $data) ? $this->resolveLeadId($data['lead_id']) : $quotation->lead_id;
            $clientId = array_key_exists('client_id', $data) ? $this->resolveClientId($data['client_id']) : $quotation->client_id;

            $quotation->update([
                'lead_id' => $leadId,
                'client_id' => $clientId,
                'quotation_date' => $data['quotation_date'] ?? $quotation->quotation_date,
                'expiry_date' => array_key_exists('expiry_date', $data) ? $data['expiry_date'] : $quotation->expiry_date,
                'status' => $data['status'] ?? $quotation->status,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $quotation->notes,
                'terms_conditions' => array_key_exists('terms_conditions', $data) ? $data['terms_conditions'] : $quotation->terms_conditions,
            ]);

            if (!empty($data['items'])) {
                $quotation->items()->delete();

                foreach ($data['items'] as $item) {
                    $qty = (float) $item['qty'];
                    $rate = (float) $item['rate'];
                    $taxRate = (float) ($item['tax_rate'] ?? 0);
                    $amount = round($qty * $rate, 2);
                    $taxAmount = round($amount * $taxRate / 100, 2);

                    $quotation->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'item_name' => $item['item_name'],
                        'description' => $item['description'] ?? null,
                        'hsn_code' => $item['hsn_code'] ?? null,
                        'qty' => $qty,
                        'unit' => $item['unit'] ?? 'pcs',
                        'rate' => $rate,
                        'amount' => $amount,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                    ]);
                }
            }

            $quotation->load('items');
            $quotation->calculateTotals();

            return $quotation->fresh(['lead', 'client', 'items.product']);
        });
    }

    public function delete(int $id): bool
    {
        $quotation = $this->scopeQuery(Quotation::query())->findOrFail($id);
        return $quotation->delete();
    }

    public function updateStatus(int $id, string $status): mixed
    {
        $quotation = $this->scopeQuery(Quotation::query())->findOrFail($id);
        $quotation->update(['status' => $status]);
        $quotation = $quotation->fresh(['lead', 'client', 'items.product']);
        $this->applyQuotationWorkflowRules($quotation, $status);
        return $quotation;
    }

    // Mirrors LeadRepository::applyWorkflowRules(), but for
    // trigger_type='quotation_status_change' rules. Deliberately does NOT
    // use scopeQuery()/Auth::user() — it's called both from authenticated
    // HTTP requests (manual status change, email-triggered auto-advance)
    // and from the unauthenticated CheckQuotationExpiry scheduled command,
    // so it scopes purely off the quotation's own org_id/user_id instead.
    public function applyQuotationWorkflowRules(Quotation $quotation, string $newStatus): void
    {
        $notifyUserId = $quotation->lead?->owner_id ?? $quotation->client?->user_id;
        if (!$notifyUserId) return;

        $rules = LeadWorkflowRule::where('trigger_type', 'quotation_status_change')
            ->where('trigger_status', $newStatus)
            ->where('is_active', true)
            ->where(function ($q) use ($quotation) {
                $q->where('org_id', $quotation->org_id)->orWhere('user_id', $quotation->user_id);
            })
            ->get();

        foreach ($rules as $rule) {
            if ($rule->action_type !== 'notify_owner') continue;

            $partyName = $quotation->lead?->company_name ?? $quotation->client?->company_name ?? 'the customer';
            $this->notify->sendToUser($notifyUserId, [
                'title' => $rule->name,
                'body'  => $rule->action_message
                    ?: "Quotation {$quotation->quotation_no} for {$partyName} is now \"{$newStatus}\"",
                'data'  => ['leadId' => $quotation->lead_id],
            ]);
        }
    }

    private function generateQuotationNumber(): string
    {
        $prefix = 'QT-' . now()->format('Ymd') . '-';
        $latestId = $this->scopeQuery(Quotation::query())->max('id') ?? 0;

        return $prefix . str_pad((string) ($latestId + 1), 4, '0', STR_PAD_LEFT);
    }

    private function resolveLeadId(?int $leadId): ?int
    {
        if (!$leadId) {
            return null;
        }

        return $this->scopeQuery(Lead::query())->findOrFail($leadId)->id;
    }

    private function resolveClientId(?int $clientId): ?int
    {
        if (!$clientId) {
            return null;
        }

        return $this->scopeQuery(Client::query())->findOrFail($clientId)->id;
    }
}