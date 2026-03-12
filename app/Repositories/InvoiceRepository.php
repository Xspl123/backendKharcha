<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceNumberService;
use App\Services\StockService;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function getAll(array $filters = [])
    {
        $query = Invoice::where('user_id', auth()->id())
            ->with(['client', 'company', 'items', 'payments']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (isset($filters['from_date'])) {
            $query->where('invoice_date', '>=', $filters['from_date']);
        }
        if (isset($filters['to_date'])) {
            $query->where('invoice_date', '<=', $filters['to_date']);
        }
        if (isset($filters['search'])) {
            $query->where('invoice_no', 'like', "%{$filters['search']}%");
        }

        // Return invoices list se exclude karo
        $query->where(function ($q) {
            $q->whereNull('is_return')->orWhere('is_return', 0);
        });

        return $query->latest('invoice_date')->get();
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $tenantId = auth()->id();

            $data['invoice_no'] = InvoiceNumberService::generate($tenantId);
            $data['user_id']    = $tenantId;

            $items = $data['items'] ?? [];
            unset($data['items']);

            $invoice = Invoice::create($data);

            foreach ($items as $item) {
                $invoice->items()->create($item);
            }

            $invoice->calculateTotals();

            StockService::recordSaleOut($items, $tenantId, $invoice->id, $invoice->invoice_no);

            return $invoice->load(['client', 'company', 'items', 'payments']);
        });
    }

    public function show($id)
    {
        return Invoice::where('user_id', auth()->id())
            ->with(['client', 'company', 'items', 'payments'])
            ->findOrFail($id);
    }

    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = $this->show($id);

            $items = $data['items'] ?? [];
            unset($data['items']);

            StockService::reverseSaleOut($invoice->id, auth()->id(), $invoice->invoice_no);

            $invoice->update($data);

            if (!empty($items)) {
                $invoice->items()->delete();
                foreach ($items as $item) {
                    $invoice->items()->create($item);
                }
            }

            $invoice->calculateTotals();

            StockService::recordSaleOut($items, auth()->id(), $invoice->id, $invoice->invoice_no);

            return $invoice->load(['client', 'company', 'items', 'payments']);
        });
    }

    public function partialUpdate($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = $this->show($id);

            $items = $data['items'] ?? [];
            unset($data['items']);

            // ✅ FIX: Purani sale_out movements reverse karo
            StockService::reverseSaleOut($invoice->id, auth()->id(), $invoice->invoice_no);

            $invoice->update($data);

            if (!empty($items)) {
                $invoice->items()->delete();
                foreach ($items as $item) {
                    $invoice->items()->create($item);
                }
            }

            $invoice->calculateTotals();

            // ✅ FIX: Naye items ke liye sale_out DOBARA record karo
            // Pehle yeh line missing thi — stock reduce nahi hota tha partial update pe
            StockService::recordSaleOut($items, auth()->id(), $invoice->id, $invoice->invoice_no);

            return $invoice->load(['client', 'company', 'items', 'payments']);
        });
    }

    public function delete($id)
    {
        $invoice = $this->show($id);
        StockService::reverseSaleOut($invoice->id, auth()->id(), $invoice->invoice_no);
        return $invoice->delete();
    }

    public function getNextInvoiceNumber()
    {
        $lastInvoice = Invoice::where('user_id', auth()->id())
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastInvoice) {
            return 'INV-' . date('Y') . '-0001';
        }

        $lastNumber = (int) substr($lastInvoice->invoice_no, -4);
        $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return 'INV-' . date('Y') . '-' . $nextNumber;
    }

    public function getByClient($clientId)
    {
        return Invoice::where('user_id', auth()->id())
            ->where('client_id', $clientId)
            ->with(['company', 'items', 'payments'])
            ->latest('invoice_date')
            ->get();
    }

    public function getOverdue()
    {
        return Invoice::where('user_id', auth()->id())
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->toDateString())
            ->with(['client', 'company', 'items', 'payments'])
            ->latest('due_date')
            ->get();
    }
}