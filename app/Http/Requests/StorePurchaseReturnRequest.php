<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; 
    }

    public function rules(): array
    {
        return [
            'original_po_id' => 'required|exists:purchase_orders,id',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id' => 'required_without:items.*.purchase_order_item_id|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'return_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_refunded'             => 'nullable|boolean',
            'account_id'              => 'nullable|integer',
            'refund_amount'           => 'nullable|numeric|min:0',
            'payment_method'          => 'nullable|string|max:50',
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

    /**
     * Get validated data with org_id
     */
    public function validatedWithOrg(): array
    {
        $validated = $this->validated();
        
        // Add org_id and user_id from authenticated user
        $validated['org_id'] = Auth::user()->org_id;
        $validated['user_id'] = Auth::user()->id;
        
        return $validated;
    }
}
