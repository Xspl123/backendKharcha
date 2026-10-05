<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public static function generate($orgId)
    {
        return retry(5, function () use ($orgId) {
            return DB::transaction(function () use ($orgId) {
                $year   = now()->year;
                $prefix = auth()->user()->invoice_prefix ?? 'INV';

                $seq = DB::table('invoice_sequences')
                    ->where('tenant_id', $orgId)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                $next = $seq ? $seq->current_no + 1 : 1;

                // number pehle se maujood ho to aage badho
                while (Invoice::where('invoice_no', sprintf('%s-%d-%05d', $prefix, $year, $next))->exists()) {
                    $next++;
                }

                if ($seq) {
                    DB::table('invoice_sequences')
                        ->where('id', $seq->id)
                        ->update(['current_no' => $next, 'updated_at' => now()]);
                } else {
                    DB::table('invoice_sequences')->insert([
                        'tenant_id'  => $orgId,
                        'year'       => $year,
                        'current_no' => $next,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return sprintf('%s-%d-%05d', $prefix, $year, $next);
            });
        }, 50);
    }
}
