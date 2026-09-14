<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendQuotationEmailRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'to'          => 'required|email|max:255',
            'cc'          => 'nullable|email|max:255',
            'subject'     => 'required|string|max:255',
            'body'        => 'required|string|max:20000',
            'pdf_base64'  => 'required|string', // the PDF, generated client-side, base64-encoded (no data-URI prefix)
        ];
    }
}