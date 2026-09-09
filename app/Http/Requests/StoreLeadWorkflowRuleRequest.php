<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadWorkflowRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'trigger_status'  => 'required|in:new,contact_attempted,connected,requirement_discussion,quotation_sent,negotiation,positive_response,po_received,invoice_generated,closed_won,closed_lost',
            'action_type'     => 'required|in:notify_owner',
            'action_message'  => 'nullable|string|max:255',
            'is_active'       => 'nullable|boolean',
        ];
    }
}