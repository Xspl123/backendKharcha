<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceNumberService;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function getAll(array $filters = [])
    {
        $query = Invoice::where('user_id', auth()->id())
            ->with(['client', 'company', 'items', 'payments']); // ADD 'company' here

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by client
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        // Filter by date range
        if (isset($filters['from_date'])) {
            $query->where('invoice_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('invoice_date', '<=', $filters['to_date']);
        }

        // Search by invoice number
        if (isset($filters['search'])) {
            $query->where('invoice_no', 'like', "%{$filters['search']}%");
        }

        return $query->latest('invoice_date')->get();
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {

            $tenantId = auth()->id();

            // ⭐ AUTO invoice number
            $data['invoice_no'] = InvoiceNumberService::generate($tenantId);

            $data['user_id'] = $tenantId;

            $items = $data['items'] ?? [];
            unset($data['items']);

            $invoice = Invoice::create($data);

            foreach ($items as $item) {
                $invoice->items()->create($item);
            }

            $invoice->calculateTotals();

            return $invoice->load(['client', 'company', 'items', 'payments']); // ADD 'company' here
        });
    }

    public function show($id)
    {
        return Invoice::where('user_id', auth()->id())
            ->with(['client', 'company', 'items', 'payments']) // ADD 'company' here
            ->findOrFail($id);
    }

    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = $this->show($id);

            // Separate invoice data and items
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Update invoice
            $invoice->update($data);

            // Delete old items and create new ones
            if (!empty($items)) {
                $invoice->items()->delete();
                
                foreach ($items as $item) {
                    $invoice->items()->create($item);
                }
            }

            // Recalculate totals
            $invoice->calculateTotals();

            return $invoice->load(['client', 'company', 'items', 'payments']); // ADD 'company' here
        });
    }

    public function delete($id)
    {
        $invoice = $this->show($id);
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

        // Extract number from last invoice
        $lastNumber = (int) substr($lastInvoice->invoice_no, -4);
        $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return 'INV-' . date('Y') . '-' . $nextNumber;
    }

    public function getByClient($clientId)
    {
        return Invoice::where('user_id', auth()->id())
            ->where('client_id', $clientId)
            ->with(['company', 'items', 'payments']) // ADD 'company' here
            ->latest('invoice_date')
            ->get();
    }
}
?>
