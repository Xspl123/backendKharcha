<?php

namespace App\Services;

use App\Models\PurchaseOrder;

class PurchaseOrderNumberService
{
    public function generate(int $userId): string
    {
        $year   = date('Y');
        $prefix = "PO-{$year}-";

        $last = PurchaseOrder::where('user_id', $userId)
            ->where('po_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('po_number');

        $lastNumber = $last
            ? (int) substr($last, strlen($prefix))
            : 0;

        return $prefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        // Output: PO-2026-00001
    }
}