<?php

namespace App\Jobs;

use App\Models\ExportRequest;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public function __construct(private readonly int $exportRequestId)
    {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $export = ExportRequest::findOrFail($this->exportRequestId);
        $export->update(['status' => 'processing', 'error_message' => null]);

        $relativePath = 'exports/' . now()->format('Y/m') . "/export-{$export->id}.csv";
        $fullPath = Storage::disk($export->file_disk)->path($relativePath);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($fullPath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create export file.');
        }

        try {
            [$headers, $builder] = $this->buildQuery($export);

            fputcsv($handle, $headers);

            $builder->chunkById(500, function ($rows) use ($handle, $export) {
                foreach ($rows as $row) {
                    fputcsv($handle, $this->mapRow($export->type, $row));
                }
            });

            fclose($handle);

            $export->update([
                'status' => 'completed',
                'file_path' => $relativePath,
                'finished_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            fclose($handle);

            $export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function buildQuery(ExportRequest $export): array
    {
        $filters = $export->filters ?? [];

        return match ($export->type) {
            'invoices' => [
                ['id', 'invoice_no', 'invoice_date', 'status', 'sub_total', 'total_amount', 'paid_amount', 'balance_amount'],
                $this->scope(Invoice::query()->select([
                    'id', 'user_id', 'org_id', 'invoice_no', 'invoice_date', 'status', 'sub_total', 'total_amount', 'paid_amount', 'balance_amount',
                ]), $export)
                    ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
                    ->when(!empty($filters['client_id']), fn ($q) => $q->where('client_id', $filters['client_id']))
                    ->orderBy('id'),
            ],
            'purchase_orders' => [
                ['id', 'po_number', 'po_date', 'status', 'sub_total', 'total_amount', 'paid_amount', 'balance_amount'],
                $this->scope(PurchaseOrder::query()->select([
                    'id', 'user_id', 'org_id', 'po_number', 'po_date', 'status', 'sub_total', 'total_amount', 'paid_amount', 'balance_amount',
                ]), $export)
                    ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
                    ->when(!empty($filters['vendor_id']), fn ($q) => $q->where('vendor_id', $filters['vendor_id']))
                    ->orderBy('id'),
            ],
            'products' => [
                ['id', 'name', 'sku', 'status', 'current_stock', 'avg_cost', 'selling_price'],
                $this->scope(Product::query()->select([
                    'id', 'user_id', 'org_id', 'name', 'sku', 'status', 'current_stock', 'avg_cost', 'selling_price',
                ]), $export)
                    ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
                    ->orderBy('id'),
            ],
            'vendors' => [
                ['id', 'vendor_name', 'company_name', 'email', 'phone', 'status', 'gstin'],
                $this->scope(Vendor::query()->select([
                    'id', 'user_id', 'org_id', 'vendor_name', 'company_name', 'email', 'phone', 'status', 'gstin',
                ]), $export)
                    ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
                    ->orderBy('id'),
            ],
            'leads' => [
                ['id', 'company_name', 'contact_person', 'email', 'phone', 'status', 'source', 'budget'],
                $this->scope(Lead::query()->select([
                    'id', 'user_id', 'org_id', 'company_name', 'contact_person', 'email', 'phone', 'status', 'source', 'budget',
                ]), $export)
                    ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
                    ->when(!empty($filters['owner_id']), fn ($q) => $q->where('owner_id', $filters['owner_id']))
                    ->orderBy('id'),
            ],
            default => throw new \InvalidArgumentException('Unsupported export type.'),
        };
    }

    private function scope($query, ExportRequest $export)
    {
        if ($export->org_id) {
            return $query->where('org_id', $export->org_id);
        }

        return $query->where('user_id', $export->user_id);
    }

    private function mapRow(string $type, object $row): array
    {
        return match ($type) {
            'invoices' => [
                $row->id,
                $row->invoice_no,
                (string) $row->invoice_date,
                $row->status,
                $row->sub_total,
                $row->total_amount,
                $row->paid_amount,
                $row->balance_amount,
            ],
            'purchase_orders' => [
                $row->id,
                $row->po_number,
                (string) $row->po_date,
                $row->status,
                $row->sub_total,
                $row->total_amount,
                $row->paid_amount,
                $row->balance_amount,
            ],
            'products' => [
                $row->id,
                $row->name,
                $row->sku,
                $row->status,
                $row->current_stock,
                $row->avg_cost,
                $row->selling_price,
            ],
            'vendors' => [
                $row->id,
                $row->vendor_name,
                $row->company_name,
                $row->email,
                $row->phone,
                $row->status,
                $row->gstin,
            ],
            'leads' => [
                $row->id,
                $row->company_name,
                $row->contact_person,
                $row->email,
                $row->phone,
                $row->status,
                $row->source,
                $row->budget,
            ],
            default => [],
        };
    }
}
