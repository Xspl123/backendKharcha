<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Quotation;
use App\Repositories\Interfaces\QuotationRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Facades\DB;

class QuotationRepository implements QuotationRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

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

            if ($quotation->lead_id) {
                $lead = $this->scopeQuery(Lead::query())->find($quotation->lead_id);
                if ($lead && in_array($lead->status, ['new', 'attempted', 'contacted', 'qualified', 'requirement_discussion'], true)) {
                    $lead->update(['status' => 'quotation_sent']);
                }
            }

            $this->bumpScopedCache(['leads', 'clients']);

            return $quotation->fresh(['lead', 'client', 'items.product']);
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
        return $quotation->fresh(['lead', 'client', 'items.product']);
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
