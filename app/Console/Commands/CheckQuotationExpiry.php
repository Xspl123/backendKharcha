<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\LeadActivity;
use App\Models\Organisation;
use App\Models\Quotation;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckQuotationExpiry extends Command
{
    protected $signature = 'quotations:check-expiry';

    protected $description = 'Reminds the owner ~2 days before a sent quotation expires, and auto-marks it Expired once the expiry date has passed';

    public function handle(NotificationService $notify): int
    {
        $orgs = Organisation::with('tenant')
            ->where('is_active', true)
            ->whereNotNull('tenant_id')
            ->get();

        $expiredCount = 0;
        $reminderCount = 0;

        foreach ($orgs as $org) {
            if (! $org->tenant) continue;

            tenancy()->initialize($org->tenant);

            try {
                $primaryCompany = Company::where('org_id', $org->id)->oldest('id')->first();
                $orgIconUrl = $primaryCompany?->logo_url
                    ?: ($org->logo ? url('storage/' . $org->logo) : null);

                // ── Reminder: quotation still "sent" and expiring within
                //    the next 2 days — nudge the owner to follow up while
                //    there's still time, not after it's already too late.
                $expiringSoon = Quotation::where('status', 'sent')
                    ->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(2)->toDateString()])
                    ->with(['lead:id,company_name,owner_id', 'client:id,company_name,user_id'])
                    ->get();

                foreach ($expiringSoon as $quotation) {
                    $ownerId = $quotation->lead?->owner_id ?? $quotation->client?->user_id;
                    if (! $ownerId) continue;

                    // One reminder per quotation, ever — not once per day
                    // for the whole 2-day window.
                    $cacheKey = "quotation_expiry_reminder_{$quotation->id}";
                    if (Cache::has($cacheKey)) continue;

                    $partyName = $quotation->lead?->company_name ?? $quotation->client?->company_name ?? 'the customer';
                    $notify->sendToUser($ownerId, [
                        'title' => 'Quotation Expiring Soon',
                        'body'  => "{$quotation->quotation_no} for {$partyName} expires on {$quotation->expiry_date->format('d M Y')} — follow up before it lapses.",
                        'icon'  => $orgIconUrl,
                        'data'  => ['leadId' => $quotation->lead_id],
                    ]);
                    Cache::forever($cacheKey, true);
                    $reminderCount++;
                }

                // ── Auto-expire: quotation still "sent" but the expiry
                //    date has already passed.
                $overdue = Quotation::where('status', 'sent')
                    ->whereNotNull('expiry_date')
                    ->where('expiry_date', '<', now()->toDateString())
                    ->with(['lead:id,company_name,owner_id', 'client:id,company_name,user_id'])
                    ->get();

                foreach ($overdue as $quotation) {
                    $quotation->update(['status' => 'expired']);

                    if ($quotation->lead_id) {
                        LeadActivity::create([
                            'lead_id' => $quotation->lead_id,
                            'user_id' => $quotation->lead?->owner_id,
                            'type'    => 'status_change',
                            'note'    => "Quotation {$quotation->quotation_no} auto-marked as Expired (past expiry date {$quotation->expiry_date->format('d M Y')}).",
                        ]);
                    }

                    $ownerId = $quotation->lead?->owner_id ?? $quotation->client?->user_id;
                    if ($ownerId) {
                        $partyName = $quotation->lead?->company_name ?? $quotation->client?->company_name ?? 'the customer';
                        $notify->sendToUser($ownerId, [
                            'title' => 'Quotation Expired',
                            'body'  => "{$quotation->quotation_no} for {$partyName} has expired without a response.",
                            'icon'  => $orgIconUrl,
                            'data'  => ['leadId' => $quotation->lead_id],
                        ]);
                    }
                    $expiredCount++;
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Quotation expiry check done — {$reminderCount} reminder(s), {$expiredCount} auto-expired.");

        return self::SUCCESS;
    }
}