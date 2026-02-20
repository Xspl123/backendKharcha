<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoicePaymentRequest;
use App\Http\Resources\InvoicePaymentResource;
use App\Repositories\Interfaces\InvoicePaymentRepositoryInterface;

class InvoicePaymentController extends Controller
{
    private $paymentRepo;

    public function __construct(InvoicePaymentRepositoryInterface $paymentRepo)
    {
        $this->paymentRepo = $paymentRepo;
    }

    public function index()
    {
        $payments = $this->paymentRepo->getAll();
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
    public function getByInvoice($invoiceId)
    {
        $payments = $this->paymentRepo->getByInvoice($invoiceId);
        return InvoicePaymentResource::collection($payments);
    }
}
?>