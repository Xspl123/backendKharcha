<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Repositories\Interfaces\LeadRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PublicLeadController extends Controller
{
    public function __construct(
        private LeadRepositoryInterface $repo,
        private NotificationService $push,
    ) {}

    // GET /api/public/leads/{orgSlug}
    // Minimal, non-sensitive org info so the form can show a company name —
    // nothing else about the org (billing, members, settings) is exposed.
    public function show(Request $request)
    {
        $org = app('currentOrg');

        return response()->json([
            'data' => [
                'name' => $org->name,
                'logo' => $org->logo,
            ],
        ]);
    }

    // POST /api/public/leads/{orgSlug}
    public function store(Request $request)
    {
        $org = app('currentOrg');

        // Honeypot: a field real visitors never see or fill (hidden via CSS
        // on the frontend), but that bots which auto-fill every input on a
        // form will trip. We respond exactly like a normal success so bots
        // don't learn the trap exists, but never actually save the lead.
        if ($request->filled('hp_confirm')) {
            return response()->json([
                'message' => 'Thank you! We will get back to you soon.',
            ], 201);
        }

        // Basic rate limit, scoped per-IP *and* per-org, so a spam burst
        // against one org's public link can't exhaust a shared bucket that
        // would also block legitimate submissions to a different org.
        $key = 'public-lead:' . $org->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many submissions. Please try again later.',
            ], 429);
        }
        RateLimiter::hit($key, 3600); // max 5 submissions / IP / org / hour

        $data = $request->validate([
            'company_name'     => 'required|string|max:255',
            'contact_person'   => 'nullable|string|max:255',
            'phone'            => 'nullable|string|max:30',
            'email'            => 'nullable|email|max:255',
            'product_interest' => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:2000',
            'utm_source'       => 'nullable|string|max:255',
            'utm_medium'       => 'nullable|string|max:255',
            'utm_campaign'     => 'nullable|string|max:255',
            'utm_term'         => 'nullable|string|max:255',
            'utm_content'      => 'nullable|string|max:255',
        ]);

        if (empty($data['phone']) && empty($data['email'])) {
            return response()->json([
                'message' => 'Please provide a phone number or email so we can reach you.',
            ], 422);
        }

        // No logged-in user exists for a public submission, but `leads.user_id`
        // is a required (NOT NULL) column — the org owner is used as the
        // fallback creator/owner, same as any other system-generated lead.
        $lead = Lead::create(array_merge($data, [
            'org_id'   => $org->id,
            'user_id'  => $org->owner_id,
            'owner_id' => $org->owner_id,
            'source'   => 'website',
            'status'   => 'new',
        ]));

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $org->owner_id,
            'type'    => 'note',
            'note'    => 'Lead submitted via public web form.',
        ]);

        // Automatic duplicate detection — a web-form lead has no one
        // reviewing it before it lands in the CRM (unlike the Add Lead
        // form, where the person creating it sees an inline warning
        // immediately), so the org owner gets pushed a heads-up right
        // away instead of only discovering it later via the Leads list's
        // duplicate banner.
        $duplicates = $this->repo->findDuplicatesForOrg($lead, $org->id);
        if (!empty($duplicates)) {
            $names = collect($duplicates)->pluck('company_name')->implode(', ');
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $org->owner_id,
                'type'    => 'note',
                'note'    => "Possible duplicate detected — matches: {$names}",
            ]);
            $this->push->sendToUser($org->owner_id, [
                'title' => 'Possible Duplicate Lead',
                'body'  => "{$lead->company_name} matches: {$names}",
                'data'  => ['leadId' => $lead->id],
            ]);
        }

        return response()->json([
            'message' => 'Thank you! We will get back to you soon.',
        ], 201);
    }
}