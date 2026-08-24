<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = $this->scopeQuery()->get();
        return response()->json(['data' => $companies]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:15',
            'website' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'bank_branch' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = auth()->id();
        if ($this->usesOrgScope()) {
            $validated['org_id'] = auth()->user()->org_id;
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('company-logos', 'public');
            $validated['logo'] = $path;
        }

        $company = Company::create($validated);

        return response()->json([
            'message' => 'Company created successfully',
            'company' => $company
        ], 201);
    }

    public function show($id)
    {
        $company = $this->scopeQuery()->findOrFail($id);
        return response()->json(['data' => $company]);
    }

    public function update(Request $request, $id)
    {
        $company = $this->scopeQuery()->findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:15',
            'website' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'bank_branch' => 'nullable|string|max:255',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $path = $request->file('logo')->store('company-logos', 'public');
            $validated['logo'] = $path;
        }

        $company->update($validated);

        return response()->json([
            'message' => 'Company updated successfully',
            'company' => $company
        ]);
    }

    public function destroy($id)
    {
        $company = $this->scopeQuery()->findOrFail($id);
        
        // Delete logo
        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }
        
        $company->delete();

        return response()->json([
            'message' => 'Company deleted successfully'
        ]);
    }

    private function scopeQuery(): Builder
    {
        $query = Company::query();

        if ($this->usesOrgScope()) {
            return $query->where('org_id', auth()->user()->org_id);
        }

        return $query->where('user_id', auth()->id());
    }

    private function usesOrgScope(): bool
    {
        $user = auth()->user();

        return $user && $user->hasOrg() && Schema::hasColumn('companies', 'org_id');
    }
}
