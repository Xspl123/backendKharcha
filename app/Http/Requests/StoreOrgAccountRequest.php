<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrgAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'account_type'    => 'required|in:bank,cash,upi,other',
            'bank_name'       => 'nullable|string|max:255',
            'account_number'  => 'nullable|string|max:50',
            'ifsc_code'       => 'nullable|string|max:20',
            'upi_id'          => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric',
            'is_default'      => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'notes'           => 'nullable|string',
        ];
    }
}