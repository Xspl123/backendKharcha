<?php

namespace App\Services;

use App\Repositories\Interfaces\AccountRepositoryInterface;
use App\Models\Account;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountService
{
    private AccountRepositoryInterface $accountRepository;

    public function __construct(AccountRepositoryInterface $accountRepository)
    {
        $this->accountRepository = $accountRepository;
    }

    public function createAccount(array $data): Account
    {
        return $this->accountRepository->create($data);
    }

    public function getUserAccounts(int $userId, ?string $search = null, int $page = 1, int $limit = 10): LengthAwarePaginator
    {
        return $this->accountRepository->getAll($userId, $search, $page, $limit);
    }
    

    public function getAccountById(int $userId, int $id): ?Account
    {
        return $this->accountRepository->findById($userId, $id);
    }

    public function updateAccount(int $userId, int $id, array $data): bool
    {
        return $this->accountRepository->update($userId, $id, $data);
    }

    public function deleteAccount(int $userId, int $id): bool
    {
        return $this->accountRepository->delete($userId, $id);
    }
}
