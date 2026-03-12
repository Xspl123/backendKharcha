<?php
// app/Http/Requests/StoreSalesReturnRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // 🔍 DEBUGGING - Yeh line add karo
        \Log::info('📦 Incoming Request Data:', [
            'all' => $this->all(),
            'json' => $this->json()->all(),
            'input' => $this->input(),
            'headers' => $this->header('Content-Type'),
            'method' => $this->method()
        ]);

        return [
            'original_invoice_id' => 'required|exists:invoices,id',
            'items' => 'required|array|min:1',
            'items.*.invoice_item_id' => 'nullable|exists:invoice_items,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'return_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }

    protected function prepareForValidation()
    {
        // 🔍 Debug before validation
        \Log::info('🔄 Before validation:', $this->all());
    }

     public function messages(): array
    {
        return [
            'original_invoice_id.required' => 'Original invoice select karo!',
            'original_invoice_id.exists'   => 'Invoice exist nahi karta!',
            'items.required'               => 'Kam se kam ek item chahiye!',
            'items.*.qty.required'         => 'Item ki qty daalo!',
            'items.*.qty.min'              => 'Qty zero se zyada honi chahiye!',
            'items.*.rate.required'        => 'Item ka rate daalo!',
        ];
    }
}