<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganisationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'email'      => 'nullable|email|max:255',
            'phone'      => 'nullable|string|max:20',
            'address'    => 'nullable|string',
            'city'       => 'nullable|string|max:100',
            'country'    => 'nullable|string|max:100',
            'gst_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Organisation name required.'];
    }
}