<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Repositories\Interfaces\ClientLedgerRepositoryInterface;
use Carbon\Carbon;

class ClientLedgerRepository implements ClientLedgerRepositoryInterface
{
    public function getLedger($clientId, $fromDate = null, $toDate = null)
    {
        $client = Client::findOrFail($clientId);

        $ledger = [];
        $runningBalance = (float) $client->opening_balance;

        // Opening Balance
        $ledger[] = [
            'date' => null,
            'type' => 'Opening Balance',
            'debit' => 0,
            'credit' => 0,
            'balance' => round($runningBalance, 2)
        ];

        /*
        |--------------------------------------------------------------------------
        | Invoices (Debit)
        |--------------------------------------------------------------------------
        */
        $invoices = Invoice::where('client_id', $clientId)
            ->when($fromDate, fn($q) => $q->whereDate('invoice_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('invoice_date', '<=', $toDate))
            ->get()
            ->map(function ($inv) {
                $date = Carbon::parse($inv->invoice_date);

                return [
                    'timestamp' => $date->timestamp,
                    'display_date' => $date->format('d M Y'),
                    'type' => 'Invoice - ' . $inv->invoice_no,
                    'debit' => (float) $inv->total_amount,
                    'credit' => 0
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Payments (Credit)
        |--------------------------------------------------------------------------
        */
        $payments = InvoicePayment::whereHas('invoice', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            })
            ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->get()
            ->map(function ($pay) {
                $date = Carbon::parse($pay->payment_date);

                // Build type string safely
                $type = 'Payment';

                if (!empty($pay->payment_method)) {
                    $type .= ' - ' . strtoupper($pay->payment_method);
                }

                if (!empty($pay->transaction_id)) {
                    $type .= ' (' . $pay->transaction_id . ')';
                }

                return [
                    'timestamp' => $date->timestamp,
                    'display_date' => $date->format('d M Y'),
                    'type' => $type,
                    'debit' => 0,
                    'credit' => (float) $pay->amount
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Merge + Sort by timestamp
        |--------------------------------------------------------------------------
        */
        $transactions = collect($invoices)
            ->merge($payments)
            ->sortBy('timestamp')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Running Balance Calculation
        |--------------------------------------------------------------------------
        */
        foreach ($transactions as $trx) {
            $runningBalance += $trx['debit'];
            $runningBalance -= $trx['credit'];

            $ledger[] = [
                'date' => $trx['display_date'],
                'type' => $trx['type'],
                'debit' => round($trx['debit'], 2),
                'credit' => round($trx['credit'], 2),
                'balance' => round($runningBalance, 2)
            ];
        }

        return [
            'client' => $client->company_name,
            'opening_balance' => (float) $client->opening_balance,
            'closing_balance' => round($runningBalance, 2),
            'ledger' => $ledger
        ];
    }
}
