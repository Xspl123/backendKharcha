<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateExportJob;
use App\Models\ExportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:invoices,purchase_orders,products,vendors,leads',
            'filters' => 'nullable|array',
        ]);

        $export = ExportRequest::create([
            'user_id' => $request->user()->id,
            'org_id' => $request->user()->org_id,
            'type' => $data['type'],
            'status' => 'queued',
            'filters' => $data['filters'] ?? [],
            'file_disk' => 'local',
        ]);

        GenerateExportJob::dispatch($export->id);

        return response()->json([
            'message' => 'Export queued successfully.',
            'data' => $this->transform($export),
        ], 202);
    }

    public function show(Request $request, int $id)
    {
        $export = $this->findScoped($request, $id);

        return response()->json([
            'data' => $this->transform($export),
        ]);
    }

    public function download(Request $request, int $id)
    {
        $export = $this->findScoped($request, $id);

        abort_unless($export->isCompleted() && $export->file_path, 422, 'Export file is not ready yet.');

        return Storage::disk($export->file_disk)->download(
            $export->file_path,
            "{$export->type}-export-{$export->id}.csv"
        );
    }

    private function findScoped(Request $request, int $id): ExportRequest
    {
        $query = ExportRequest::query()->where('id', $id);

        if ($request->user()->hasOrg()) {
            return $query->where('org_id', $request->user()->org_id)->firstOrFail();
        }

        return $query->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function transform(ExportRequest $export): array
    {
        return [
            'id' => $export->id,
            'type' => $export->type,
            'status' => $export->status,
            'filters' => $export->filters ?? [],
            'error_message' => $export->error_message,
            'finished_at' => $export->finished_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'download_available' => $export->isCompleted() && !empty($export->file_path),
        ];
    }
}
