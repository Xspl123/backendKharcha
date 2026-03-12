<?php

namespace App\Repositories\Interfaces;

interface PurchaseReturnRepositoryInterface
{
    public function create(array $data);
    public function find(int $id);
    public function getAll(array $filters = []);
    public function getByPO(int $poId);
    public function updateStatus(int $id, string $status);
}