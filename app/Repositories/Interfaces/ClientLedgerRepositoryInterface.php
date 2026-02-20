<?php

namespace App\Repositories\Interfaces;

interface ClientLedgerRepositoryInterface
{
    public function getLedger($clientId, $fromDate = null, $toDate = null);
}
