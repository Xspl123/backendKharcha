<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'          => 'required|in:call,email,whatsapp,meeting,note,status_change',
            'note'          => 'nullable|string',
            'call_duration' => 'nullable|integer|min:0',
            'outcome'       => 'nullable|string|max:100',
        ];
    }
}