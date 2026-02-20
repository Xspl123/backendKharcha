<?php

namespace App\Repositories;

use App\Repositories\Interfaces\AccountRepositoryInterface;
use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountRepository implements AccountRepositoryInterface
{
    public function create(array $data): Account
    {
        return Account::create($data);
    }

    public function getAll(int $userId, ?string $search = null, int $page = 1, int $limit = 10): LengthAwarePaginator
    {
        return Account::where('user_id', $userId)
            ->when($search, fn($query) => $query->where('name', 'like', "%$search%"))
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
    }
    

    public function findById(int $userId, int $id): ?Account
    {
        return Account::where('user_id', $userId)->where('id', $id)->first();
    }

    public function update(int $userId, int $id, array $data): bool
    {
        $account = Account::where('user_id', $userId)->where('id', $id)->first();
        return $account ? $account->fill($data)->save() : false;
    }

    public function delete(int $userId, int $id): bool
    {
        $account = Account::where('user_id', $userId)->where('id', $id)->first();
        return $account ? $account->delete() : false;
    }
}
