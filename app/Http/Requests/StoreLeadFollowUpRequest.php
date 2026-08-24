<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadFollowUpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'due_date' => 'required|date|after:now',
            'note'     => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'due_date.required' => 'Follow-up date required.',
            'due_date.after'    => 'Follow-up date must be in the future.',
        ];
    }
}