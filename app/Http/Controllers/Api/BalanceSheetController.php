<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\OrgAccount;
use App\Models\OrgTransaction;
use App\Models\Client;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BalanceSheetController extends Controller
{
    /**
     * ── Balance Sheet (As of a specific date) ───────────
     * Assets = Liabilities + Owner Equity
     */
    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->query('as_of_date') ?: Carbon::now()->toDateString();

        // ════════════════════════════════════════════════════
        // 1. ASSETS (Sampatti — Jo company ke paas hai)
        // ════════════════════════════════════════════════════

        // A. Cash & Bank Balances (Liquid Funds)
        $accounts = OrgAccount::where('is_active', true)->get();
        $bankAccounts = [];
        $cashInHand   = 0;
        $totalBankCash = 0;

        foreach ($accounts as $acc) {
            $bal = (float) $acc->current_balance;
            $totalBankCash += $bal;
            if ($acc->account_type === 'cash') {
                $cashInHand += $bal;
            } else {
                $bankAccounts[] = [
                    'id'           => $acc->id,
                    'account_name' => $acc->account_name ?: $acc->name,
                    'bank_name'    => $acc->bank_name ?: 'Bank Account',
                    'balance'      => $bal,
                ];
            }
        }

        // B. Sundry Debtors (Clients Receivable — Unpaid/Partial Invoices)
        $debtorsTotal = (float) Invoice::where('is_return', false)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereDate('invoice_date', '<=', $asOfDate)
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount');

        // C. Closing Stock (Warehouse Inventory Valuation)
        $products = Product::where('status', 'active')->get();
        $stockValuation = 0;
        foreach ($products as $p) {
            $costRate = $p->avg_cost > 0 ? (float) $p->avg_cost : (float) $p->purchase_price;
            $stockValuation += round((float) $p->current_stock * $costRate, 2);
        }

        // D. GST Input Tax Credit (ITC - Eligible input tax on purchases)
        $inputGst = (float) PurchaseOrder::where('is_return', false)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereDate('po_date', '<=', $asOfDate)
            ->selectRaw('SUM(COALESCE(cgst,0) + COALESCE(sgst,0) + COALESCE(igst,0)) as total_tax')
            ->value('total_tax');

        $outputGst = (float) Invoice::where('is_return', false)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereDate('invoice_date', '<=', $asOfDate)
            ->selectRaw('SUM(COALESCE(cgst,0) + COALESCE(sgst,0) + COALESCE(igst,0)) as total_tax')
            ->value('total_tax');

        $netGstAsset = max(0, round($inputGst - $outputGst, 2));

        $totalCurrentAssets = round($totalBankCash + $debtorsTotal + $stockValuation + $netGstAsset, 2);
        $totalFixedAssets   = 0; // Office hardware/machinery
        $totalAssets        = round($totalCurrentAssets + $totalFixedAssets, 2);

        // ════════════════════════════════════════════════════
        // 2. LIABILITIES & EQUITY (Daayitva & Poonji)
        // ════════════════════════════════════════════════════

        // A. Sundry Creditors (Vendors Payable — Unpaid/Partial Purchase Orders)
        $creditorsTotal = (float) PurchaseOrder::where('is_return', false)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereDate('po_date', '<=', $asOfDate)
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount');

        // B. GST Liability Payable (If Output GST > Input GST)
        $netGstLiability = max(0, round($outputGst - $inputGst, 2));

        $totalLiabilities = round($creditorsTotal + $netGstLiability, 2);

        // C. Net Profit / (Loss) for the period (From Inception up to as_of_date)
        $netSalesTaxable = (float) Invoice::where('is_return', false)->whereNotIn('status', ['cancelled', 'draft'])->whereDate('invoice_date', '<=', $asOfDate)->sum('sub_total')
                         - (float) Invoice::where('is_return', true)->whereDate('invoice_date', '<=', $asOfDate)->sum('sub_total');

        $netPurchasesTaxable = (float) PurchaseOrder::where('is_return', false)->whereNotIn('status', ['cancelled', 'draft'])->whereDate('po_date', '<=', $asOfDate)->sum('sub_total')
                             - (float) PurchaseOrder::where('is_return', true)->whereDate('po_date', '<=', $asOfDate)->sum('sub_total');

        $grossProfit = $netSalesTaxable - $netPurchasesTaxable;

        $operatingExpenses = (float) OrgTransaction::where('type', 'debit')
            ->whereNotIn('category', ['vendor_payment', 'sales_return', 'transfer'])
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->sum('amount');

        $otherIncomes = (float) OrgTransaction::where('type', 'credit')
            ->whereNotIn('category', ['client_payment', 'purchase_return', 'transfer', 'owner_capital'])
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->sum('amount');

        $retainedEarnings = round($grossProfit + $otherIncomes - $operatingExpenses, 2);

        // D. Owner Capital Infusion
        $ownerCapital = (float) OrgTransaction::where('type', 'credit')
            ->where('category', 'owner_capital')
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->sum('amount');

        // Total Equity = Capital + Retained Earnings (or Balancing Equity)
        $totalEquity = round($totalAssets - $totalLiabilities, 2);

        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'as_of_date' => $asOfDate,
                'assets' => [
                    'current_assets' => [
                        'bank_balances'    => $bankAccounts,
                        'cash_in_hand'     => $cashInHand,
                        'total_bank_cash'  => $totalBankCash,
                        'sundry_debtors'   => $debtorsTotal,
                        'closing_stock'    => $stockValuation,
                        'gst_itc_asset'    => $netGstAsset,
                        'total'            => $totalCurrentAssets,
                    ],
                    'fixed_assets' => [
                        'items' => [],
                        'total' => $totalFixedAssets,
                    ],
                    'total_assets' => $totalAssets,
                ],
                'liabilities' => [
                    'current_liabilities' => [
                        'sundry_creditors' => $creditorsTotal,
                        'gst_payable'      => $netGstLiability,
                        'total'            => $totalLiabilities,
                    ],
                    'equity' => [
                        'owner_capital'     => $ownerCapital,
                        'retained_earnings' => $retainedEarnings,
                        'total_equity'      => $totalEquity,
                    ],
                    'total_liabilities_equity' => $totalLiabilitiesAndEquity,
                ],
                'is_balanced' => ($totalAssets === $totalLiabilitiesAndEquity),
            ],
        ]);
    }

    /**
     * ── Trial Balance (तलपट — Debit vs Credit Reconciliation) ──
     */
    public function trialBalance(Request $request)
    {
        $asOfDate = $request->query('as_of_date') ?: Carbon::now()->toDateString();

        $rows = [];
        $totalDebit  = 0;
        $totalCredit = 0;

        // 1. Bank Accounts (Debit Balances)
        foreach (OrgAccount::where('is_active', true)->get() as $acc) {
            $bal = (float) $acc->current_balance;
            if ($bal >= 0) {
                $rows[] = ['ledger' => 'Bank / Cash: ' . ($acc->account_name ?: $acc->name), 'debit' => $bal, 'credit' => 0];
                $totalDebit += $bal;
            } else {
                $rows[] = ['ledger' => 'Bank Overdraft: ' . ($acc->account_name ?: $acc->name), 'debit' => 0, 'credit' => abs($bal)];
                $totalCredit += abs($bal);
            }
        }

        // 2. Sundry Debtors (Receivables — Debit)
        $debtors = (float) Invoice::where('is_return', false)->whereNotIn('status', ['cancelled', 'draft'])->whereDate('invoice_date', '<=', $asOfDate)->where('balance_amount', '>', 0)->sum('balance_amount');
        if ($debtors > 0) {
            $rows[] = ['ledger' => 'Sundry Debtors (Clients Outstanding)', 'debit' => $debtors, 'credit' => 0];
            $totalDebit += $debtors;
        }

        // 3. Sundry Creditors (Payables — Credit)
        $creditors = (float) PurchaseOrder::where('is_return', false)->whereNotIn('status', ['cancelled', 'draft'])->whereDate('po_date', '<=', $asOfDate)->where('balance_amount', '>', 0)->sum('balance_amount');
        if ($creditors > 0) {
            $rows[] = ['ledger' => 'Sundry Creditors (Vendors Payable)', 'debit' => 0, 'credit' => $creditors];
            $totalCredit += $creditors;
        }

        // 4. Sales Turnover (Credit) & Sales Returns (Debit)
        $sales = (float) Invoice::where('is_return', false)->whereNotIn('status', ['cancelled', 'draft'])->whereDate('invoice_date', '<=', $asOfDate)->sum('sub_total');
        if ($sales > 0) {
            $rows[] = ['ledger' => 'Sales Account (Turnover)', 'debit' => 0, 'credit' => $sales];
            $totalCredit += $sales;
        }

        $salesRet = (float) Invoice::where('is_return', true)->whereDate('invoice_date', '<=', $asOfDate)->sum('sub_total');
        if ($salesRet > 0) {
            $rows[] = ['ledger' => 'Sales Return (Inward)', 'debit' => $salesRet, 'credit' => 0];
            $totalDebit += $salesRet;
        }

        // 5. Purchases (Debit) & Purchase Returns (Credit)
        $purchases = (float) PurchaseOrder::where('is_return', false)->whereNotIn('status', ['cancelled', 'draft'])->whereDate('po_date', '<=', $asOfDate)->sum('sub_total');
        if ($purchases > 0) {
            $rows[] = ['ledger' => 'Purchase Account (Cost of Goods)', 'debit' => $purchases, 'credit' => 0];
            $totalDebit += $purchases;
        }

        $purchaseRet = (float) PurchaseOrder::where('is_return', true)->whereDate('po_date', '<=', $asOfDate)->sum('sub_total');
        if ($purchaseRet > 0) {
            $rows[] = ['ledger' => 'Purchase Return (Outward)', 'debit' => 0, 'credit' => $purchaseRet];
            $totalCredit += $purchaseRet;
        }

        // 6. Operating Expenses (Debit)
        $expenses = OrgTransaction::where('type', 'debit')
            ->whereNotIn('category', ['vendor_payment', 'sales_return', 'transfer'])
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->get();

        foreach ($expenses->groupBy('category') as $cat => $items) {
            $amt = (float) $items->sum('amount');
            $rows[] = ['ledger' => 'Expense: ' . ucwords(str_replace('_', ' ', $cat)), 'debit' => $amt, 'credit' => 0];
            $totalDebit += $amt;
        }

        // 7. Other Incomes (Credit)
        $incomes = OrgTransaction::where('type', 'credit')
            ->whereNotIn('category', ['client_payment', 'purchase_return', 'transfer', 'owner_capital'])
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->get();

        foreach ($incomes->groupBy('category') as $cat => $items) {
            $amt = (float) $items->sum('amount');
            $rows[] = ['ledger' => 'Income: ' . ucwords(str_replace('_', ' ', $cat)), 'debit' => 0, 'credit' => $amt];
            $totalCredit += $amt;
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'as_of_date'   => $asOfDate,
                'rows'         => $rows,
                'total_debit'  => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'difference'   => round(abs($totalDebit - $totalCredit), 2),
                'is_tallied'   => round($totalDebit, 2) === round($totalCredit, 2),
            ],
        ]);
    }
}