<?php

namespace App\Repositories;

use App\Models\InvoicePayment;
use App\Repositories\Interfaces\InvoicePaymentRepositoryInterface;

class InvoicePaymentRepository implements InvoicePaymentRepositoryInterface
{
    public function getAll()
    {
        return InvoicePayment::where('user_id', auth()->id())
            ->with(['invoice.client'])
            ->latest('payment_date')
            ->get();
    }

    public function store(array $data)
    {
        $data['user_id'] = auth()->id();
        
        $payment = InvoicePayment::create($data);
        
        return $payment->load(['invoice.client']);
    }

    public function show($id)
    {
        return InvoicePayment::where('user_id', auth()->id())
            ->with(['invoice.client'])
            ->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $payment = $this->show($id);
        $payment->update($data);
        
        // Status update hoga automatically via model event
        return $payment->load(['invoice.client']);
    }

    public function delete($id)
    {
        $payment = $this->show($id);
        return $payment->delete();
    }

    public function getByInvoice($invoiceId)
    {
        return InvoicePayment::where('user_id', auth()->id())
            ->where('invoice_id', $invoiceId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }
}
?>