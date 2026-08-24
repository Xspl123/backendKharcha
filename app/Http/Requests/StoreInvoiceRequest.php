<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                $this->tenantScopedExists('companies'),
            ],
            'client_id' => [
                'required',
                $this->tenantScopedExists('clients'),
            ],

            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'invoice_type'              => 'required|in:b2b,b2cs,b2cl,export',
            'supply_type'               => 'required|in:intra,inter',
            'place_of_supply'           => 'nullable',
            'is_reverse_charge'         => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.product_id' => [
                'nullable',
                $this->tenantScopedExists('products'),
            ],
            'items.*.description' => 'nullable|string',
            'items.*.hsn_code' => 'nullable|string|max:255',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Company is required',
            'company_id.exists' => 'Selected company does not exist',
            'client_id.required' => 'Client is required',
            'client_id.exists' => 'Selected client does not exist',
            'invoice_no.required' => 'Invoice number is required',
            'invoice_no.unique' => 'Invoice number already exists',
            'invoice_date.required' => 'Invoice date is required',
            'due_date.after_or_equal' => 'Due date must be after or equal to invoice date',
            'invoice_type.required'         => 'Invoice type is required',
            'invoice_type.in'               => 'Invoice type must be: b2b, b2cs, b2cl, or export',
            'supply_type.required'          => 'Supply type is required',
            'supply_type.in'                => 'Supply type must be: intra or inter',
            'place_of_supply.max'           => 'Place of supply must not exceed 255 characters',
            'items.required' => 'At least one item is required',
            'items.*.item_name.required' => 'Item name is required',
            'items.*.product_id.exists' => 'Selected product does not exist',
            'items.*.qty.required' => 'Quantity is required',
            'items.*.qty.min' => 'Quantity must be at least 0.01',
            'items.*.rate.required' => 'Rate is required',
        ];
    }
}
