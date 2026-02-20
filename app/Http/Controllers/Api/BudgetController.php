<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\BudgetRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class BudgetController extends Controller
{
    /**
     * @var BudgetService
     */
    private $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Create a new budget entry
     */
    public function store(BudgetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::user()->id;
        $budget = $this->budgetService->createBudget($data);

        return response()->json([
            'message' => 'Budget created successfully',
            'data' => $budget
        ], Response::HTTP_CREATED);
    }

    /**
     * Fetch all budgets for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $filters = [
            'search' => $request->query('search', null),
            'page'   => $request->query('page', 1),
        ];

        $budgets = $this->budgetService->getUserBudgets($userId, $filters);

        return response()->json([
            'message' => 'Budgets retrieved successfully',
            'data'    => $budgets
        ], Response::HTTP_OK);
    }


    /**
     * Fetch a specific budget entry
     */
    public function show($id): JsonResponse
    {
        $userId =Auth::user()->id;
        $budget = $this->budgetService->getBudgetById($userId, $id);

        if (!$budget) {
            return response()->json(['message' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'Budget retrieved successfully',
            'data' => $budget
        ], Response::HTTP_OK);
    }

   

    public function update(Request $request, $categoryId)
    {
        $validated = $request->validate([
            'budget_amount' => 'required|numeric',
        ]);

        $userId = Auth::id();
        $result = $this->budgetService->updateBudget($userId, $categoryId, $validated['budget_amount']);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json([
            'message' => 'Budget amount updated successfully',
            'budget' => $result
        ], 200);
    }


    /**
     * Delete a budget entry
     */
    public function destroy($id): JsonResponse
    {
        $userId =Auth::user()->id;
        $deleted = $this->budgetService->deleteBudget($userId, $id);

        if (!$deleted) {
            return response()->json(['message' => 'Budget deletion failed'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'message' => 'Budget deleted successfully'
        ], Response::HTTP_OK);
    }

    public function myLoans()
    {
        $user = Auth::user();

        $loans = DB::table('loan_ledgers')
            ->where('user_id', $user->id)
            ->get();

        $loansWithTransactions = $loans->map(function ($loan) {
            $transactions = DB::table('loan_ledger_transactions')
                ->where('loan_id', $loan->id)
                ->select('id', 'transaction_type', 'amount', 'transaction_date', 'created_at', 'transaction_type')
                ->orderBy('transaction_date', 'desc')
                ->get();

            $loan->transactions = $transactions;
            return $loan;
        });

        return response()->json($loansWithTransactions);
    }


    

    
}
