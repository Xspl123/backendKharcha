<?php

namespace App\Repositories;

use App\Models\Vendor;
use App\Repositories\Interfaces\VendorRepositoryInterface;

class VendorRepository implements VendorRepositoryInterface
{
    // ── Get All ────────────────────────────────────────────

    public function getAll(array $filters): mixed
    {
        $query = Vendor::where('user_id', auth()->id())
            ->withCount('purchaseOrders')
            ->orderByDesc('created_at');

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('vendor_name',  'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email',        'like', "%{$search}%")
                  ->orWhere('phone',        'like', "%{$search}%")
                  ->orWhere('gstin',        'like', "%{$search}%");
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    // ── Get By ID ──────────────────────────────────────────

    public function getById(int $id): mixed
    {
        return Vendor::where('user_id', auth()->id())
            ->with([
                'purchaseOrders' => fn($q) => $q->latest()->take(5),
                'payments'       => fn($q) => $q->latest()->take(5),
            ])
            ->findOrFail($id);
    }

    // ── Create ─────────────────────────────────────────────

    public function create(array $data): mixed
    {
        return Vendor::create([
            ...$data,
            'user_id' => auth()->id(),
        ]);
    }

    // ── Update ─────────────────────────────────────────────

    public function update(int $id, array $data): mixed
    {
        $vendor = Vendor::where('user_id', auth()->id())->findOrFail($id);
        $vendor->update($data);
        return $vendor->fresh();
    }

    // ── Delete ─────────────────────────────────────────────

    public function delete(int $id): bool
    {
        $vendor = Vendor::where('user_id', auth()->id())->findOrFail($id);

        // Check karo koi active PO toh nahi
        $activePOs = $vendor->purchaseOrders()
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        abort_if(
            $activePOs > 0,
            422,
            'Vendor ke active purchase orders hain. Pehle unhe cancel ya complete karo.'
        );

        return $vendor->delete();
    }

    // ── Summary ────────────────────────────────────────────

    public function getSummary(): array
    {
        $vendors = Vendor::where('user_id', auth()->id())
            ->withSum(['purchaseOrders as total_purchases' => fn($q) =>
                $q->whereIn('status', ['approved', 'received'])
            ], 'total_amount')
            ->withSum('payments as total_paid', 'amount')
            ->get();

        return [
            'total_vendors'   => $vendors->count(),
            'active_vendors'  => $vendors->where('status', 'active')->count(),
            'inactive_vendors'=> $vendors->where('status', 'inactive')->count(),
            'total_purchases' => round($vendors->sum('total_purchases'), 2),
            'total_paid'      => round($vendors->sum('total_paid'), 2),
            'total_balance'   => round(
                $vendors->sum('total_purchases') - $vendors->sum('total_paid'), 2
            ),
        ];
    }
}