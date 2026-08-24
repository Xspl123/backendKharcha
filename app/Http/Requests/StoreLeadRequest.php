<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRequestRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    use TenantRequestRules;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'company_name'        => 'required|string|max:255',
            'contact_person'      => 'nullable|string|max:255',
            'phone'               => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:255',
            'website'             => 'nullable|url|max:255',
            'country'             => 'nullable|string|max:100',
            'city'                => 'nullable|string|max:100',
            'source'              => 'nullable|in:website,indiamart,whatsapp,referral,cold_call,email,social_media,other',
            'product_interest'    => 'nullable|string|max:255',
            'budget'              => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|max:10',
            'notes'               => 'nullable|string',
            'status'              => 'nullable|in:new,contact_attempted,connected,requirement_discussion,quotation_sent,negotiation,positive_response,po_received,invoice_generated,closed_won,closed_lost',
            'owner_id'            => [
                'nullable',
                $this->centralExists(User::class, 'id', function ($query) use ($user) {
                    return $user && $user->org_id
                        ? $query->where('org_id', $user->org_id)
                        : $query->where('id', $user?->id ?? 0);
                }),
            ],
            'expected_close_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required.',
            'email.email'           => 'Valid email address required.',
            'owner_id.exists'       => 'Selected agent does not exist.',
        ];
    }
}
