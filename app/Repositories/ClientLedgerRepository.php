<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Repositories\Interfaces\ClientLedgerRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\ScopedCache;
use Carbon\Carbon;

class ClientLedgerRepository implements ClientLedgerRepositoryInterface
{
    use OrgScope, ScopedCache;

    public function getLedger($clientId, $fromDate = null, $toDate = null)
    {
        $suffix = 'ledger:' . $clientId . ':' . ($fromDate ?? 'null') . ':' . ($toDate ?? 'null');

        return $this->rememberScoped('client_ledger', $suffix, 180, function () use ($clientId, $fromDate, $toDate) {
            $client = $this->scopeQuery(Client::query())->findOrFail($clientId);

            $ledger         = [];
            $runningBalance = (float) $client->opening_balance;

            $ledger[] = ['date' => null, 'type' => 'Opening Balance', 'debit' => 0, 'credit' => 0, 'balance' => round($runningBalance, 2)];

            $invoices = $this->scopeQuery(Invoice::query())
                ->where('client_id', $clientId)
                ->when($fromDate, fn($q) => $q->whereDate('invoice_date', '>=', $fromDate))
                ->when($toDate,   fn($q) => $q->whereDate('invoice_date', '<=', $toDate))
                ->get()
                ->map(fn($inv) => [
                    'timestamp'    => Carbon::parse($inv->invoice_date)->timestamp,
                    'display_date' => Carbon::parse($inv->invoice_date)->format('d M Y'),
                    'type'         => 'Invoice - ' . $inv->invoice_no,
                    'debit'        => (float) $inv->total_amount,
                    'credit'       => 0,
                ]);

            $payments = InvoicePayment::whereHas('invoice', function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                    if (auth()->user()->hasOrg()) {
                        $q->where('org_id', $this->orgId());
                    } else {
                        $q->where('user_id', $this->userId());
                    }
                })
                ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
                ->when($toDate,   fn($q) => $q->whereDate('payment_date', '<=', $toDate))
                ->get()
                ->map(fn($pay) => [
                    'timestamp'    => Carbon::parse($pay->payment_date)->timestamp,
                    'display_date' => Carbon::parse($pay->payment_date)->format('d M Y'),
                    'type'         => 'Payment' .
                        (!empty($pay->payment_method) ? ' - ' . strtoupper($pay->payment_method) : '') .
                        (!empty($pay->transaction_id)  ? ' (' . $pay->transaction_id . ')' : ''),
                    'debit'        => 0,
                    'credit'       => (float) $pay->amount,
                ]);

            $transactions = collect($invoices)->merge($payments)->sortBy('timestamp')->values();

            foreach ($transactions as $trx) {
                $runningBalance += $trx['debit'];
                $runningBalance -= $trx['credit'];
                $ledger[] = [
                    'date'    => $trx['display_date'],
                    'type'    => $trx['type'],
                    'debit'   => round($trx['debit'], 2),
                    'credit'  => round($trx['credit'], 2),
                    'balance' => round($runningBalance, 2),
                ];
            }

            return [
                'client'          => $client->company_name,
                'opening_balance' => (float) $client->opening_balance,
                'closing_balance' => round($runningBalance, 2),
                'ledger'          => $ledger,
            ];
        });
    }
}
