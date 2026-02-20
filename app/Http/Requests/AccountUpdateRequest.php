<?php

namespace App\Http\Requests;

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
        $accountId = $this->route('account'); // Assuming 'account' is the route parameter for the account ID

        return [
            'account_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('accounts')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                })->ignore($accountId), // Exclude the current account ID for updates
            ],
            'account_balance' => 'nullable',
        ];
    }
}
