<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id'    => 'required|exists:products,id',
            'type'          => 'required|in:manual_in,manual_out,adjustment',
            'qty'           => 'required|numeric|min:0.01',
            'rate'          => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            'movement_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product select karo',
            'type.required'       => 'Movement type required hai',
            'type.in'             => 'Sirf manual_in, manual_out ya adjustment allowed hai',
            'qty.required'        => 'Quantity required hai',
            'qty.min'             => 'Quantity 0 se zyada honi chahiye',
        ];
    }
}