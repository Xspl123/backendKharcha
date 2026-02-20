<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_category_id' => 'nullable|exists:product_categories,id',
            'name'                => 'required|string|max:255',
            'sku'                 => 'nullable|string|max:100',
            'hsn_code'            => 'nullable|string|max:20',
            'description'         => 'nullable|string',
            'unit'                => 'nullable|string|max:20',
            'purchase_price'      => 'nullable|numeric|min:0',
            'selling_price'       => 'nullable|numeric|min:0',
            'tax_rate'            => 'nullable|numeric|min:0|max:100',
            'opening_stock'       => 'nullable|numeric|min:0',
            'low_stock_alert'     => 'nullable|numeric|min:0',
            'status'              => 'nullable|in:active,inactive',
            'notes'               => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Product name required hai',
            'tax_rate.max'     => 'Tax rate 100% se zyada nahi ho sakta',
        ];
    }
}