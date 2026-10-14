<?php

namespace App\Repositories;

use App\Models\Vendor;
use App\Models\PurchaseOrder;
use App\Models\VendorPayment;
use App\Repositories\Interfaces\VendorLedgerRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\ScopedCache;
use Carbon\Carbon;

class VendorLedgerRepository implements VendorLedgerRepositoryInterface
{
    use OrgScope, ScopedCache;

    public function getLedger(int $vendorId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $suffix = 'ledger:' . $vendorId . ':' . ($fromDate ?? 'null') . ':' . ($toDate ?? 'null');

        return $this->rememberScoped('vendor_ledger', $suffix, 180, function () use ($vendorId, $fromDate, $toDate) {
            $vendor = $this->scopeQuery(Vendor::query())->findOrFail($vendorId);

            $ledger         = [];
            $runningBalance = (float) ($vendor->opening_balance ?? 0);

            // Row 0: Opening balance
            $ledger[] = [
                'date'    => null,
                'type'    => 'Opening Balance',
                'debit'   => 0,
                'credit'  => 0,
                'balance' => round($runningBalance, 2),
            ];

            // 1. Purchase Orders & Returns
            $pos = $this->scopeQuery(PurchaseOrder::query())
                ->where('vendor_id', $vendorId)
                ->whereIn('status', ['approved', 'received', 'return', 'returned'])
                ->when($fromDate, fn($q) => $q->whereDate('po_date', '>=', $fromDate))
                ->when($toDate,   fn($q) => $q->whereDate('po_date', '<=', $toDate))
                ->get()
                ->map(function ($po) {
                    $isReturn = (bool) (
                        $po->is_return ||
                        in_array($po->status, ['return', 'returned']) ||
                        str_starts_with($po->po_number, 'PR-')
                    );
                    $date = $po->po_date ?? $po->created_at;

                    return [
                        'timestamp'    => Carbon::parse($date)->timestamp,
                        'display_date' => Carbon::parse($date)->format('d M Y'),
                        'type'         => ($isReturn ? 'Purchase Return (Debit Note) - ' : 'Purchase Order - ') . $po->po_number,
                        'debit'        => $isReturn ? (float) $po->total_amount : 0,
                        'credit'       => $isReturn ? 0 : (float) $po->total_amount,
                        'is_return'    => $isReturn,
                        'amount'       => (float) $po->total_amount,
                    ];
                });

            // 2. Vendor Payments
            $payments = $this->scopeQuery(VendorPayment::query())
                ->with('purchaseOrder')
                ->where('vendor_id', $vendorId)
                ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
                ->when($toDate,   fn($q) => $q->whereDate('payment_date', '<=', $toDate))
                ->get()
                ->map(function ($pay) {
                    $method = strtoupper($pay->payment_method ?? 'BANK');
                    $ref    = !empty($pay->reference_no) ? ' (' . $pay->reference_no . ')' : '';
                    $poStr  = $pay->purchaseOrder ? ' for ' . $pay->purchaseOrder->po_number : '';

                    return [
                        'timestamp'    => Carbon::parse($pay->payment_date)->timestamp,
                        'display_date' => Carbon::parse($pay->payment_date)->format('d M Y'),
                        'type'         => 'Payment - ' . $method . $ref . $poStr,
                        'debit'        => (float) $pay->amount,
                        'credit'       => 0,
                        'is_return'    => false,
                        'amount'       => (float) $pay->amount,
                    ];
                });

            $transactions = collect($pos)->merge($payments)->sortBy('timestamp')->values();

            $totalPurchases = 0;
            $totalReturned  = 0;
            $totalPaid      = 0;

            foreach ($transactions as $trx) {
                $runningBalance += $trx['credit'];
                $runningBalance -= $trx['debit'];

                if ($trx['credit'] > 0) {
                    $totalPurchases += $trx['credit'];
                }
                if ($trx['is_return']) {
                    $totalReturned += $trx['debit'];
                } elseif ($trx['debit'] > 0) {
                    $totalPaid += $trx['debit'];
                }

                $ledger[] = [
                    'date'    => $trx['display_date'],
                    'type'    => $trx['type'],
                    'debit'   => round($trx['debit'], 2),
                    'credit'  => round($trx['credit'], 2),
                    'balance' => round($runningBalance, 2),
                ];
            }

            return [
                'vendor'          => $vendor->vendor_name . ($vendor->company_name ? " ({$vendor->company_name})" : ''),
                'opening_balance' => (float) ($vendor->opening_balance ?? 0),
                'total_purchases' => round($totalPurchases, 2),
                'total_returned'  => round($totalReturned, 2),
                'total_paid'      => round($totalPaid, 2),
                'closing_balance' => round($runningBalance, 2),
                'ledger'          => $ledger,
            ];
        });
    }
}