<?php

namespace App\Repositories\Interfaces;

interface ProductCategoryRepositoryInterface
{
    public function getAll(): mixed;
    public function getById(int $id): mixed;
    public function create(array $data): mixed;
    public function update(int $id, array $data): mixed;
    public function delete(int $id): bool;
}