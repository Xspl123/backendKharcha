<?php

namespace App\Repositories;

use App\Models\OrgAccount;
use App\Models\OrgTransaction;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Facades\DB;

class OrgAccountRepository
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getAll(array $filters = []): mixed
    {
        $query = $this->scopeQuery(OrgAccount::query())
            ->orderByDesc('is_default')
            ->orderBy('account_name');

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn($q) => $q
                ->where('account_name', 'like', "%{$s}%")
                ->orWhere('bank_name', 'like', "%{$s}%")
                ->orWhere('account_number', 'like', "%{$s}%")
                ->orWhere('upi_id', 'like', "%{$s}%")
            );
        }

        if (!empty($filters['account_type']) && $filters['account_type'] !== 'all') {
            $query->where('account_type', $filters['account_type']);
        }

        if (!empty($filters['paginate']) || !empty($filters['per_page'])) {
            return $query->paginate($this->resolvePerPage($filters, 20));
        }

        return $query->get();
    }

    public function getSummary(): array
    {
        $accounts = $this->scopeQuery(OrgAccount::query())->get();

        $totalBalance = (float) $accounts->sum('current_balance');
        $bankBalance  = (float) $accounts->where('account_type', 'bank')->sum('current_balance');
        $cashBalance  = (float) $accounts->where('account_type', 'cash')->sum('current_balance');
        $upiBalance   = (float) $accounts->where('account_type', 'upi')->sum('current_balance');

        return [
            'total_balance'   => $totalBalance,
            'bank_balance'    => $bankBalance,
            'cash_balance'    => $cashBalance,
            'upi_balance'     => $upiBalance,
            'total_accounts'  => $accounts->count(),
            'active_accounts' => $accounts->count(),
        ];
    }

    public function getById(int $id): OrgAccount
    {
        return $this->scopeQuery(OrgAccount::query())
            ->with(['transactions' => fn($q) => $q->latest()->take(20)])
            ->findOrFail($id);
    }

    public function create(array $data): OrgAccount
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['is_default'])) {
                $this->scopeQuery(OrgAccount::query())->update(['is_default' => false]);
            }

            // Map 'name' to 'account_name' if needed and remove 'name' so MySQL doesn't error
            if (isset($data['name']) && empty($data['account_name'])) {
                $data['account_name'] = $data['name'];
            }
            unset($data['name']);

            $opening = isset($data['opening_balance']) ? (float) $data['opening_balance'] : 0;
            $data['current_balance'] = $opening;

            $account = OrgAccount::create($this->scopeData($data));

            if ($opening > 0) {
                OrgTransaction::create($this->scopeData([
                    'account_id'       => $account->id,
                    'transaction_date' => now()->toDateString(),
                    'type'             => 'credit',
                    'category'         => 'opening_balance',
                    'amount'           => $opening,
                    'balance_after'    => $opening,
                    'payment_method'   => $account->account_type === 'cash' ? 'cash' : 'bank_transfer',
                    'description'      => 'Opening Balance for ' . ($account->account_name ?? 'Account'),
                ]));
            }

            $this->bumpScopedCache(['org_accounts', 'org_transactions']);
            return $account;
        });
    }

    public function update(int $id, array $data): OrgAccount
    {
        return DB::transaction(function () use ($id, $data) {
            $account = $this->scopeQuery(OrgAccount::query())->findOrFail($id);

            if (!empty($data['is_default']) && !$account->is_default) {
                $this->scopeQuery(OrgAccount::query())->where('id', '!=', $id)->update(['is_default' => false]);
            }

            if (isset($data['name']) && empty($data['account_name'])) {
                $data['account_name'] = $data['name'];
            }
            unset($data['name']);
            unset($data['current_balance']);

            $account->update($data);
            $this->bumpScopedCache(['org_accounts']);
            return $account->fresh();
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $account = $this->scopeQuery(OrgAccount::query())->findOrFail($id);
            $deleted = $account->delete();
            $this->bumpScopedCache(['org_accounts', 'org_transactions']);
            return $deleted;
        });
    }
}