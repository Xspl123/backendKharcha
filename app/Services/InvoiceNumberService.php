<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * Financial Year String nikaalta hai (e.g. "26-27")
     */
    public static function getFinancialYear(): string
    {
        $month = (int) now()->format('m');
        $year  = (int) now()->format('y');

        if ($month >= 4) {
            $nextYear = $year + 1;
            return sprintf('%02d-%02d', $year, $nextYear);
        } else {
            $prevYear = $year - 1;
            return sprintf('%02d-%02d', $prevYear, $year);
        }
    }

    public static function generate(int $orgId, ?string $customPrefix = null): string
    {
        return retry(5, function () use ($orgId, $customPrefix) {
            return DB::transaction(function () use ($orgId, $customPrefix) {
                $fy     = self::getFinancialYear();
                $prefix = $customPrefix ?: (auth()->user()?->invoice_prefix ?: 'INV');

                $seq = DB::table('invoice_sequences')
                    ->where('tenant_id', $orgId)
                    ->where('year', (int) now()->format('Y')) // sequence tracking
                    ->lockForUpdate()
                    ->first();

                $next = $seq ? $seq->current_no + 1 : 1;

                // Double check uniqueness
                $candidate = sprintf('%s/%s/%04d', $prefix, $fy, $next);
                while (Invoice::where('invoice_no', $candidate)->exists()) {
                    $next++;
                    $candidate = sprintf('%s/%s/%04d', $prefix, $fy, $next);
                }

                if ($seq) {
                    DB::table('invoice_sequences')
                        ->where('id', $seq->id)
                        ->update(['current_no' => $next, 'updated_at' => now()]);
                } else {
                    DB::table('invoice_sequences')->insert([
                        'tenant_id'  => $orgId,
                        'year'       => (int) now()->format('Y'),
                        'current_no' => $next,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $candidate;
            });
        }, 50);
    }
}