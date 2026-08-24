<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vendor_id'              => [
                'required',
                $this->tenantScopedExists('vendors'),
            ],
            'po_date'                => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:po_date',
            'supply_type'            => 'required|in:intra,inter',
            'place_of_supply'        => 'nullable|string|max:100',
            'is_reverse_charge'      => 'nullable|boolean',
            'notes'                  => 'nullable|string',
            'terms_conditions'       => 'nullable|string',

            'items'                  => 'required|array|min:1',
            'items.*.item_name'      => 'required|string|max:255',
            'items.*.product_id'     => [
                'nullable',
                $this->tenantScopedExists('products'),
            ],
            'items.*.category_id'    => [
                'nullable',
                $this->tenantScopedExists('product_categories'),
            ],
            'items.*.description'    => 'nullable|string',
            'items.*.hsn_code'       => 'nullable|string|max:255',
            'items.*.qty'            => 'required|numeric|min:0.01',
            'items.*.unit'           => 'nullable|string|max:50',
            'items.*.rate'           => 'required|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_id.required'          => 'Vendor select karo',
            'vendor_id.exists'            => 'Selected vendor exist nahi karta',
            'po_date.required'            => 'PO date required hai',
            'supply_type.required'        => 'Supply type required hai',
            'supply_type.in'              => 'Supply type intra ya inter hona chahiye',
            'place_of_supply.max'         => 'Place of supply 100 characters se zyada nahi ho sakta',
            'items.required'              => 'Kam se kam ek item required hai',
            'items.*.item_name.required'  => 'Item name required hai',
            'items.*.qty.required'        => 'Quantity required hai',
            'items.*.qty.min'             => 'Quantity 0 se zyada honi chahiye',
            'items.*.rate.required'       => 'Rate required hai',
        ];
    }
}
