<?php

namespace App\Repositories;

use App\Models\InvoicePayment;
use App\Repositories\Interfaces\InvoicePaymentRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\PaginatesResults;
use App\Repositories\Traits\ScopedCache;

class InvoicePaymentRepository implements InvoicePaymentRepositoryInterface
{
    use OrgScope, PaginatesResults, ScopedCache;

    public function getAll(array $filters = [])
    {
        return $this->scopeQuery(InvoicePayment::query())
            ->with(['invoice.client'])->latest('payment_date')
            ->paginate($this->resolvePerPage($filters, 50));
    }

    public function store(array $data)
    {
        $payment = InvoicePayment::create($this->scopeData($data))
            ->load(['invoice.client']);
        $this->bumpScopedCache(['invoices', 'clients', 'gst']);
        return $payment;
    }

    public function show($id)
    {
        return $this->scopeQuery(InvoicePayment::query())
            ->with(['invoice.client'])->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $payment = $this->show($id);
        $payment->update($data);
        $this->bumpScopedCache(['invoices', 'clients', 'gst']);
        return $payment->load(['invoice.client']);
    }

    public function delete($id)
    {
        $deleted = $this->show($id)->delete();
        $this->bumpScopedCache(['invoices', 'clients', 'gst']);
        return $deleted;
    }

    public function getByInvoice($invoiceId, array $filters = [])
    {
        return $this->scopeQuery(InvoicePayment::query())
            ->where('invoice_id', $invoiceId)
            ->orderBy('payment_date', 'desc')
            ->paginate($this->resolvePerPage($filters, 50));
    }
}
