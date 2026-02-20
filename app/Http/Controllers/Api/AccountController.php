<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Http\Requests\AccountRequest;
use App\Http\Requests\AccountUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
class AccountController extends Controller
{
    private AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $search = $request->query('search');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
    
        $accounts = $this->accountService->getUserAccounts($userId, $search, $page, $limit);
    
        return response()->json($accounts, 200);
    }

    public function store(AccountRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $data = array_merge($request->validated(), ['user_id' => $userId]);

        $account = $this->accountService->createAccount($data);
        return response()->json(['message' => 'Account created successfully', 'data' => $account], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $account = $this->accountService->getAccountById($userId, $id);

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        return response()->json(['data' => $account]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $updated = $this->accountService->updateAccount($userId, $id, $request->all());

        if (!$updated) {
            return response()->json(['message' => 'Failed to update account'], 400);
        }

        return response()->json(['message' => 'Account updated successfully']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $deleted = $this->accountService->deleteAccount($userId, $id);

        if (!$deleted) {
            return response()->json(['message' => 'Failed to delete account'], 400);
        }

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
