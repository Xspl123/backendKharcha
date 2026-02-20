<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\Interfaces\PurchaseOrderRepositoryInterface;
use App\Services\PurchaseOrderNumberService;
use Illuminate\Support\Facades\DB;
use App\Services\StockService;


class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(
        private PurchaseOrderNumberService $poNumberService,
        private StockService $stockService,
    ) {}

    // ── Get All ────────────────────────────────────────────

    public function getAll(array $filters): mixed
    {
        $query = PurchaseOrder::where('user_id', auth()->id())
            ->with(['vendor:id,vendor_name,company_name,gstin'])
            ->withCount('items')
            ->orderByDesc('po_date');

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Vendor filter
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        // Date range
        if (!empty($filters['from_date'])) {
            $query->whereDate('po_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('po_date', '<=', $filters['to_date']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', fn($vq) =>
                      $vq->where('vendor_name', 'like', "%{$search}%")
                         ->orWhere('company_name', 'like', "%{$search}%")
                  );
            });
        }

        return $query->get();
    }

    // ── Get By ID ──────────────────────────────────────────

    public function getById(int $id): mixed
    {
        return PurchaseOrder::where('user_id', auth()->id())
            ->with([
                'vendor',
                'items',
                'payments' => fn($q) => $q->latest(),
            ])
            ->findOrFail($id);
    }

    // ── Create ─────────────────────────────────────────────

    public function create(array $data): mixed
    {
        return DB::transaction(function () use ($data) {
            // PO number auto-generate
            $po = PurchaseOrder::create([
                'user_id'                => auth()->id(),
                'vendor_id'              => $data['vendor_id'],
                'po_number'              => $this->poNumberService->generate(auth()->id()),
                'po_date'                => $data['po_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'supply_type'            => $data['supply_type']       ?? 'intra',
                'place_of_supply'        => $data['place_of_supply']   ?? null,
                'is_reverse_charge'      => $data['is_reverse_charge'] ?? false,
                'notes'                  => $data['notes']             ?? null,
                'terms_conditions'       => $data['terms_conditions']  ?? null,
                'status'                 => 'pending',
            ]);

            // Items create karo
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
                ]);
            }

            // Totals calculate karo
            $po->load('items');
            $po->calculateTotals();

            return $po->load(['vendor', 'items']);
        });
    }

    // ── Update ─────────────────────────────────────────────

    public function update(int $id, array $data): mixed
    {
        return DB::transaction(function () use ($id, $data) {
            $po = PurchaseOrder::where('user_id', auth()->id())->findOrFail($id);

            abort_if(
                $po->isReceived() || $po->isCancelled(),
                422,
                'Received ya cancelled PO update nahi ho sakta.'
            );

            // PO fields update
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

            // Items replace karo
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
                    ]);
                }

                $po->load('items');
                $po->calculateTotals();
            }

            return $po->load(['vendor', 'items']);
        });
    }

    // ── Delete ─────────────────────────────────────────────

    public function delete(int $id): bool
    {
        $po = PurchaseOrder::where('user_id', auth()->id())->findOrFail($id);

        abort_if(
            $po->isReceived(),
            422,
            'Received PO delete nahi ho sakta.'
        );

        abort_if(
            $po->payments()->count() > 0,
            422,
            'Is PO ke payments exist hain. Pehle payments delete karo.'
        );

        return $po->delete();
    }

    // ── Update Status ──────────────────────────────────────

    public function updateStatus(int $id, string $status): mixed
    {
        return DB::transaction(function () use ($id, $status) {
            $po = PurchaseOrder::where('user_id', auth()->id())
                ->with(['items', 'items.product'])    // ← items.product load karo
                ->findOrFail($id);

            $this->validateStatusTransition($po, $status);

            $updateData = ['status' => $status];

            if ($status === 'received') {
                $updateData['received_date'] = now()->toDateString();

                // ── AUTO STOCK UPDATE ── ✅
                $this->stockService->processPurchaseOrderReceived($po);
            }

            $po->update($updateData);

            return $po->fresh(['vendor', 'items', 'payments']);
        });
    }

    // ── Summary ────────────────────────────────────────────

    public function getSummary(): array
    {
        $pos = PurchaseOrder::where('user_id', auth()->id())->get();

        return [
            'total_orders'    => $pos->count(),
            'pending'         => $pos->where('status', 'pending')->count(),
            'approved'        => $pos->where('status', 'approved')->count(),
            'received'        => $pos->where('status', 'received')->count(),
            'cancelled'       => $pos->where('status', 'cancelled')->count(),
            'total_amount'    => round(
                $pos->whereIn('status', ['approved', 'received'])->sum('total_amount'), 2
            ),
            'total_paid'      => round($pos->sum('paid_amount'), 2),
            'total_balance'   => round(
                $pos->whereIn('status', ['approved', 'received'])->sum('balance_amount'), 2
            ),
        ];
    }

    // ── Private: Validate Status Transition ───────────────

    private function validateStatusTransition(PurchaseOrder $po, string $newStatus): void
    {
        $allowed = [
            'pending'   => ['approved', 'cancelled'],
            'approved'  => ['received', 'cancelled'],
            'received'  => [],
            'cancelled' => [],
        ];

        abort_unless(
            in_array($newStatus, $allowed[$po->status] ?? []),
            422,
            "Status '{$po->status}' se '{$newStatus}' mein change nahi ho sakta."
        );
    }
}