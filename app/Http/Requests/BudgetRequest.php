<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Change this if you need authorization logic
    }

    public function rules(): array
{
    return [
        'category_id' => [
            'required',
            Rule::exists('categories', 'id')->where(function ($query) {
                return $query->where('user_id', Auth::id());
            }),
        ],
        'total_amount'  => 'nullable|numeric|min:0',
        'budget_amount' => 'nullable|numeric|min:0',
    ];
}
}
