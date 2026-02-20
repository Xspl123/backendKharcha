<?php

namespace App\Repositories\Interfaces;

interface StockMovementRepositoryInterface
{
    public function getByProduct(int $productId, array $filters): mixed;
    public function getAll(array $filters): mixed;
    public function create(array $data): mixed;
    public function delete(int $id): bool;
    public function getReport(array $filters): array;
}