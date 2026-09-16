<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadWorkflowRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => 'sometimes|required|string|max:255',
            'trigger_type'    => 'nullable|in:status_change,quotation_status_change',
            'trigger_status'  => [
                'sometimes', 'required', 'string',
                function ($attribute, $value, $fail) {
                    $type = $this->input('trigger_type', 'status_change');
                    $leadStatuses = ['new','contact_attempted','connected','requirement_discussion','quotation_sent','negotiation','positive_response','po_received','invoice_generated','closed_won','closed_lost'];
                    $quotationStatuses = ['draft','sent','approved','rejected','expired'];
                    $valid = $type === 'quotation_status_change' ? $quotationStatuses : $leadStatuses;
                    if (!in_array($value, $valid, true)) {
                        $fail('The selected trigger status is not valid for this trigger type.');
                    }
                },
            ],
            'action_type'     => 'sometimes|required|in:notify_owner',
            'action_message'  => 'nullable|string|max:255',
            'is_active'       => 'nullable|boolean',
        ];
    }
}