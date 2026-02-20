<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVendorPaymentRequest;
use App\Http\Resources\VendorPaymentResource;
use App\Repositories\Interfaces\VendorPaymentRepositoryInterface;
use Illuminate\Http\Request;

class VendorPaymentController extends Controller
{
    public function __construct(
        private VendorPaymentRepositoryInterface $paymentRepo
    ) {}

    
    public function index(Request $request)
    {
        if ($request->filled('purchase_order_id')) {
            $payments = $this->paymentRepo->getByPurchaseOrder(
                $request->purchase_order_id
            );
        } elseif ($request->filled('vendor_id')) {
            $payments = $this->paymentRepo->getByVendor(
                $request->vendor_id
            );
        } else {
            return response()->json([
                'message' => 'purchase_order_id ya vendor_id filter required hai'
            ], 422);
        }

        return VendorPaymentResource::collection($payments);
    }

    // POST /api/vendor-payments
    public function store(StoreVendorPaymentRequest $request)
    {
        $payment = $this->paymentRepo->create($request->validated());

        return response()->json([
            'message' => 'Payment recorded successfully',
            'data'    => new VendorPaymentResource($payment),
        ], 201);
    }

    // DELETE /api/vendor-payments/{id}
    public function destroy(int $id)
    {
        $this->paymentRepo->delete($id);

        return response()->json([
            'message' => 'Payment deleted successfully',
        ]);
    }
}