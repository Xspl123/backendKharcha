<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadCustomFieldRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'label'       => 'required|string|max:255',
            'field_key'   => 'nullable|string|max:255|alpha_dash',
            'field_type'  => 'required|in:text,number,date,select',
            'options'     => 'nullable|required_if:field_type,select|array',
            'options.*'   => 'string|max:255',
            'is_required' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ];
    }
}