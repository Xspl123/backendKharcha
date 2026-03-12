<?php
// app/Repositories/SalesReturnRepository.php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Repositories\Interfaces\SalesReturnRepositoryInterface;
use App\Repositories\StockMovementRepository;
use Illuminate\Support\Facades\DB;

class SalesReturnRepository implements SalesReturnRepositoryInterface
{
    public function __construct(
        private StockMovementRepository $stockRepo
    ) {}

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            // ── STEP 1: Original invoice fetch ────────────────────
            $originalInvoice = Invoice::with(['items.product', 'client'])
                ->find($data['original_invoice_id']);

            if (!$originalInvoice) {
                throw new \Exception('Original invoice not found');
            }

            // ── STEP 2: Client ID resolve ─────────────────────────
            $clientId = $originalInvoice->client_id
                ?? $originalInvoice->client?->id;

            if (!$clientId) {
                throw new \Exception('Client ID is null');
            }

            // ── STEP 3: Auth check ────────────────────────────────
            $userId = auth()->id();
            if (!$userId) {
                throw new \Exception('User not authenticated');
            }

            // ── STEP 4: Return Invoice create ─────────────────────
            $returnInvoice = new Invoice();
            $returnInvoice->user_id             = $userId;
            $returnInvoice->client_id           = $clientId;
            $returnInvoice->invoice_no          = 'R' . ($originalInvoice->invoice_no ?? 'INV') . '-' . time();
            $returnInvoice->invoice_date        = $data['return_date'] ?? now()->toDateString();
            $returnInvoice->due_date            = now()->addDays(15)->toDateString();
            $returnInvoice->sub_total           = 0;
            $returnInvoice->cgst                = 0;
            $returnInvoice->sgst                = 0;
            $returnInvoice->igst                = 0;
            $returnInvoice->total_amount        = 0;
            $returnInvoice->paid_amount         = 0;
            $returnInvoice->balance_amount      = 0;
            $returnInvoice->status              = 'return';
            $returnInvoice->is_return           = 1;
            $returnInvoice->original_invoice_id = $originalInvoice->id;
            $returnInvoice->invoice_type        = $originalInvoice->invoice_type  ?? 'b2b';
            $returnInvoice->supply_type         = $originalInvoice->supply_type   ?? 'intra';
            $returnInvoice->is_reverse_charge   = $originalInvoice->is_reverse_charge ?? 0;
            $returnInvoice->company_id          = $originalInvoice->company_id    ?? null;
            $returnInvoice->place_of_supply     = $originalInvoice->place_of_supply ?? null;
            $returnInvoice->notes               = $data['notes']
                ?? "Return of Invoice #{$originalInvoice->invoice_no}";
            $returnInvoice->save();

            if (!$returnInvoice->id) {
                throw new \Exception('Failed to create return invoice');
            }

            // ── STEP 5: Items process karo ────────────────────────
            $totalAmount = 0;
            $totalTax    = 0;

            foreach ($data['items'] as $item) {

                $productId = $item['product_id'] ?? null;
                $itemName  = $item['item_name']  ?? null;

                // ✅ invoice_item_id se seedha lookup — most reliable
                $originalItem = null;

                if (!empty($item['invoice_item_id'])) {
                    $originalItem = InvoiceItem::where('invoice_id', $originalInvoice->id)
                        ->where('id', $item['invoice_item_id'])
                        ->first();
                }

                // Fallback 1: product_id se
                if (!$originalItem && $productId) {
                    $originalItem = InvoiceItem::where('invoice_id', $originalInvoice->id)
                        ->where('product_id', $productId)
                        ->first();
                }

                // Fallback 2: item_name se
                if (!$originalItem && $itemName) {
                    $originalItem = InvoiceItem::where('invoice_id', $originalInvoice->id)
                        ->where('item_name', $itemName)
                        ->first();
                }

                if (!$originalItem) {
                    throw new \Exception(
                        "Item not found in original invoice. "
                        . "Item: " . ($itemName ?? "product_id #{$productId}")
                    );
                }

                // ✅ Product info — product_id se ya original item se fallback
                $product       = $productId ? Product::find($productId) : null;
                // ✅ FIX: original item ka product_id use karo agar frontend ne nahi bheja
                $finalProductId = $productId
                    ?? $originalItem->product_id
                    ?? null;
                $finalItemName = $product?->name
                    ?? $itemName
                    ?? $originalItem->item_name;

                // Max returnable check
                $maxReturnable = (float) $originalItem->qty
                    - (float) ($originalItem->returned_qty ?? 0);

                if ((float) $item['qty'] > $maxReturnable) {
                    throw new \Exception(
                        "Cannot return {$item['qty']} units of {$finalItemName}. "
                        . "Max returnable: {$maxReturnable}"
                    );
                }

                // Amounts calculate
                $amount    = round((float) $item['qty'] * (float) $item['rate'], 2);
                $taxRate   = (float) ($originalItem->tax_rate ?? 18);
                $taxAmount = round($amount * $taxRate / 100, 2);

                $totalAmount += $amount;
                $totalTax    += $taxAmount;

                // ✅ Return item create
                $returnItem = new InvoiceItem();
                $returnItem->invoice_id   = $returnInvoice->id;
                $returnItem->product_id   = $finalProductId;  // ✅ original item se fallback
                $returnItem->item_name    = $finalItemName;
                $returnItem->description  = $item['reason'] ?? $originalItem->description ?? '';
                $returnItem->hsn_code     = $originalItem->hsn_code ?? '';
                $returnItem->qty          = $item['qty'];
                $returnItem->unit         = $originalItem->unit ?? 'pcs';
                $returnItem->rate         = $item['rate'];
                $returnItem->amount       = $amount;
                $returnItem->tax_rate     = $taxRate;
                $returnItem->tax_amount   = $taxAmount;
                $returnItem->returned_qty = 0;
                $returnItem->is_returned  = 1;
                $returnItem->save();

                // ✅ Original item returned_qty update
                DB::table('invoice_items')
                    ->where('id', $originalItem->id)
                    ->update([
                        'returned_qty' => (float) $originalItem->returned_qty + (float) $item['qty'],
                        'is_returned'  => 1,
                        'updated_at'   => now(),
                    ]);

                // ✅ Stock movement — sirf product_id ho tab
                if ($finalProductId) {
                    $this->stockRepo->create([
                        'product_id'     => $finalProductId,
                        'type'           => 'return_in',
                        'qty'            => $item['qty'],
                        'rate'           => $item['rate'],
                        'notes'          => "Sales Return — Invoice #{$originalInvoice->invoice_no}"
                            . (isset($item['reason']) ? " — {$item['reason']}" : ''),
                        'movement_date'  => $data['return_date'] ?? now()->toDateString(),
                        'reference_type' => 'invoice_return',
                        'reference_id'   => $returnInvoice->id,
                        'reference_no'   => $returnInvoice->invoice_no,
                    ]);
                }
            }

            // ── STEP 6: Invoice totals update ─────────────────────
            $isInter = ($originalInvoice->supply_type === 'inter');
            DB::table('invoices')
                ->where('id', $returnInvoice->id)
                ->update([
                    'sub_total'      => round($totalAmount, 2),
                    'cgst'           => $isInter ? 0 : round($totalTax / 2, 2),
                    'sgst'           => $isInter ? 0 : round($totalTax / 2, 2),
                    'igst'           => $isInter ? round($totalTax, 2) : 0,
                    'total_amount'   => round($totalAmount + $totalTax, 2),
                    'balance_amount' => round($totalAmount + $totalTax, 2),
                    'updated_at'     => now(),
                ]);

            // ── STEP 7: Original invoice — allReturned check ──────
            // ✅ Fresh DB query — stale model cache avoid
            $allReturned = !DB::table('invoice_items')
                ->where('invoice_id', $originalInvoice->id)
                ->whereRaw('returned_qty < qty')
                ->exists();

            if ($allReturned) {
                DB::table('invoices')
                    ->where('id', $originalInvoice->id)
                    ->update([
                        'status'     => 'return',
                        'updated_at' => now(),
                    ]);
            }

            // Load fresh relationships
            return $returnInvoice->fresh([
                'items.product',
                'client',
                'originalInvoice',
            ]);
        });
    }

    // ── Find ──────────────────────────────────────────────
    public function find(int $id)
    {
        return Invoice::with(['items.product', 'client', 'originalInvoice'])
            ->where('is_return', true)
            ->findOrFail($id);
    }

    // ── Get All ───────────────────────────────────────────
    public function getAll(array $filters = [])
    {
        $query = Invoice::with(['client', 'originalInvoice'])
            ->where('is_return', true)
            ->where('user_id', auth()->id());

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    // ── Get By Invoice ────────────────────────────────────
    public function getByInvoice(int $invoiceId)
    {
        return Invoice::with(['items.product', 'client'])
            ->where('is_return', true)
            ->where('original_invoice_id', $invoiceId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ── Update Status ─────────────────────────────────────
    public function updateStatus(int $id, string $status)
    {
        $return = Invoice::where('is_return', true)->findOrFail($id);
        $return->update(['status' => $status]);
        return $return;
    }
}