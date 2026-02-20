<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vendor_name'      => 'required|string|max:255',
            'company_name'     => 'nullable|string|max:255',
            'email'            => 'nullable|email|max:255',
            'phone'            => 'nullable|string|max:20',
            'address'          => 'nullable|string',
            'city'             => 'nullable|string|max:100',
            'state'            => 'nullable|string|max:100',
            'pincode'          => 'nullable|string|max:10',
            'country'          => 'nullable|string|max:100',
            'gstin'            => 'nullable|string|max:15',
            'pan'              => 'nullable|string|max:10',
            'bank_name'        => 'nullable|string|max:255',
            'bank_account_no'  => 'nullable|string|max:50',
            'bank_ifsc'        => 'nullable|string|max:20',
            'bank_branch'      => 'nullable|string|max:255',
            'status'           => 'nullable|in:active,inactive',
            'notes'            => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_name.required' => 'Vendor name required hai',
            'email.email'          => 'Valid email address dalo',
            'gstin.max'            => 'GSTIN 15 characters se zyada nahi ho sakta',
            'pan.max'              => 'PAN 10 characters se zyada nahi ho sakta',
        ];
    }
}