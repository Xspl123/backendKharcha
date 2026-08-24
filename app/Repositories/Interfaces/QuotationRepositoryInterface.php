<?php

namespace App\Repositories\Interfaces;

interface QuotationRepositoryInterface
{
    public function getAll(array $filters): mixed;
    public function getById(int $id): mixed;
    public function create(array $data): mixed;
    public function update(int $id, array $data): mixed;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status): mixed;
}
