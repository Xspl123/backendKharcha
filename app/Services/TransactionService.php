<?php

namespace App\Services;

use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Repositories\Interfaces\AccountRepositoryInterface;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\Budget;
use App\Models\Account;
use Carbon\Carbon;


class TransactionService
{
    protected $transactionRepository;
    protected $accountRepository;
    protected $categoryRepository;

    public function __construct(TransactionRepositoryInterface $transactionRepository, AccountRepositoryInterface $accountRepository, CategoryRepositoryInterface $categoryRepository)
    {
        $this->transactionRepository = $transactionRepository;
        $this->accountRepository = $accountRepository;
        $this->categoryRepository = $categoryRepository;
    }


    public function createTransaction(array $data)
    {
        $data['user_id'] = Auth::id();
        $data['person_name'] = $data['description'] ?? 'Unknown';
        $currentMonth = Carbon::now()->format('Y-m');

        //  Find account
        $account = $this->accountRepository->findById($data['user_id'], $data['account_id']);
        if (!$account) return ['error' => 'Invalid account!'];

        //  Find category
        $category = $this->categoryRepository->findById($data['user_id'], $data['category_id']);
        if (!$category) return ['error' => 'Invalid category!'];

        //  Budget check only for expense
        $budget = null;
        $newTotalAmount = 0;

        if ($category->type === 'expense') {
            $budget = $this->transactionRepository->findBudgetByCategoryAndMonth(
                $data['user_id'], $data['category_id'], $currentMonth
            );

            $newTotalAmount = ($budget->total_amount ?? 0) + $data['amount'];

            if ($budget && $budget->budget_amount > 0 && $newTotalAmount > $budget->budget_amount) {
                return ['error' => 'Budget exceeded for this category in ' . $currentMonth];
            }
        }

        switch (strtolower($category->type)) {
            case 'expense':
            case 'saving':
            case 'borrow_return':
                if ($account->account_balance < $data['amount']) {
                    return ['error' => 'Insufficient balance in account!'];
                }
                $account->account_balance -= $data['amount'];
                break;

            case 'income':
            case 'borrow':
            case 'reimbursement':    
                $account->account_balance += $data['amount'];
                break;
            case 'transfer':
                $transferTo = $data['transfer_to'] ?? null;

                if (!$transferTo) {
                    return ['error' => 'To Account is required for transfer transactions!'];
                }

                if ($account->account_balance < $data['amount']) {
                    return ['error' => 'Insufficient balance in from-account!'];
                }
                $account->account_balance -= $data['amount'];
                $account->save();
                $toAccount = Account::find($transferTo);
                if (!$toAccount) {
                    return ['error' => 'To Account not found!'];
                }
                $toAccount->account_balance += $data['amount'];
                $toAccount->save();
                break;
        
            case 'special':
            default:
                break;
        }

        $account->save();

        //  Create transaction
        $transaction = $this->transactionRepository->create($data);

        //  Update budget
        if ($budget) {
            $this->transactionRepository->updateBudget($budget, $newTotalAmount);
        } elseif ($category->type === 'expense') {
            $this->transactionRepository->createBudget([
                'user_id' => $data['user_id'],
                'category_id' => $data['category_id'],
                'total_amount' => $data['amount'],
                'budget_amount' => 0,
                'month' => $currentMonth,
            ]);
        }

        // Loan Ledger Management
        if (in_array($category->type, ['borrow', 'borrow_return'])) {
            $this->updateLoanLedger($data, $category->type);
        }

        return $transaction;
    }

    public function updateLoanLedger(array $data, string $type)
    {
        // Ledger find karo
        $ledger = DB::table('loan_ledgers')
            ->where('user_id', $data['user_id'])
            ->where('person_name', $data['description'])
            ->first();

        if ($type === 'borrow') {
            if ($ledger) {
                DB::table('loan_ledgers')->where('id', $ledger->id)->update([
                    'total_borrowed' => $ledger->total_borrowed + $data['amount'],
                    'balance'        => $ledger->balance + $data['amount'],
                    'status'         => 'pending',
                    'updated_at'     => now(),
                ]);

                $loanLedgerId = $ledger->id;
            } else {
                $loanLedgerId = DB::table('loan_ledgers')->insertGetId([
                    'user_id'        => $data['user_id'],
                    'person_name'    => $data['description'],
                    'total_borrowed' => $data['amount'],
                    'total_returned' => 0,
                    'balance'        => $data['amount'],
                    'status'         => 'pending',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // History record save in loan_ledger_transactions
            DB::table('loan_ledger_transactions')->insert([
                'loan_id'          => $loanLedgerId,
                'transaction_type' => 'borrow',
                'amount'           => $data['amount'],
                'transaction_date' => now()->toDateString(),
                'created_at'       => now(),
            ]);
        }

        if ($type === 'borrow_return') {
            if ($ledger) {
                $newReturned = $ledger->total_returned + $data['amount'];
                $newBalance  = $ledger->balance - $data['amount'];
                $status      = $newBalance <= 0 ? 'cleared' : 'pending';

                DB::table('loan_ledgers')->where('id', $ledger->id)->update([
                    'total_returned' => $newReturned,
                    'balance'        => $newBalance,
                    'status'         => $status,
                    'updated_at'     => now(),
                ]);

                $loanLedgerId = $ledger->id;
            } else {
                $loanLedgerId = DB::table('loan_ledgers')->insertGetId([
                    'user_id'        => $data['user_id'],
                    'person_name'    => $data['description'],
                    'total_borrowed' => 0,
                    'total_returned' => $data['amount'],
                    'balance'        => -$data['amount'],
                    'status'         => 'pending',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // History record save in loan_ledger_transactions
            DB::table('loan_ledger_transactions')->insert([
                'loan_id'          => $loanLedgerId,
                'transaction_type' => 'repay',
                'amount'           => $data['amount'],
                'transaction_date' => now()->toDateString(),
                'created_at'       => now(),
            ]);
        }
    }

    public function getTransactions($filters)
    {
        return $this->transactionRepository->getAllTransactions($filters);
    }

    public function updateTransaction($id, array $data)
    {
        $transaction = $this->transactionRepository->findById($id);
        if (!$transaction) return ['error' => 'Transaction not found'];

        return $this->transactionRepository->update($id, $data);
    }


    public function deleteTransaction($id)
    {
        // Find the transaction
        $transaction = $this->transactionRepository->findById($id);
        if (!$transaction) {
            return ['error' => 'Transaction not found'];
        }

        // Find the FROM account
        $account = $this->accountRepository->findById(Auth::id(), $transaction->account_id);
        if (!$account) {
            return ['error' => 'Invalid account!'];
        }

        // Find budget
        $budget = $this->transactionRepository->findBudgetByCategory($transaction->category_id);

        // Handle rollback based on transaction type
        switch (strtolower($transaction->category->type)) {

            // EXPENSE / SAVING / BORROW RETURN → add back money
            case 'expense':
            case 'saving':
            case 'borrow_return': 
                $account->account_balance += $transaction->amount; 
                $account->save();
                break;

            // INCOME / BORROW / REIMBURSEMENT → subtract money
            case 'income':
            case 'borrow':
            case 'reimbursement':
                $account->account_balance -= $transaction->amount;
                $account->save();
                break;

            // NEW: TRANSFER ROLLBACK
            case 'transfer':

                // FROM account rollback: add back money
                $account->account_balance += $transaction->amount;
                $account->save();

                // TO account rollback: deduct money
                if ($transaction->transfer_to) {
                    $toAccount = Account::find($transaction->transfer_to);

                    if ($toAccount) {
                        $toAccount->account_balance -= $transaction->amount;
                        $toAccount->save();
                    }
                }

                break;
        }

        // Budget rollback
        if ($budget) {
            $newTotalAmount = $budget->total_amount - $transaction->amount;
            $this->transactionRepository->updateBudget($budget, $newTotalAmount);
        }

        // Loan Ledger rollback
        if (in_array($transaction->category->type, ['borrow', 'borrow_return'])) {
            $this->rollbackLoanLedger($transaction);
        }

        // Delete the transaction
        return $this->transactionRepository->delete($id);
    }


    protected function rollbackLoanLedger($transaction)
    {
        $ledger = DB::table('loan_ledgers')
            ->where('user_id', $transaction->user_id)
            ->where('person_name', $transaction->description)
            ->first();

        if (!$ledger) return;

        $oldUpdatedAt = $ledger->updated_at;

        if ($transaction->category->type === 'borrow') {
            $newBorrowed = max(0, $ledger->total_borrowed - $transaction->amount);
            $newBalance  = max(0, $ledger->balance - $transaction->amount);

            DB::table('loan_ledgers')->where('id', $ledger->id)->update([
                'total_borrowed' => $newBorrowed,
                'balance'        => $newBalance,
                'status'         => $newBalance <= 0 ? 'cleared' : 'pending',
                'updated_at'     => $oldUpdatedAt,
            ]);

            $lastTxn = DB::table('loan_ledger_transactions')
                ->where('loan_id', $ledger->id)
                ->where('transaction_type', 'borrow')
                ->where('amount', $transaction->amount)
                ->orderByDesc('id')
                ->first();

            if ($lastTxn) {
                DB::table('loan_ledger_transactions')->where('id', $lastTxn->id)->delete();
            }
        }

        if ($transaction->category->type === 'borrow_return') {
            $newReturned = max(0, $ledger->total_returned - $transaction->amount);
            $newBalance  = $ledger->balance + $transaction->amount;

            DB::table('loan_ledgers')->where('id', $ledger->id)->update([
                'total_returned' => $newReturned,
                'balance'        => $newBalance,
                'status'         => $newBalance <= 0 ? 'cleared' : 'pending',
                'updated_at'     => $oldUpdatedAt,
            ]);

            $lastTxn = DB::table('loan_ledger_transactions')
                ->where('loan_id', $ledger->id)
                ->where('transaction_type', 'repay')
                ->where('amount', $transaction->amount)
                ->orderByDesc('id')
                ->first();

            if ($lastTxn) {
                DB::table('loan_ledger_transactions')->where('id', $lastTxn->id)->delete();
            }
        }
    }

    


}
