<?php

namespace App\Repositories\Interfaces;

interface VendorPaymentRepositoryInterface
{
    public function getByPurchaseOrder(int $purchaseOrderId): mixed;
    public function getByVendor(int $vendorId): mixed;
    public function create(array $data): mixed;
    public function delete(int $id): bool;
}