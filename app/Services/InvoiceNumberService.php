<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public static function generate($tenantId)
    {
        return retry(5, function () use ($tenantId) {

            return DB::transaction(function () use ($tenantId) {

                $year = now()->year;

                $seq = DB::table('invoice_sequences')
                    ->where('tenant_id', $tenantId)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if (!$seq) {
                    DB::table('invoice_sequences')->insert([
                        'tenant_id' => $tenantId,
                        'year' => $year,
                        'current_no' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $next = 1;
                } else {
                    $next = $seq->current_no + 1;

                    DB::table('invoice_sequences')
                        ->where('id', $seq->id)
                        ->update([
                            'current_no' => $next,
                            'updated_at' => now(),
                        ]);
                }

                $prefix = auth()->user()->invoice_prefix ?? 'INV';

                return sprintf('%s-%d-%05d', $prefix, $year, $next);
            });

        }, 50);
    }
}
