<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorLedgerRequest;
use App\Http\Resources\VendorLedgerResource;
use App\Repositories\Interfaces\VendorLedgerRepositoryInterface;

class VendorLedgerController extends Controller
{
    public function __construct(
        private VendorLedgerRepositoryInterface $ledgerRepo
    ) {}

    public function show($vendorId, VendorLedgerRequest $request)
    {
        $data = $this->ledgerRepo->getLedger(
            (int)$vendorId,
            $request->from_date,
            $request->to_date
        );

        return new VendorLedgerResource($data);
    }
}