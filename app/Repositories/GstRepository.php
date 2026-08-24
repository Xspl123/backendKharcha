<?php

namespace App\Repositories;

use App\Models\GstReturn;
use App\Models\Invoice;
use App\Repositories\Interfaces\GstRepositoryInterface;
use App\Repositories\Traits\OrgScope;
use App\Repositories\Traits\ScopedCache;
use Illuminate\Support\Carbon;

class GstRepository implements GstRepositoryInterface
{
    use OrgScope, ScopedCache;

    private function invoiceQuery(string $period)
    {
        $date  = Carbon::createFromFormat('Y-m', $period);
        $start = $date->copy()->startOfMonth()->toDateString();
        $end   = $date->copy()->endOfMonth()->toDateString();

        return $this->scopeQuery(Invoice::query())
            ->whereBetween('invoice_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->with(['client', 'items']);
    }

    // Existing methods same hain — sirf invoiceQuery mein scopeQuery add kiya
    // Baaki GST logic unchanged

    public function getSummary(string $period): array
    {
        return $this->rememberScoped('gst', 'summary:' . $period, 300, function () use ($period) {
            $invoices = $this->invoiceQuery($period)->get(['sub_total', 'cgst', 'sgst', 'igst', 'total_amount']);
            return [
                'period'              => $period,
                'total_invoices'      => $invoices->count(),
                'total_taxable_value' => round($invoices->sum('sub_total'), 2),
                'total_cgst'          => round($invoices->sum('cgst'), 2),
                'total_sgst'          => round($invoices->sum('sgst'), 2),
                'total_igst'          => round($invoices->sum('igst'), 2),
                'total_tax'           => round($invoices->sum('cgst') + $invoices->sum('sgst') + $invoices->sum('igst'), 2),
                'total_invoice_value' => round($invoices->sum('total_amount'), 2),
            ];
        });
    }

    public function getReturns(array $filters): mixed
    {
        $suffix = 'returns:' . md5(json_encode($filters));

        return $this->rememberScoped('gst', $suffix, 180, function () use ($filters) {
            $query = $this->scopeQuery(GstReturn::query())->orderByDesc('period');
            if (!empty($filters['return_type'])) $query->where('return_type', $filters['return_type']);
            if (!empty($filters['period']))      $query->where('period', $filters['period']);
            if (!empty($filters['status']))      $query->where('status', $filters['status']);
            return $query->get();
        });
    }

    public function saveDraft(string $returnType, string $period): mixed
    {
        $data = $returnType === 'GSTR1' ? $this->getGstr1($period) : $this->getGstr3B($period);
        $return = GstReturn::updateOrCreate(
            ['user_id' => $this->userId(), 'return_type' => $returnType, 'period' => $period],
            ['status' => 'draft', 'data_snapshot' => $data]
        );
        $this->bumpScopedCache(['gst']);
        return $return;
    }

    public function markFiled(int $id): mixed
    {
        $return = $this->scopeQuery(GstReturn::query())->findOrFail($id);
        abort_if($return->isFiled(), 422, 'Return already filed.');
        $return->update(['status' => 'filed', 'filed_at' => now()]);
        $this->bumpScopedCache(['gst']);
        return $return->fresh();
    }

    // getGstr1, getGstr3B, hsnSummary etc — same as existing (no change needed)
    // Copy karo existing GstRepository se yeh methods
    public function getGstr1(string $period): array { return []; }
    public function getHsnSummary(string $period): array { return []; }
    public function getGstr3B(string $period): array { return []; }
}
