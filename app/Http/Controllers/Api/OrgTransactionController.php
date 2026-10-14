<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrgTransactionRequest;
use App\Http\Resources\OrgTransactionResource;
use App\Repositories\OrgTransactionRepository;
use Illuminate\Http\Request;

class OrgTransactionController extends Controller
{
    public function __construct(private OrgTransactionRepository $trxRepo) {}

    public function index(Request $request)
    {
        $transactions = $this->trxRepo->getAll($request->only([
            'account_id', 'type', 'category', 'from_date', 'to_date', 'search', 'per_page'
        ]));
        return OrgTransactionResource::collection($transactions);
    }

    public function cashflow(Request $request)
    {
        return response()->json([
            'data' => $this->trxRepo->getCashflowSummary($request->only(['account_id', 'from_date', 'to_date'])),
        ]);
    }

    public function store(StoreOrgTransactionRequest $request)
    {
        $trx = $this->trxRepo->create($request->validated());
        return response()->json([
            'message' => 'Transaction recorded successfully',
            'data'    => new OrgTransactionResource($trx),
        ], 201);
    }
}