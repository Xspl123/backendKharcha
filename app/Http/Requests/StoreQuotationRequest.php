<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'lead_id' => [
                'nullable',
                $this->tenantScopedExists('leads'),
            ],
            'client_id' => [
                'nullable',
                $this->tenantScopedExists('clients'),
            ],
            'quotation_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:quotation_date',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'status' => 'nullable|in:draft,sent,approved,rejected,expired',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'nullable',
                $this->tenantScopedExists('products'),
            ],
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.hsn_code' => 'nullable|string|max:50',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
