<?php

namespace App\Repositories\Interfaces;

interface SalesReturnRepositoryInterface
{
    public function create(array $data);
    public function find(int $id);
    public function getAll(array $filters = []);
    public function getByInvoice(int $invoiceId);
    public function updateStatus(int $id, string $status);
}