<?php

namespace App\Services;

use App\Repositories\Interfaces\BudgetRepositoryInterface;
use App\Models\Budget;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class BudgetService
{
    private BudgetRepositoryInterface $budgetRepository;

    public function __construct(BudgetRepositoryInterface $budgetRepository)
    {
        $this->budgetRepository = $budgetRepository;
    }

    public function createBudget(array $data): ?Budget
    {
        try {
            return $this->budgetRepository->create($data);
        } catch (\Exception $e) {
            Log::error('Error creating budget: ' . $e->getMessage());
            return null;
        }
    }

    public function getUserBudgets(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->budgetRepository->getAll($userId, $filters);
    }

    public function getBudgetById(int $userId, int $id): ?Budget
    {
        try {
            return $this->budgetRepository->findById($userId, $id);
        } catch (ModelNotFoundException $e) {
            Log::warning("Budget ID $id not found for user $userId");
            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching budget ID $id for user $userId: " . $e->getMessage());
            return null;
        }
    }

 
    public function updateBudget(int $userId, int $categoryId, float $budgetAmount)
    {
        $budget = Budget::where('category_id', $categoryId)
                        ->where('user_id', $userId)
                        ->first();

        if (!$budget) {
            // Create new budget if not exists
            $budget = Budget::create([
                'user_id' => $userId,
                'category_id' => $categoryId,
                'budget_amount' => $budgetAmount,
            ]);

            return $budget;
        }

        // Update existing budget
        $updated = $this->budgetRepository->update($categoryId, ['budget_amount' => $budgetAmount]);

        if (!$updated) {
            return ['error' => 'Failed to update budget'];
        }

        return $budget->fresh();
    }


    public function deleteBudget(int $userId, int $id): bool
    {
        try {
            return $this->budgetRepository->delete($userId, $id);
        } catch (ModelNotFoundException $e) {
            Log::warning("Budget ID $id not found for deletion (user $userId)");
            return false;
        } catch (\Exception $e) {
            Log::error("Error deleting budget ID $id for user $userId: " . $e->getMessage());
            return false;
        }
    }
}
