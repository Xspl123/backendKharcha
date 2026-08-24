<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status'      => 'required|in:new,contact_attempted,connected,requirement_discussion,quotation_sent,negotiation,positive_response,po_received,invoice_generated,closed_won,closed_lost',
            'lost_reason' => 'required_if:status,closed_lost|nullable|string|max:255',
        ];
    }
}