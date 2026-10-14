<?php

namespace App\Repositories;

use App\Models\OrgAccount;
use App\Models\OrgTransaction;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Facades\DB;

class OrgTransactionRepository
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getAll(array $filters = []): mixed
    {
        $query = $this->scopeQuery(OrgTransaction::query())
            ->with(['account', 'transferAccount'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if (!empty($filters['account_id']) && $filters['account_id'] !== 'all') {
            $query->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn($q) => $q
                ->where('description', 'like', "%{$s}%")
                ->orWhere('reference_no', 'like', "%{$s}%")
                ->orWhere('category', 'like', "%{$s}%")
            );
        }

        if (isset($filters['paginate']) && !$filters['paginate']) {
            return $query->get();
        }

        return $query->paginate($this->resolvePerPage($filters, 50));
    }

    public function getCashflowSummary(array $filters = []): array
    {
        $query = $this->scopeQuery(OrgTransaction::query());

        if (!empty($filters['from_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['account_id']) && $filters['account_id'] !== 'all') {
            $query->where('account_id', $filters['account_id']);
        }

        $isAllAccounts = empty($filters['account_id']) || $filters['account_id'] === 'all';

        $creditQuery = (clone $query)->where('type', 'credit');
        $debitQuery  = (clone $query)->where('type', 'debit');

        if ($isAllAccounts) {
            $creditQuery->where('category', '!=', 'transfer');
            $debitQuery->where('category', '!=', 'transfer');
        }

        $totalInflow  = (float) $creditQuery->sum('amount');
        $totalOutflow = (float) $debitQuery->sum('amount');
        $netFlow      = $totalInflow - $totalOutflow;

        return [
            'total_inflow'  => $totalInflow,
            'total_outflow' => $totalOutflow,
            'net_flow'      => $netFlow,
        ];
    }

    public function create(array $data): OrgTransaction
    {
        return DB::transaction(function () use ($data) {
            $amount = (float) $data['amount'];

            // 1. Handle Internal Contra Transfer (Between two company accounts)
            if (!empty($data['transfer_account_id'])) {
                $fromAccount = $this->scopeQuery(OrgAccount::query())->findOrFail($data['account_id']);
                $toAccount   = $this->scopeQuery(OrgAccount::query())->findOrFail($data['transfer_account_id']);

                $fromName = $fromAccount->account_name ?? $fromAccount->name ?? 'Account';
                $toName   = $toAccount->account_name ?? $toAccount->name ?? 'Account';

                // Deduct from Source Account
                $fromAccount->decrement('current_balance', $amount);
                $fromBalanceAfter = (float) $fromAccount->fresh()->current_balance;

                $fromTx = OrgTransaction::create($this->scopeData([
                    'account_id'          => $fromAccount->id,
                    'transfer_account_id' => $toAccount->id,
                    'transaction_date'    => $data['transaction_date'],
                    'type'                => 'debit',
                    'category'            => 'transfer',
                    'amount'              => $amount,
                    'balance_after'       => $fromBalanceAfter,
                    'payment_method'      => $data['payment_method'] ?? 'bank_transfer',
                    'reference_no'        => $data['reference_no'] ?? null,
                    'description'         => $data['description'] ?: "Transfer to {$toName}",
                ]));

                // Add to Destination Account
                $toAccount->increment('current_balance', $amount);
                $toBalanceAfter = (float) $toAccount->fresh()->current_balance;

                $toTx = OrgTransaction::create($this->scopeData([
                    'account_id'          => $toAccount->id,
                    'transfer_account_id' => $fromAccount->id,
                    'transaction_date'    => $data['transaction_date'],
                    'type'                => 'credit',
                    'category'            => 'transfer',
                    'amount'              => $amount,
                    'balance_after'       => $toBalanceAfter,
                    'payment_method'      => $data['payment_method'] ?? 'bank_transfer',
                    'reference_no'        => $data['reference_no'] ?? null,
                    'description'         => $data['description'] ?: "Transfer from {$fromName}",
                ]));

                $this->bumpScopedCache(['org_accounts', 'org_transactions']);
                return $fromTx->load(['account', 'transferAccount']);
            }

            // 2. Normal Transaction
            $account = $this->scopeQuery(OrgAccount::query())->findOrFail($data['account_id']);

            if ($data['type'] === 'credit') {
                $account->increment('current_balance', $amount);
            } else {
                $account->decrement('current_balance', $amount);
            }

            $balanceAfter = (float) $account->fresh()->current_balance;

            $tx = OrgTransaction::create($this->scopeData([
                'account_id'       => $account->id,
                'transaction_date' => $data['transaction_date'],
                'type'             => $data['type'],
                'category'         => $data['category'],
                'amount'           => $amount,
                'balance_after'    => $balanceAfter,
                'payment_method'   => $data['payment_method'] ?? 'bank_transfer',
                'reference_no'     => $data['reference_no'] ?? null,
                'description'      => $data['description'] ?? null,
            ]));

            $this->bumpScopedCache(['org_accounts', 'org_transactions']);
            return $tx->load(['account']);
        });
    }
}