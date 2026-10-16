<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\GstEInvoiceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EInvoiceController extends Controller
{
    public function __construct(
        protected GstEInvoiceService $eInvoiceService
    ) {}

    public function downloadEWayBillJson(int $id, Request $request)
    {
        $invoice = Invoice::with(['company', 'client', 'items'])->findOrFail($id);
        $payload = $this->eInvoiceService->generateEWayBillPayload($invoice, $request->all());
        $fileName = 'EWB_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice->invoice_no) . '.json';

        return response()->json($payload, Response::HTTP_OK, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function saveEWayBill(int $id, Request $request)
    {
        $request->validate([
            'transporter_id'   => 'nullable|string|max:15',
            'transporter_name' => 'nullable|string|max:150',
            'trans_doc_no'     => 'nullable|string|max:50',
            'trans_doc_date'   => 'nullable|date',
            'trans_mode'       => 'nullable|in:1,2,3,4',
            'vehicle_no'       => 'nullable|string|max:20',
            'vehicle_type'     => 'nullable|in:R,O',
            'distance_km'      => 'nullable|integer|min:1|max:5000',
            'eway_bill_no'     => 'nullable|string|max:20',
            'eway_bill_date'   => 'nullable|date',
        ]);

        $invoice = Invoice::findOrFail($id);
        $updated = $this->eInvoiceService->saveTransportationDetails($invoice, $request->all());

        return response()->json([
            'message' => 'E-Way Bill & Transportation details saved successfully',
            'data'    => new InvoiceResource($updated->load(['company', 'client', 'items'])),
        ]);
    }

    public function downloadEInvoiceJson(int $id)
    {
        $invoice = Invoice::with(['company', 'client', 'items'])->findOrFail($id);
        $payload = $this->eInvoiceService->generateEInvoicePayload($invoice);
        $fileName = 'EINV_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice->invoice_no) . '.json';

        return response()->json($payload, Response::HTTP_OK, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function generateIrn(int $id)
    {
        $invoice = Invoice::with(['company', 'client', 'items'])->findOrFail($id);

        if ($invoice->einvoice_status === 'generated' && !empty($invoice->irn)) {
            return response()->json([
                'message' => 'E-Invoice IRN already generated for this invoice',
                'data'    => [
                    'irn'             => $invoice->irn,
                    'ack_no'          => $invoice->ack_no,
                    'ack_date'        => $invoice->ack_date?->format('Y-m-d H:i:s'),
                    'signed_qr_data'  => $invoice->signed_qr_data,
                    'einvoice_status' => $invoice->einvoice_status,
                ],
            ]);
        }

        $result = $this->eInvoiceService->generateIrn($invoice);

        return response()->json([
            'message' => 'E-Invoice IRN & Signed QR successfully generated',
            'data'    => $result,
        ]);
    }

    public function cancelIrn(int $id, Request $request)
    {
        $invoice = Invoice::findOrFail($id);
        $this->eInvoiceService->cancelIrn($invoice, $request->input('reason', '1'));

        return response()->json([
            'message' => 'E-Invoice IRN marked as cancelled',
        ]);
    }
}