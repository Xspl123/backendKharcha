<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'original_po_id' => 'required|exists:purchase_orders,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'return_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'original_po_id.required' => 'Original purchase order select karo',
            'items.required' => 'Kam se kam ek item to return karo',
            'items.*.qty.min' => 'Quantity 0 se zyada honi chahiye',
        ];
    }
}