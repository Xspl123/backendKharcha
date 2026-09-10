<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendLeadEmailRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'to'      => 'nullable|email|max:255', // defaults to the lead's own email if omitted
            'cc'      => 'nullable|email|max:255',
            'subject' => 'required|string|max:255',
            'body'    => 'required|string|max:20000',
        ];
    }
}