<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('accounts')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                }),
            ],
            'account_type' => ['nullable', Rule::in([Account::TYPE_CASH, Account::TYPE_BANK, Account::TYPE_CREDIT_CARD])],
            'account_balance' => ['nullable', 'numeric'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'required_if:account_type,' . Account::TYPE_CREDIT_CARD],
            'billing_cycle_day' => ['nullable', 'integer', 'between:1,31'],
            'payment_due_day' => ['nullable', 'integer', 'between:1,31'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'account_type' => $this->input('account_type', Account::TYPE_CASH),
            'account_balance' => $this->input('account_balance', 0),
        ]);
    }
}
