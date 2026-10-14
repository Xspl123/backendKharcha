<?php

namespace App\Repositories\Interfaces;

interface VendorLedgerRepositoryInterface
{
    public function getLedger(int $vendorId, ?string $fromDate = null, ?string $toDate = null): array;
}