<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Organisation;
use App\Models\User;
use App\Repositories\Interfaces\LeadRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SendFollowUpPushReminders extends Command
{
    protected $signature = 'leads:push-followup-reminders';

    protected $description = 'Push a browser notification for each due/overdue lead follow-up (per tenant, per user), repeating every ~3 hours until the follow-up is marked done';

    public function handle(LeadRepositoryInterface $leads, NotificationService $push): int
    {
        $orgs = Organisation::with('tenant')
            ->where('is_active', true)
            ->whereNotNull('tenant_id')
            ->get();

        foreach ($orgs as $org) {
            if (! $org->tenant) continue;

            tenancy()->initialize($org->tenant);

            try {
                // Same fallback order the Sidebar uses for branding
                // (companies[0]?.logo_url || organisation?.logo): Company
                // is tenant-scoped, so it can only be queried after
                // tenancy()->initialize() above — unlike Organisation.logo,
                // which lives centrally and could be read earlier, but is
                // kept here so both checks read the same way.
                $primaryCompany = Company::where('org_id', $org->id)->oldest('id')->first();
                $orgIconUrl = $primaryCompany?->logo_url
                    ?: ($org->logo ? url('storage/' . $org->logo) : null);

                $users = User::where('org_id', $org->id)->where('is_active', true)->get();

                foreach ($users as $user) {
                    Auth::setUser($user);

                    // getDueFollowUps() already scopes to this user's own
                    // leads for sales_agent role (see LeadRepository), and
                    // to the whole org for other roles — same scoping the
                    // Navbar bell relies on, so behaviour stays consistent.
                    $dueFollowUps = $leads->getDueFollowUps();

                    foreach ($dueFollowUps as $item) {
                        // Repeat the push every 3 hours for as long as the
                        // follow-up stays undone (not date-based anymore —
                        // once the cache entry expires, the next scheduler
                        // run picks it up again, so a missed/dismissed
                        // notification doesn't mean silence until tomorrow).
                        $cacheKey = "push_notified_followup_{$user->id}_{$item['follow_up_id']}";
                        if (Cache::has($cacheKey)) continue;

                        $push->sendToUser($user->id, [
                            'title' => $item['is_overdue'] ? 'Overdue Follow-up' : 'Follow-up Due Today',
                            'body'  => $item['company_name'] . ($item['note'] ? " — {$item['note']}" : ''),
                            'icon'  => $orgIconUrl, // null is fine — sw.js falls back to the default icon
                            'data'  => ['leadId' => $item['lead_id']],
                        ]);

                        Cache::put($cacheKey, true, now()->addHours(3));
                    }

                    Auth::forgetGuards();
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->info('Follow-up push reminders processed for ' . $orgs->count() . ' organisation(s).');

        return self::SUCCESS;
    }
}