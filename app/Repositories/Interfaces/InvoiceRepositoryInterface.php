<?php

namespace App\Repositories\Interfaces;

interface InvoiceRepositoryInterface
{
    public function getAll(array $filters = []);
    public function store(array $data);
    public function show($id);
    public function update($id, array $data);
    public function delete($id);
    public function getNextInvoiceNumber();
    public function getByClient($clientId);
}
?>