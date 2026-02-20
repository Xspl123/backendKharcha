<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'amount'            => 'required|numeric|min:0.01',
            'payment_date'      => 'required|date',
            'payment_method'    => 'required|in:cash,bank_transfer,cheque,upi,other',
            'reference_no'      => 'nullable|string|max:100',
            'notes'             => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'Purchase order select karo',
            'purchase_order_id.exists'   => 'Selected purchase order exist nahi karta',
            'amount.required'            => 'Amount required hai',
            'amount.min'                 => 'Amount 0 se zyada hona chahiye',
            'payment_date.required'      => 'Payment date required hai',
            'payment_method.required'    => 'Payment method required hai',
            'payment_method.in'          => 'Invalid payment method',
        ];
    }
}