<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Organisation;
use App\Models\User;
use App\Repositories\Interfaces\LeadRepositoryInterface;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SendNewWebLeadPushNotifications extends Command
{
    protected $signature = 'leads:push-new-web-leads';

    protected $description = 'Push a browser notification for each new, unactioned web-form lead (per tenant, per user) — fires once per lead, not repeated';

    public function handle(LeadRepositoryInterface $leads, PushNotificationService $push): int
    {
        $orgs = Organisation::with('tenant')
            ->where('is_active', true)
            ->whereNotNull('tenant_id')
            ->get();

        foreach ($orgs as $org) {
            if (! $org->tenant) continue;

            tenancy()->initialize($org->tenant);

            try {
                $primaryCompany = Company::where('org_id', $org->id)->oldest('id')->first();
                $orgIconUrl = $primaryCompany?->logo_url
                    ?: ($org->logo ? url('storage/' . $org->logo) : null);

                $users = User::where('org_id', $org->id)->where('is_active', true)->get();

                foreach ($users as $user) {
                    Auth::setUser($user);

                    // getNewWebLeads() already scopes to this user's own
                    // leads for sales_agent role, and to the whole org for
                    // other roles — same scoping the Navbar bell relies on.
                    // A lead naturally drops off this list the moment
                    // anyone changes its status away from 'new', so unlike
                    // follow-ups there's no "still pending" state to nag
                    // about — one push per lead is enough, ever.
                    $newWebLeads = $leads->getNewWebLeads();

                    foreach ($newWebLeads as $item) {
                        $cacheKey = "push_notified_weblead_{$user->id}_{$item['lead_id']}";
                        if (Cache::has($cacheKey)) continue;

                        $push->sendToUser($user->id, [
                            'title' => 'New Web Lead',
                            'body'  => $item['contact_person']
                                ? "{$item['company_name']} — {$item['contact_person']}"
                                : $item['company_name'],
                            'icon'  => $orgIconUrl,
                            'data'  => ['leadId' => $item['lead_id']],
                        ]);

                        // No expiry — a given lead should only ever trigger
                        // one push per user, same as the existing in-tab
                        // Notification dedupe already does via localStorage.
                        Cache::forever($cacheKey, true);
                    }

                    Auth::forgetGuards();
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->info('New web lead push notifications processed for ' . $orgs->count() . ' organisation(s).');

        return self::SUCCESS;
    }
}