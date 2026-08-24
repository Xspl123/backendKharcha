<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use App\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use App\Services\PurchaseOrderNumberService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use App\Models\StockMovement;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function __construct(
        private PurchaseOrderNumberService $poNumberService,
        private StockService $stockService,
    ) {}

    public function getAll(array $filters): mixed
    {
        $query = $this->scopeQuery(PurchaseOrder::query())
            ->where('is_return', false)
            ->with(['vendor:id,vendor_name,company_name,gstin'])
            ->withCount('items')
            ->orderByDesc('po_date');

        if (!empty($filters['status']))    $query->where('status', $filters['status']);
        if (!empty($filters['vendor_id'])) $query->where('vendor_id', $filters['vendor_id']);
        if (!empty($filters['from_date'])) $query->whereDate('po_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date']))   $query->whereDate('po_date', '<=', $filters['to_date']);
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn($q) => $q
                ->where('po_number', 'like', "%{$s}%")
                ->orWhereHas('vendor', fn($vq) => $vq
                    ->where('vendor_name',  'like', "%{$s}%")
                    ->orWhere('company_name','like', "%{$s}%")
                )
            );
        }
        return $query->paginate($this->resolvePerPage($filters));
    }

    public function getById(int $id): mixed
    {
        return $this->scopeQuery(PurchaseOrder::query())
            ->with(['vendor', 'items', 'payments' => fn($q) => $q->latest()])
            ->findOrFail($id);
    }

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            $scoped = $this->scopeData([]);

            $po = PurchaseOrder::create([
                'user_id'                => $scoped['user_id'],
                'org_id'                 => $scoped['org_id'] ?? null, 
                'vendor_id'              => $data['vendor_id'],
                'po_number'              => $this->poNumberService->generate($scoped['user_id']),
                'po_date'                => $data['po_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'supply_type'            => $data['supply_type']       ?? 'intra',
                'place_of_supply'        => $data['place_of_supply']   ?? null,
                'is_reverse_charge'      => $data['is_reverse_charge'] ?? false,
                'notes'                  => $data['notes']             ?? null,
                'terms_conditions'       => $data['terms_conditions']  ?? null,
                'status'                 => 'pending',
            ]);

            foreach ($data['items'] as $itemData) {
                $qty    = (float) $itemData['qty'];
                $rate   = (float) $itemData['rate'];
                $amount = round($qty * $rate, 2);
                $taxAmt = round($amount * (float) ($itemData['tax_rate'] ?? 0) / 100, 2);

                $po->items()->create([
                    'item_name'   => $itemData['item_name'],
                    'description' => $itemData['description'] ?? null,
                    'hsn_code'    => $itemData['hsn_code']    ?? null,
                    'qty'         => $qty,
                    'unit'        => $itemData['unit']        ?? 'pcs',
                    'rate'        => $rate,
                    'amount'      => $amount,
                    'tax_rate'    => (float) ($itemData['tax_rate'] ?? 0),
                    'tax_amount'  => $taxAmt,
                    'product_id'  => $itemData['product_id']  ?? null,
                    'category_id' => $itemData['category_id'] ?? null,
                ]);
            }

            $po->load('items');
            $po->calculateTotals();
            $this->bumpScopedCache(['purchase_orders', 'vendors', 'stock', 'stock_report']);
            return $po->load(['vendor', 'items']);
        });
    }

    public function update(int $id, array $data): mixed
    {
        return DB::transaction(function () use ($id, $data) {
            $po = $this->scopeQuery(PurchaseOrder::query())->findOrFail($id);
            abort_if($po->isReceived() || $po->isCancelled(), 422, 'Received ya cancelled PO update nahi ho sakta.');

            $po->update([
                'vendor_id'              => $data['vendor_id']              ?? $po->vendor_id,
                'po_date'                => $data['po_date']                ?? $po->po_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $po->expected_delivery_date,
                'supply_type'            => $data['supply_type']            ?? $po->supply_type,
                'place_of_supply'        => $data['place_of_supply']        ?? $po->place_of_supply,
                'is_reverse_charge'      => $data['is_reverse_charge']      ?? $po->is_reverse_charge,
                'notes'                  => $data['notes']                  ?? $po->notes,
                'terms_conditions'       => $data['terms_conditions']       ?? $po->terms_conditions,
            ]);

            if (!empty($data['items'])) {
                $po->items()->delete();
                foreach ($data['items'] as $itemData) {
                    $qty    = (float) $itemData['qty'];
                    $rate   = (float) $itemData['rate'];
                    $amount = round($qty * $rate, 2);
                    $taxAmt = round($amount * (float) ($itemData['tax_rate'] ?? 0) / 100, 2);
                    $po->items()->create([
                        'item_name'   => $itemData['item_name'],
                        'description' => $itemData['description'] ?? null,
                        'hsn_code'    => $itemData['hsn_code']    ?? null,
                        'qty'         => $qty,
                        'unit'        => $itemData['unit']        ?? 'pcs',
                        'rate'        => $rate,
                        'amount'      => $amount,
                        'tax_rate'    => (float) ($itemData['tax_rate'] ?? 0),
                        'tax_amount'  => $taxAmt,
                        'product_id'  => $itemData['product_id']  ?? null,
                        'category_id' => $itemData['category_id'] ?? null,
                    ]);
                }
                $po->load('items');
                $po->calculateTotals();
            }

            $this->bumpScopedCache(['purchase_orders', 'vendors', 'stock', 'stock_report']);
            return $po->load(['vendor', 'items']);
        });
    }

    public function delete(int $id): bool
    {
        $po = $this->scopeQuery(PurchaseOrder::query())->findOrFail($id);
        abort_if($po->isReceived(), 422, 'Received PO delete nahi ho sakta.');
        abort_if($po->payments()->count() > 0, 422, 'Is PO ke payments exist hain.');
        $this->bumpScopedCache(['purchase_orders', 'vendors', 'stock', 'stock_report']);
        return $po->delete();
    }

    public function updateStatus(int $id, string $status): mixed
    {
        return DB::transaction(function () use ($id, $status) {
            $po = $this->scopeQuery(PurchaseOrder::query())
                ->with(['items', 'items.product'])->findOrFail($id);

            $this->validateStatusTransition($po, $status);

            $updateData = ['status' => $status];

            if ($status === 'received') {
                $updateData['received_date'] = now()->toDateString();
                $this->stockService->processPurchaseOrderReceived($po);

                // ✅ Lead auto-link
                $this->linkLeadToPO($po);
            }

            $po->update($updateData);
            $this->bumpScopedCache(['purchase_orders', 'vendors', 'stock', 'stock_report', 'leads']);
            return $po->fresh(['vendor', 'items', 'payments']);
        });
    }

    // ✅ Lead auto-link when PO received
    private function linkLeadToPO(PurchaseOrder $po): void
    {
        try {
            if (!$po->org_id) return;

            $lead = \App\Models\Lead::where('org_id', $po->org_id)
                ->whereNull('po_id')
                ->whereIn('status', ['quotation_sent','negotiation','positive_response','connected','requirement_discussion'])
                ->latest()->first();

            if ($lead) {
                $lead->update(['po_id' => $po->id, 'status' => 'po_received']);
                \App\Models\LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $po->user_id,
                    'type'    => 'status_change',
                    'note'    => "✅ PO Received — Auto-linked: {$po->po_number}",
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Lead-PO auto-link failed: ' . $e->getMessage());
        }
    }

  public function getSummary(): array
  {
      return $this->rememberScoped('purchase_orders', 'summary', 300, function () {
          $summary = $this->scopeQuery(PurchaseOrder::query())
              ->where('is_return', false)
              ->selectRaw('
                  COUNT(*) as total_orders,
                  SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved,
                  SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as received,
                  SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as returned,
                  SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled,
                  COALESCE(SUM(CASE WHEN status IN (?, ?) THEN total_amount ELSE 0 END), 0) as total_amount,
                  COALESCE(SUM(paid_amount), 0) as total_paid
              ', ['pending', 'approved', 'received', 'return', 'returned', 'cancelled', 'approved', 'received'])
              ->first();

          $returnedAmount = $this->scopeQuery(StockMovement::query())
              ->where('type', 'return_out')
              ->where('reference_type', 'po_return')
              ->sum('value');

          $totalAmount = round((float) $summary->total_amount, 2);
          $returnedAmount = round(abs($returnedAmount), 2);
          $netAmount = round($totalAmount - $returnedAmount, 2);
          $totalPaid = round((float) $summary->total_paid, 2);
          $balance = round(max($netAmount - $totalPaid, 0), 2);
          $advance = round(max($totalPaid - $netAmount, 0), 2);

          return [
              'total_orders'    => (int) $summary->total_orders,
              'pending'         => (int) $summary->pending,
              'approved'        => (int) $summary->approved,
              'received'        => (int) $summary->received,
              'returned'        => (int) $summary->returned,
              'cancelled'       => (int) $summary->cancelled,
              'total_amount'    => $totalAmount,
              'returned_amount' => $returnedAmount,
              'net_amount'      => $netAmount,
              'total_paid'      => $totalPaid,
              'total_balance'   => $balance,
              'advance_paid'    => $advance,
          ];
      });
  }





    private function validateStatusTransition(PurchaseOrder $po, string $newStatus): void
    {
        $allowed = [
            'pending'   => ['approved','cancelled'],
            'approved'  => ['received','cancelled'],
            'received'  => ['return', 'returned'],
            'cancelled' => [],
            'return'    => [],
        ];
        abort_unless(in_array($newStatus, $allowed[$po->status] ?? []), 422,
            "Status '{$po->status}' se '{$newStatus}' mein change nahi ho sakta.");
    }
}
