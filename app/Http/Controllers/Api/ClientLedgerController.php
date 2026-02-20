<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientLedgerRequest;
use App\Http\Resources\ClientLedgerResource;
use App\Repositories\Interfaces\ClientLedgerRepositoryInterface;

class ClientLedgerController extends Controller
{
    private $ledgerRepo;

    public function __construct(ClientLedgerRepositoryInterface $ledgerRepo)
    {
        $this->ledgerRepo = $ledgerRepo;
    }

    public function show($clientId, ClientLedgerRequest $request)
    {
        $data = $this->ledgerRepo->getLedger(
            $clientId,
            $request->from_date,
            $request->to_date
        );

        return new ClientLedgerResource($data);
    }
}
