<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\OrgTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfitLossController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate   = $request->query('to_date');

        // ── 1. Revenue from Operations (Sales Invoices) ──────
        $salesQuery = Invoice::query()->whereNotIn('status', ['cancelled', 'draft']);
        if ($fromDate) $salesQuery->whereDate('invoice_date', '>=', $fromDate);
        if ($toDate)   $salesQuery->whereDate('invoice_date', '<=', $toDate);

        // Gross Sales (excluding returns)
        $grossSales = (clone $salesQuery)->where('is_return', false);
        $grossSalesTaxable = (float) $grossSales->sum('sub_total');
        $grossSalesTotal   = (float) $grossSales->sum('total_amount');
        $grossSalesCount   = $grossSales->count();

        // Sales Returns
        $salesReturns = (clone $salesQuery)->where('is_return', true);
        $salesReturnsTaxable = (float) $salesReturns->sum('sub_total');
        $salesReturnsTotal   = (float) $salesReturns->sum('total_amount');
        $salesReturnsCount   = $salesReturns->count();

        // Net Sales Revenue
        $netSalesTaxable = round($grossSalesTaxable - $salesReturnsTaxable, 2);
        $netSalesTotal   = round($grossSalesTotal - $salesReturnsTotal, 2);

        // ── 2. Cost of Goods Sold (Purchase Orders) ──────────
        $poQuery = PurchaseOrder::query()->whereNotIn('status', ['cancelled', 'draft']);
        if ($fromDate) $poQuery->whereDate('po_date', '>=', $fromDate);
        if ($toDate)   $poQuery->whereDate('po_date', '<=', $toDate);

        // Gross Purchases (excluding returns)
        $grossPurchases = (clone $poQuery)->where('is_return', false);
        $grossPurchasesTaxable = (float) $grossPurchases->sum('sub_total');
        $grossPurchasesTotal   = (float) $grossPurchases->sum('total_amount');
        $grossPurchasesCount   = $grossPurchases->count();

        // Purchase Returns
        $purchaseReturns = (clone $poQuery)->where('is_return', true);
        $purchaseReturnsTaxable = (float) $purchaseReturns->sum('sub_total');
        $purchaseReturnsTotal   = (float) $purchaseReturns->sum('total_amount');
        $purchaseReturnsCount   = $purchaseReturns->count();

        // Net Purchases (COGS)
        $netPurchasesTaxable = round($grossPurchasesTaxable - $purchaseReturnsTaxable, 2);
        $netPurchasesTotal   = round($grossPurchasesTotal - $purchaseReturnsTotal, 2);

        // ── 3. Gross Profit (Trading Margin) ─────────────────
        $grossProfit = round($netSalesTaxable - $netPurchasesTaxable, 2);
        $grossMarginPercent = $netSalesTaxable > 0 ? round(($grossProfit / $netSalesTaxable) * 100, 2) : 0;

        // ── 4. Operating Expenses (From Org Transactions) ────
        $txQuery = OrgTransaction::query();
        if ($fromDate) $txQuery->whereDate('transaction_date', '>=', $fromDate);
        if ($toDate)   $txQuery->whereDate('transaction_date', '<=', $toDate);

        // Debits excluding vendor payments (COGS), sales return refunds, and contra transfers
        $expenseTx = (clone $txQuery)
            ->where('type', 'debit')
            ->whereNotIn('category', ['vendor_payment', 'sales_return', 'transfer'])
            ->get();

        $expenseCategories = [];
        $totalOperatingExpenses = 0;

        foreach ($expenseTx->groupBy('category') as $catKey => $items) {
            $catSum = round((float) $items->sum('amount'), 2);
            $totalOperatingExpenses += $catSum;
            $expenseCategories[] = [
                'category'    => $catKey,
                'name'        => ucwords(str_replace('_', ' ', $catKey)),
                'total_amount'=> $catSum,
                'count'       => $items->count(),
            ];
        }

        usort($expenseCategories, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        // ── 5. Other Incomes (From Org Transactions) ─────────
        // Credits excluding client receipts, purchase return refunds, transfers, and capital
        $incomeTx = (clone $txQuery)
            ->where('type', 'credit')
            ->whereNotIn('category', ['client_payment', 'purchase_return', 'transfer', 'owner_capital'])
            ->get();

        $incomeCategories = [];
        $totalOtherIncome = 0;

        foreach ($incomeTx->groupBy('category') as $catKey => $items) {
            $catSum = round((float) $items->sum('amount'), 2);
            $totalOtherIncome += $catSum;
            $incomeCategories[] = [
                'category'    => $catKey,
                'name'        => ucwords(str_replace('_', ' ', $catKey)),
                'total_amount'=> $catSum,
                'count'       => $items->count(),
            ];
        }

        // ── 6. Net Profit (Bottom Line) ──────────────────────
        $netProfit = round($grossProfit + $totalOtherIncome - $totalOperatingExpenses, 2);
        $netMarginPercent = $netSalesTaxable > 0 ? round(($netProfit / $netSalesTaxable) * 100, 2) : 0;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'period' => [
                    'from_date' => $fromDate ?: 'All time',
                    'to_date'   => $toDate ?: Carbon::now()->toDateString(),
                ],
                'revenue' => [
                    'gross_sales_taxable'   => $grossSalesTaxable,
                    'gross_sales_total'     => $grossSalesTotal,
                    'gross_sales_count'     => $grossSalesCount,
                    'sales_returns_taxable' => $salesReturnsTaxable,
                    'sales_returns_total'   => $salesReturnsTotal,
                    'sales_returns_count'   => $salesReturnsCount,
                    'net_sales_taxable'     => $netSalesTaxable,
                    'net_sales_total'       => $netSalesTotal,
                ],
                'cogs' => [
                    'gross_purchases_taxable'  => $grossPurchasesTaxable,
                    'gross_purchases_total'    => $grossPurchasesTotal,
                    'gross_purchases_count'    => $grossPurchasesCount,
                    'purchase_returns_taxable' => $purchaseReturnsTaxable,
                    'purchase_returns_total'   => $purchaseReturnsTotal,
                    'purchase_returns_count'   => $purchaseReturnsCount,
                    'net_purchases_taxable'    => $netPurchasesTaxable,
                    'net_purchases_total'      => $netPurchasesTotal,
                ],
                'gross_profit' => [
                    'amount'         => $grossProfit,
                    'margin_percent' => $grossMarginPercent,
                ],
                'operating_expenses' => [
                    'items' => $expenseCategories,
                    'total' => round($totalOperatingExpenses, 2),
                ],
                'other_income' => [
                    'items' => $incomeCategories,
                    'total' => round($totalOtherIncome, 2),
                ],
                'net_profit' => [
                    'amount'         => $netProfit,
                    'margin_percent' => $netMarginPercent,
                ],
            ],
        ]);
    }
}