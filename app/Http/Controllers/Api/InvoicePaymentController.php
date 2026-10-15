<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoicePaymentRequest;
use App\Http\Resources\InvoicePaymentResource;
use App\Repositories\Interfaces\InvoicePaymentRepositoryInterface;
use App\Models\OrgAccount;
use App\Models\OrgTransaction;

class InvoicePaymentController extends Controller
{
    private $paymentRepo;

    public function __construct(InvoicePaymentRepositoryInterface $paymentRepo)
    {
        $this->paymentRepo = $paymentRepo;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $payments = $this->paymentRepo->getAll($request->only(['per_page']));
        return InvoicePaymentResource::collection($payments);
    }

    public function store(InvoicePaymentRequest $request)
    {
        $payment = $this->paymentRepo->store($request->validated());

        return response()->json([
            'message' => 'Payment recorded successfully',
            'payment' => new InvoicePaymentResource($payment)
        ], 201);
    }

    public function show($id)
    {
        $payment = $this->paymentRepo->show($id);
        return new InvoicePaymentResource($payment);
    }

    public function update(InvoicePaymentRequest $request, $id)
    {
        $payment = $this->paymentRepo->update($id, $request->validated());

        return response()->json([
            'message' => 'Payment updated successfully',
            'payment' => new InvoicePaymentResource($payment)
        ]);
    }

    public function destroy($id)
    {
        $this->paymentRepo->delete($id);

        return response()->json([
            'message' => 'Payment deleted successfully'
        ]);
    }

    // Get payments by invoice
    public function getByInvoice($invoiceId, \Illuminate\Http\Request $request)
    {
        $payments = $this->paymentRepo->getByInvoice($invoiceId, $request->only(['per_page']));
        return InvoicePaymentResource::collection($payments);
    }

        public function refund(\Illuminate\Http\Request $request, $id)
        {
            $validated = $request->validate([
                'refund_date'   => 'nullable|date',
                'refund_method' => 'nullable|string',
                'notes'         => 'nullable|string',
                'account_id'    => 'nullable|integer',
            ]);

            $payment = $this->paymentRepo->show($id);

            $rDate   = $validated['refund_date'] ?? now()->toDateString();
            $rMethod = $validated['refund_method'] ?? $payment->payment_method ?? 'bank_transfer';
            $rNotes  = $validated['notes'] ?? 'Payment refund to client';
            $amount  = (float) $payment->amount;

            // ── Auto-Debit Org Account & Log Cashbook Entry ──────
            $accountId = $validated['account_id'] ?? $payment->account_id;
            if ($accountId && $amount > 0) {
                $account = OrgAccount::find($accountId);
                if ($account) {
                    $account->decrement('current_balance', $amount);

                    OrgTransaction::create([
                        'user_id'            => auth()->id(),
                        'org_id'             => $payment->org_id ?? auth()->user()->org_id,
                        'account_id'         => $account->id,
                        'transaction_date'   => $rDate,
                        'type'               => 'debit',
                        'category'           => 'sales_return',
                        'amount'             => $amount,
                        'balance_after'      => (float) $account->fresh()->current_balance,
                        'payment_method'     => $rMethod,
                        'reference_no'       => $payment->transaction_id ?? ($payment->invoice?->invoice_no ?? null),
                        'description'        => 'Client Payment Refund: ' . ($payment->invoice?->client?->company_name ?? 'Client') . ' (Inv: ' . ($payment->invoice?->invoice_no ?? 'Invoice') . ')',
                        'invoice_payment_id' => $payment->id,
                    ]);
                }
            }

            $payment->notes = sprintf('[REFUNDED:%s|%s] %s', $rDate, $rMethod, $rNotes);
            $payment->save();

            \Illuminate\Support\Facades\Cache::flush();

            return response()->json([
                'message' => 'Payment refunded successfully & Cashbook debited! ✅',
                'payment' => new InvoicePaymentResource($payment)
            ]);
        }
}
?>
