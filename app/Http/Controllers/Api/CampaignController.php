<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CampaignController extends Controller
{
    // GET /api/campaigns
    public function index(Request $request)
    {
        $campaigns = $this->scopeQuery()
            ->withCount('leads')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => CampaignResource::collection($campaigns)]);
    }

    // POST /api/campaigns
    public function store(StoreCampaignRequest $request)
    {
        $campaign = Campaign::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
            'org_id'  => $this->usesOrgScope() ? Auth::user()->org_id : null,
        ]);

        return response()->json([
            'message' => 'Campaign created.',
            'data'    => new CampaignResource($campaign),
        ], 201);
    }

    // GET /api/campaigns/{id}
    public function show(int $id)
    {
        $campaign = $this->scopeQuery()
            ->withCount('leads')
            ->with('leads')
            ->findOrFail($id);

        return response()->json(['data' => new CampaignResource($campaign)]);
    }

    // PUT /api/campaigns/{id}
    public function update(StoreCampaignRequest $request, int $id)
    {
        $campaign = $this->scopeQuery()->findOrFail($id);
        $campaign->update($request->validated());

        return response()->json([
            'message' => 'Campaign updated.',
            'data'    => new CampaignResource($campaign->fresh()),
        ]);
    }

    // DELETE /api/campaigns/{id}
    public function destroy(int $id)
    {
        $this->scopeQuery()->findOrFail($id)->delete();
        return response()->json(['message' => 'Campaign deleted.']);
    }

    // POST /api/campaigns/{id}/leads
    public function attachLeads(Request $request, int $id)
    {
        $request->validate([
            'lead_ids'   => 'required|array',
        ]);

        $campaign = $this->scopeQuery()->findOrFail($id);
        $leadIds = $this->scopedLeadsQuery()
            ->whereIn('id', $request->lead_ids)
            ->pluck('id')
            ->all();

        if (count($leadIds) !== count($request->lead_ids)) {
            return response()->json(['message' => 'One or more leads are invalid for your scope.'], 422);
        }

        $campaign->leads()->syncWithoutDetaching($leadIds);

        return response()->json([
            'message'     => 'Leads added to campaign.',
            'leads_count' => $campaign->leads()->count(),
        ]);
    }

    // DELETE /api/campaigns/{id}/leads/{leadId}
    public function detachLead(int $id, int $leadId)
    {
        $campaign = $this->scopeQuery()->findOrFail($id);
        $this->scopedLeadsQuery()->findOrFail($leadId);
        $campaign->leads()->detach($leadId);
        return response()->json(['message' => 'Lead removed from campaign.']);
    }

    private function scopeQuery(): Builder
    {
        $query = Campaign::query();

        if ($this->usesOrgScope()) {
            return $query->where('org_id', Auth::user()->org_id);
        }

        return $query->where('user_id', Auth::id());
    }

    private function scopedLeadsQuery(): Builder
    {
        $query = Lead::query();

        if (Auth::user()->hasOrg()) {
            return $query->where('org_id', Auth::user()->org_id);
        }

        return $query->where('user_id', Auth::id());
    }

    private function usesOrgScope(): bool
    {
        $user = Auth::user();

        return $user && $user->hasOrg() && Schema::hasColumn('campaigns', 'org_id');
    }
}
