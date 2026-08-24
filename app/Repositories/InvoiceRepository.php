<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Repositories\Interfaces\InvoiceRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;
use App\Services\InvoiceNumberService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getAll(array $filters = [])
    {
        $query = $this->scopeQuery(Invoice::query())
            ->with(['client', 'company', 'items', 'payments']);

        if (isset($filters['status']))    $query->where('status', $filters['status']);
        if (isset($filters['client_id'])) $query->where('client_id', $filters['client_id']);
        if (isset($filters['from_date'])) $query->where('invoice_date', '>=', $filters['from_date']);
        if (isset($filters['to_date']))   $query->where('invoice_date', '<=', $filters['to_date']);
        if (isset($filters['search']))    $query->where('invoice_no', 'like', "%{$filters['search']}%");

        $query->where(function ($q) {
            $q->whereNull('is_return')->orWhere('is_return', 0);
        });

        return $query->latest('invoice_date')
            ->paginate($this->resolvePerPage($filters));
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data = $this->scopeData($data);
            $data['invoice_no'] = InvoiceNumberService::generate($data['user_id']);

            $items = $data['items'] ?? [];
            unset($data['items']);

            $invoice = Invoice::create($data);
            foreach ($items as $item) $invoice->items()->create($item);
            $invoice->calculateTotals();

            StockService::recordSaleOut($items, $data['user_id'], $invoice->id, $invoice->invoice_no);

            $this->bumpScopedCache(['invoices', 'clients', 'gst', 'stock', 'stock_report']);
            return $invoice->load(['client', 'company', 'items', 'payments']);
        });
    }

    public function show($id)
    {
        return $this->scopeQuery(Invoice::query())
            ->with(['client', 'company', 'items', 'payments'])
            ->findOrFail($id);
    }

    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $invoice = $this->show($id);
            $items   = $data['items'] ?? [];
            unset($data['items']);

            StockService::reverseSaleOut($invoice->id, $this->userId(), $invoice->invoice_no);
            $invoice->update($data);

            if (!empty($items)) {
                $invoice->items()->delete();
                foreach ($items as $item) $invoice->items()->create($item);
            }

            $invoice->calculateTotals();
            StockService::recordSaleOut($items, $this->userId(), $invoice->id, $invoice->invoice_no);

            $this->bumpScopedCache(['invoices', 'clients', 'gst', 'stock', 'stock_report']);
            return $invoice->load(['client', 'company', 'items', 'payments']);
        });
    }

    public function partialUpdate($id, array $data)
    {
        return $this->update($id, $data);
    }

    public function delete($id)
    {
        $invoice = $this->show($id);
        StockService::reverseSaleOut($invoice->id, $this->userId(), $invoice->invoice_no);
        $this->bumpScopedCache(['invoices', 'clients', 'gst', 'stock', 'stock_report']);
        return $invoice->delete();
    }

    public function getNextInvoiceNumber()
    {
        $last = $this->scopeQuery(Invoice::query())->orderBy('id', 'desc')->first();
        if (!$last) return 'INV-' . date('Y') . '-0001';
        $next = str_pad((int) substr($last->invoice_no, -4) + 1, 4, '0', STR_PAD_LEFT);
        return 'INV-' . date('Y') . '-' . $next;
    }

    public function getByClient($clientId)
    {
        return $this->scopeQuery(Invoice::query())
            ->where('client_id', $clientId)
            ->with(['company', 'items', 'payments'])
            ->latest('invoice_date')->get();
    }

    public function getOverdue()
    {
        return $this->scopeQuery(Invoice::query())
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->toDateString())
            ->with(['client', 'company', 'items', 'payments'])
            ->latest('due_date')->get();
    }
}
