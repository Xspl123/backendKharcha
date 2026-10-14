<?php

namespace App\Repositories;

use App\Models\InvoicePayment;
use App\Models\OrgAccount;
use App\Models\OrgTransaction;
use App\Repositories\Interfaces\InvoicePaymentRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Facades\DB;

class InvoicePaymentRepository implements InvoicePaymentRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getAll(array $filters = [])
    {
        return $this->scopeQuery(InvoicePayment::query())
            ->with(['invoice.client'])->latest('payment_date')
            ->paginate($this->resolvePerPage($filters, 50));
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $payment = InvoicePayment::create($this->scopeData($data))
                ->load(['invoice.client']);

            // 👇 Auto-credit Org Account & Auto-record in Cashbook
            if (!empty($data['account_id'])) {
                $account = OrgAccount::find($data['account_id']);
                if ($account) {
                    $amount = (float) $payment->amount;
                    $account->increment('current_balance', $amount);
                    OrgTransaction::create($this->scopeData([
                        'account_id'         => $account->id,
                        'transaction_date'   => $payment->payment_date,
                        'type'               => 'credit',
                        'category'           => 'client_payment',
                        'amount'             => $amount,
                        'balance_after'      => (float) $account->fresh()->current_balance,
                        'payment_method'     => $payment->payment_method ?? 'bank_transfer',
                        'reference_no'       => $payment->transaction_id ?? null,
                        'description'        => 'Client Payment: ' . ($payment->invoice?->client?->company_name ?? 'Client') . ' (' . ($payment->invoice?->invoice_no ?? 'Invoice') . ')',
                        'invoice_payment_id' => $payment->id,
                    ]));
                }
            }

            $this->bumpScopedCache(['invoices', 'clients', 'gst', 'org_accounts', 'org_transactions']);
            return $payment;
        });
    }

    public function show($id)
    {
        return $this->scopeQuery(InvoicePayment::query())
            ->with(['invoice.client'])->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $payment = $this->show($id);
        $payment->update($data);
        $this->bumpScopedCache(['invoices', 'clients', 'gst']);
        return $payment->load(['invoice.client']);
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $payment = $this->show($id);
            if ($payment->account_id) {
                $account = OrgAccount::find($payment->account_id);
                if ($account) {
                    $account->decrement('current_balance', (float) $payment->amount);
                    OrgTransaction::where('invoice_payment_id', $payment->id)->delete();
                }
            }
            $deleted = $payment->delete();
            $this->bumpScopedCache(['invoices', 'clients', 'gst', 'org_accounts', 'org_transactions']);
            return $deleted;
        });
    }

    public function getByInvoice($invoiceId, array $filters = [])
    {
        return $this->scopeQuery(InvoicePayment::query())
            ->where('invoice_id', $invoiceId)
            ->orderBy('payment_date', 'desc')
            ->paginate($this->resolvePerPage($filters, 50));
    }
}