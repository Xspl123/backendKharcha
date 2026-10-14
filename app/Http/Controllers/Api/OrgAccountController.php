<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrgAccountRequest;
use App\Http\Resources\OrgAccountResource;
use App\Repositories\OrgAccountRepository;
use Illuminate\Http\Request;

class OrgAccountController extends Controller
{
    public function __construct(private OrgAccountRepository $accountRepo) {}

    public function index(Request $request)
    {
        $accounts = $this->accountRepo->getAll($request->only(['search', 'account_type', 'is_active']));
        return response()->json(['data' => OrgAccountResource::collection($accounts)]);
    }

    public function summary()
    {
        return response()->json(['data' => $this->accountRepo->getSummary()]);
    }

    public function store(StoreOrgAccountRequest $request)
    {
        $account = $this->accountRepo->create($request->validated());
        return response()->json([
            'message' => 'Organisation account created successfully',
            'data'    => new OrgAccountResource($account),
        ], 201);
    }

    public function show($id)
    {
        return new OrgAccountResource($this->accountRepo->getById((int) $id));
    }

    public function update(StoreOrgAccountRequest $request, $id)
    {
        $account = $this->accountRepo->update((int) $id, $request->validated());
        return response()->json([
            'message' => 'Organisation account updated successfully',
            'data'    => new OrgAccountResource($account),
        ]);
    }

    public function destroy($id)
    {
        $this->accountRepo->delete((int) $id);
        return response()->json(['message' => 'Organisation account deleted successfully']);
    }
}