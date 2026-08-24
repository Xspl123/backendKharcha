<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    private $invoiceRepo;

    public function __construct(InvoiceRepositoryInterface $invoiceRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'client_id', 'from_date', 'to_date', 'search', 'per_page']);
        $invoices = $this->invoiceRepo->getAll($filters);
        
        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = $this->invoiceRepo->store($request->validated());

        return response()->json([
            'message' => 'Invoice created successfully',
            'invoice' => new InvoiceResource($invoice)
        ], 201);
    }

    public function show($id)
    {
        $invoice = $this->invoiceRepo->show($id);
        return new InvoiceResource($invoice);
    }

    public function update(StoreInvoiceRequest $request, $id)
    {
        $invoice = $this->invoiceRepo->update($id, $request->validated());

        return response()->json([
            'message' => 'Invoice updated successfully',
            'invoice' => new InvoiceResource($invoice)
        ]);
    }

    public function destroy($id)
    {
        $this->invoiceRepo->delete($id);

        return response()->json([
            'message' => 'Invoice deleted successfully'
        ]);
    }

    // Get next invoice number
    public function getNextInvoiceNumber()
    {
        $invoiceNumber = $this->invoiceRepo->getNextInvoiceNumber();
        
        return response()->json([
            'invoice_no' => $invoiceNumber
        ]);
    }

    // Get invoices by client
    public function getByClient($clientId)
    {
        $invoices = $this->invoiceRepo->getByClient($clientId);
        return InvoiceResource::collection($invoices);
    }
}
?>
