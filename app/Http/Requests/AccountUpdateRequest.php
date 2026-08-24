<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->route('id') ?? $this->route('account');

        return [
            'account_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('accounts')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                })->ignore($accountId),
            ],
            'account_type' => ['nullable', Rule::in([Account::TYPE_CASH, Account::TYPE_BANK, Account::TYPE_CREDIT_CARD])],
            'account_balance' => ['nullable', 'numeric'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'required_if:account_type,' . Account::TYPE_CREDIT_CARD],
            'billing_cycle_day' => ['nullable', 'integer', 'between:1,31'],
            'payment_due_day' => ['nullable', 'integer', 'between:1,31'],
        ];
    }
}
