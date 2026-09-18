<?php

namespace App\Repositories\Interfaces;

use App\Models\Quotation;

interface QuotationRepositoryInterface
{
    public function getAll(array $filters): mixed;
    public function getById(int $id): mixed;
    public function create(array $data): mixed;
    public function reviseQuotation(int $id, array $data): mixed;
    public function update(int $id, array $data): mixed;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status): mixed;
    public function applyQuotationWorkflowRules(Quotation $quotation, string $newStatus): void;
}