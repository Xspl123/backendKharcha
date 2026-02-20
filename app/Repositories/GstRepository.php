<?php

namespace App\Repositories;

use App\Models\GstReturn;
use App\Models\Invoice;
use App\Repositories\Interfaces\GstRepositoryInterface;
use Illuminate\Support\Carbon;

class GstRepository implements GstRepositoryInterface
{
    // ── Base Query ─────────────────────────────────────────

    private function invoiceQuery(string $period)
    {
        $date  = Carbon::createFromFormat('Y-m', $period);
        $start = $date->copy()->startOfMonth()->toDateString();
        $end   = $date->copy()->endOfMonth()->toDateString();

        return Invoice::where('user_id', auth()->id())
            ->whereBetween('invoice_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->with(['client', 'items']);
    }

    // ── GST Summary ────────────────────────────────────────

    public function getSummary(string $period): array
    {
        $invoices = $this->invoiceQuery($period)->get();

        return [
            'period'              => $period,
            'total_invoices'      => $invoices->count(),
            'total_taxable_value' => round($invoices->sum('sub_total'), 2),
            'total_cgst'          => round($invoices->sum('cgst'), 2),
            'total_sgst'          => round($invoices->sum('sgst'), 2),
            'total_igst'          => round($invoices->sum('igst'), 2),
            'total_tax'           => round(
                                        $invoices->sum('cgst') +
                                        $invoices->sum('sgst') +
                                        $invoices->sum('igst'), 2),
            'total_invoice_value' => round($invoices->sum('total_amount'), 2),
            'b2b_count'           => $invoices->where('invoice_type', 'b2b')->count(),
            'b2cs_count'          => $invoices->where('invoice_type', 'b2cs')->count(),
            'b2cl_count'          => $invoices->where('invoice_type', 'b2cl')->count(),
            'export_count'        => $invoices->where('invoice_type', 'export')->count(),
        ];
    }

    // ── GSTR-1 ─────────────────────────────────────────────

    public function getGstr1(string $period): array
    {
        $invoices = $this->invoiceQuery($period)->get();

        return [
            'period'      => $period,
            'b2b'         => $this->gstr1B2B($invoices),
            'b2cs'        => $this->gstr1B2CS($invoices),
            'b2cl'        => $this->gstr1B2CL($invoices),
            'exports'     => $this->gstr1Exports($invoices),
            'hsn_summary' => $this->hsnSummary($invoices),
            'summary'     => [
                'total_taxable_value' => round($invoices->sum('sub_total'), 2),
                'total_cgst'          => round($invoices->sum('cgst'), 2),
                'total_sgst'          => round($invoices->sum('sgst'), 2),
                'total_igst'          => round($invoices->sum('igst'), 2),
                'total_tax'           => round(
                                            $invoices->sum('cgst') +
                                            $invoices->sum('sgst') +
                                            $invoices->sum('igst'), 2),
                'total_invoice_value' => round($invoices->sum('total_amount'), 2),
            ],
        ];
    }

    // B2B — client ke paas GSTIN hai, invoice-wise detail
    private function gstr1B2B($invoices): array
    {
        return $invoices
            ->where('invoice_type', 'b2b')
            ->groupBy(fn($inv) => $inv->client->gstin ?? 'UNKNOWN')
            ->map(function ($group, $gstin) {
                return [
                    'gstin'         => $gstin,
                    'receiver_name' => $group->first()->client->company_name ?? '',
                    'invoices'      => $group->map(fn($inv) => $this->invoiceRow($inv))->values(),
                    'total_taxable' => round($group->sum('sub_total'), 2),
                    'total_cgst'    => round($group->sum('cgst'), 2),
                    'total_sgst'    => round($group->sum('sgst'), 2),
                    'total_igst'    => round($group->sum('igst'), 2),
                ];
            })
            ->values()
            ->toArray();
    }

    // B2CS — no GSTIN, amount < 2.5L, grouped by state + supply_type
    private function gstr1B2CS($invoices): array
    {
        return $invoices
            ->where('invoice_type', 'b2cs')
            ->groupBy(fn($inv) => ($inv->place_of_supply ?? 'NA') . '_' . $inv->supply_type)
            ->map(function ($group, $key) {
                [$pos, $supplyType] = explode('_', $key);
                return [
                    'place_of_supply'     => $pos,
                    'place_of_supply_name'=> $this->stateName($pos),
                    'supply_type'         => $supplyType,
                    'total_taxable_value' => round($group->sum('sub_total'), 2),
                    'total_cgst'          => round($group->sum('cgst'), 2),
                    'total_sgst'          => round($group->sum('sgst'), 2),
                    'total_igst'          => round($group->sum('igst'), 2),
                    'invoice_count'       => $group->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    // B2CL — no GSTIN, amount >= 2.5L, inter-state, invoice-wise
    private function gstr1B2CL($invoices): array
    {
        return $invoices
            ->where('invoice_type', 'b2cl')
            ->map(fn($inv) => array_merge(
                $this->invoiceRow($inv),
                ['place_of_supply' => $inv->place_of_supply ?? '']
            ))
            ->values()
            ->toArray();
    }

    // Exports
    private function gstr1Exports($invoices): array
    {
        return $invoices
            ->where('invoice_type', 'export')
            ->map(fn($inv) => $this->invoiceRow($inv))
            ->values()
            ->toArray();
    }

    // Single invoice row format
    private function invoiceRow(Invoice $inv): array
    {
        return [
            'invoice_no'        => $inv->invoice_no,
            'invoice_date'      => $inv->invoice_date->format('d-m-Y'),
            'invoice_value'     => round($inv->total_amount, 2),
            'taxable_value'     => round($inv->sub_total, 2),
            'supply_type'       => $inv->supply_type,
            'invoice_type'      => $inv->invoice_type,
            'place_of_supply'   => $inv->place_of_supply ?? '',
            'is_reverse_charge' => $inv->is_reverse_charge ? 'Y' : 'N',
            'cgst'              => round($inv->cgst, 2),
            'sgst'              => round($inv->sgst, 2),
            'igst'              => round($inv->igst, 2),
            'total_tax'         => round($inv->cgst + $inv->sgst + $inv->igst, 2),
            'client_name'       => $inv->client->company_name ?? '',
            'client_gstin'      => $inv->client->gstin ?? '',
        ];
    }

    // ── HSN Summary ────────────────────────────────────────

    public function getHsnSummary(string $period): array
    {
        $invoices = $this->invoiceQuery($period)->get();
        return $this->hsnSummary($invoices);
    }

    private function hsnSummary($invoices): array
    {
        $hsnData = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $hsn = $item->hsn_code ?? 'NA';

                if (!isset($hsnData[$hsn])) {
                    $hsnData[$hsn] = [
                        'hsn_code'      => $hsn,
                        'description'   => $item->description ?? $item->item_name,
                        'uqc'           => strtoupper($item->unit ?? 'NOS'),
                        'total_qty'     => 0,
                        'total_value'   => 0,
                        'taxable_value' => 0,
                        'total_cgst'    => 0,
                        'total_sgst'    => 0,
                        'total_igst'    => 0,
                    ];
                }

                $taxable = (float) $item->amount;
                $isIntra = $invoice->supply_type === 'intra';

                $cgst = $isIntra ? round($taxable * $item->tax_rate / 200, 2) : 0;
                $sgst = $isIntra ? round($taxable * $item->tax_rate / 200, 2) : 0;
                $igst = !$isIntra ? round($taxable * $item->tax_rate / 100, 2) : 0;

                $hsnData[$hsn]['total_qty']     += (float) $item->qty;
                $hsnData[$hsn]['total_value']   += $taxable + $cgst + $sgst + $igst;
                $hsnData[$hsn]['taxable_value'] += $taxable;
                $hsnData[$hsn]['total_cgst']    += $cgst;
                $hsnData[$hsn]['total_sgst']    += $sgst;
                $hsnData[$hsn]['total_igst']    += $igst;
            }
        }

        return collect($hsnData)->map(fn($row) => [
            'hsn_code'      => $row['hsn_code'],
            'description'   => $row['description'],
            'uqc'           => $row['uqc'],
            'total_qty'     => round($row['total_qty'], 2),
            'total_value'   => round($row['total_value'], 2),
            'taxable_value' => round($row['taxable_value'], 2),
            'total_cgst'    => round($row['total_cgst'], 2),
            'total_sgst'    => round($row['total_sgst'], 2),
            'total_igst'    => round($row['total_igst'], 2),
        ])->values()->toArray();
    }

    // ── GSTR-3B ────────────────────────────────────────────

    public function getGstr3B(string $period): array
    {
        $invoices = $this->invoiceQuery($period)->get();

        $intra   = $invoices->where('supply_type', 'intra');
        $inter   = $invoices->where('supply_type', 'inter');
        $exports = $invoices->where('invoice_type', 'export');
        $rcm     = $invoices->where('is_reverse_charge', true);

        return [
            'period' => $period,

            // Table 3.1 — Outward Supplies
            'outward_supplies' => [
                'intra_taxable' => [
                    'taxable_value' => round($intra->whereNotIn('invoice_type', ['export'])->sum('sub_total'), 2),
                    'cgst'          => round($intra->sum('cgst'), 2),
                    'sgst'          => round($intra->sum('sgst'), 2),
                    'igst'          => 0,
                ],
                'inter_taxable' => [
                    'taxable_value' => round($inter->whereNotIn('invoice_type', ['export'])->sum('sub_total'), 2),
                    'cgst'          => 0,
                    'sgst'          => 0,
                    'igst'          => round($inter->sum('igst'), 2),
                ],
                'exports' => [
                    'taxable_value' => round($exports->sum('sub_total'), 2),
                    'igst'          => round($exports->sum('igst'), 2),
                ],
                'reverse_charge' => [
                    'taxable_value' => round($rcm->sum('sub_total'), 2),
                    'cgst'          => round($rcm->sum('cgst'), 2),
                    'sgst'          => round($rcm->sum('sgst'), 2),
                    'igst'          => round($rcm->sum('igst'), 2),
                ],
            ],

            // Table 3.2 — Inter-state breakup by state
            'inter_state_supplies' => $inter
                ->groupBy(fn($inv) => $inv->place_of_supply ?? 'NA')
                ->map(fn($group, $pos) => [
                    'place_of_supply'      => $pos,
                    'place_of_supply_name' => $this->stateName($pos),
                    'taxable_value'        => round($group->sum('sub_total'), 2),
                    'igst'                 => round($group->sum('igst'), 2),
                ])
                ->values()
                ->toArray(),

            // Table 6 — Tax Liability Summary
            'tax_liability' => [
                'total_cgst' => round($invoices->sum('cgst'), 2),
                'total_sgst' => round($invoices->sum('sgst'), 2),
                'total_igst' => round($invoices->sum('igst'), 2),
                'total_tax'  => round(
                                    $invoices->sum('cgst') +
                                    $invoices->sum('sgst') +
                                    $invoices->sum('igst'), 2),
            ],
        ];
    }

    // ── Returns Management ─────────────────────────────────

    public function getReturns(array $filters): mixed
    {
        $query = GstReturn::where('user_id', auth()->id())
            ->orderByDesc('period');

        if (!empty($filters['return_type'])) {
            $query->where('return_type', $filters['return_type']);
        }
        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function saveDraft(string $returnType, string $period): mixed
    {
        $data = $returnType === 'GSTR1'
            ? $this->getGstr1($period)
            : $this->getGstr3B($period);

        return GstReturn::updateOrCreate(
            [
                'user_id'     => auth()->id(),
                'return_type' => $returnType,
                'period'      => $period,
            ],
            [
                'status'        => 'draft',
                'data_snapshot' => $data,
            ]
        );
    }

    public function markFiled(int $id): mixed
    {
        $return = GstReturn::where('user_id', auth()->id())->findOrFail($id);

        abort_if($return->isFiled(), 422, 'Return already filed.');

        $return->update([
            'status'   => 'filed',
            'filed_at' => now(),
        ]);

        return $return->fresh();
    }

    // ── Helper ─────────────────────────────────────────────

    private function stateName(string $code): string
    {
        $states = [
            '01' => 'Jammu & Kashmir',   '02' => 'Himachal Pradesh',
            '03' => 'Punjab',             '04' => 'Chandigarh',
            '05' => 'Uttarakhand',        '06' => 'Haryana',
            '07' => 'Delhi',              '08' => 'Rajasthan',
            '09' => 'Uttar Pradesh',      '10' => 'Bihar',
            '11' => 'Sikkim',             '12' => 'Arunachal Pradesh',
            '13' => 'Nagaland',           '14' => 'Manipur',
            '15' => 'Mizoram',            '16' => 'Tripura',
            '17' => 'Meghalaya',          '18' => 'Assam',
            '19' => 'West Bengal',        '20' => 'Jharkhand',
            '21' => 'Odisha',             '22' => 'Chhattisgarh',
            '23' => 'Madhya Pradesh',     '24' => 'Gujarat',
            '26' => 'Dadra & NH',         '27' => 'Maharashtra',
            '28' => 'Andhra Pradesh',     '29' => 'Karnataka',
            '30' => 'Goa',                '32' => 'Kerala',
            '33' => 'Tamil Nadu',         '34' => 'Puducherry',
            '36' => 'Telangana',          '37' => 'Andhra Pradesh (New)',
            '38' => 'Ladakh',
        ];

        return $states[$code] ?? 'Unknown';
    }
}