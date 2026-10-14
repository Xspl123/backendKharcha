<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrgTransactionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'account_id'          => 'required|exists:org_accounts,id',
            'transaction_date'    => 'required|date',
            'type'                => 'required|in:credit,debit',
            'category'            => 'required|string|max:100',
            'amount'              => 'required|numeric|min:0.01',
            'payment_method'      => 'nullable|string|max:50',
            'reference_no'        => 'nullable|string|max:100',
            'description'         => 'nullable|string',
            'transfer_account_id' => 'nullable|different:account_id|exists:org_accounts,id',
        ];
    }
}