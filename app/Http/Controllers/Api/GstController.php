<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GstReturnResource;
use App\Repositories\Interfaces\GstRepositoryInterface;
use Illuminate\Http\Request;

class GstController extends Controller
{
    public function __construct(
        private GstRepositoryInterface $gstRepo
    ) {}

    // ── GET /api/gst/states ───────────────────────────────
    /**
     * Indian state codes — frontend dropdown ke liye
     */
    public function states()
    {
        $states = [
            ['code' => '01', 'name' => 'Jammu & Kashmir'],
            ['code' => '02', 'name' => 'Himachal Pradesh'],
            ['code' => '03', 'name' => 'Punjab'],
            ['code' => '04', 'name' => 'Chandigarh'],
            ['code' => '05', 'name' => 'Uttarakhand'],
            ['code' => '06', 'name' => 'Haryana'],
            ['code' => '07', 'name' => 'Delhi'],
            ['code' => '08', 'name' => 'Rajasthan'],
            ['code' => '09', 'name' => 'Uttar Pradesh'],
            ['code' => '10', 'name' => 'Bihar'],
            ['code' => '11', 'name' => 'Sikkim'],
            ['code' => '12', 'name' => 'Arunachal Pradesh'],
            ['code' => '13', 'name' => 'Nagaland'],
            ['code' => '14', 'name' => 'Manipur'],
            ['code' => '15', 'name' => 'Mizoram'],
            ['code' => '16', 'name' => 'Tripura'],
            ['code' => '17', 'name' => 'Meghalaya'],
            ['code' => '18', 'name' => 'Assam'],
            ['code' => '19', 'name' => 'West Bengal'],
            ['code' => '20', 'name' => 'Jharkhand'],
            ['code' => '21', 'name' => 'Odisha'],
            ['code' => '22', 'name' => 'Chhattisgarh'],
            ['code' => '23', 'name' => 'Madhya Pradesh'],
            ['code' => '24', 'name' => 'Gujarat'],
            ['code' => '26', 'name' => 'Dadra & Nagar Haveli and Daman & Diu'],
            ['code' => '27', 'name' => 'Maharashtra'],
            ['code' => '28', 'name' => 'Andhra Pradesh'],
            ['code' => '29', 'name' => 'Karnataka'],
            ['code' => '30', 'name' => 'Goa'],
            ['code' => '32', 'name' => 'Kerala'],
            ['code' => '33', 'name' => 'Tamil Nadu'],
            ['code' => '34', 'name' => 'Puducherry'],
            ['code' => '36', 'name' => 'Telangana'],
            ['code' => '37', 'name' => 'Andhra Pradesh (New)'],
            ['code' => '38', 'name' => 'Ladakh'],
            ['code' => '97', 'name' => 'Other Territory'],
            ['code' => '99', 'name' => 'Centre Jurisdiction'],
        ];

        return response()->json([
            'data' => $states
        ]);
    }

    // ── GET /api/gst/summary/{period} ─────────────────────
    /**
     * Period ka quick GST numbers
     * period format: 2026-02
     */
    public function summary(string $period)
    {
        $this->validatePeriod($period);

        return response()->json([
            'data' => $this->gstRepo->getSummary($period)
        ]);
    }

    // ── GET /api/gst/gstr1/{period} ───────────────────────
    /**
     * Full GSTR-1 — B2B, B2CS, B2CL, Exports, HSN Summary
     */
    public function gstr1(string $period)
    {
        $this->validatePeriod($period);

        return response()->json([
            'data' => $this->gstRepo->getGstr1($period)
        ]);
    }

    // ── GET /api/gst/gstr3b/{period} ──────────────────────
    /**
     * GSTR-3B summary
     */
    public function gstr3b(string $period)
    {
        $this->validatePeriod($period);

        return response()->json([
            'data' => $this->gstRepo->getGstr3B($period)
        ]);
    }

    // ── GET /api/gst/hsn-summary/{period} ─────────────────
    /**
     * HSN-wise tax summary
     */
    public function hsnSummary(string $period)
    {
        $this->validatePeriod($period);

        return response()->json([
            'data' => $this->gstRepo->getHsnSummary($period)
        ]);
    }

    // ── GET /api/gst/returns ──────────────────────────────
    /**
     * Returns list
     * Filters: ?return_type=GSTR1 &period=2026-02 &status=filed
     */
    public function index(Request $request)
    {
        $filters  = $request->only(['return_type', 'period', 'status']);
        $returns  = $this->gstRepo->getReturns($filters);

        return GstReturnResource::collection($returns);
    }

    // ── POST /api/gst/returns/draft ───────────────────────
    /**
     * Return ko draft mein save karo (ya update existing draft)
     * Body: { "return_type": "GSTR1", "period": "2026-02" }
     */
    public function saveDraft(Request $request)
    {
        $request->validate([
            'return_type' => 'required|in:GSTR1,GSTR3B',
            'period'      => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $return = $this->gstRepo->saveDraft(
            $request->return_type,
            $request->period
        );

        return response()->json([
            'message' => 'Draft saved successfully',
            'data'    => new GstReturnResource($return)
        ]);
    }

    // ── POST /api/gst/returns/{id}/file ───────────────────
    /**
     * Return ko filed mark karo
     */
    public function fileReturn(int $id)
    {
        $return = $this->gstRepo->markFiled($id);

        return response()->json([
            'message' => 'Return marked as filed successfully',
            'data'    => new GstReturnResource($return)
        ]);
    }

    // ── Private Helper ─────────────────────────────────────

    private function validatePeriod(string $period): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            abort(422, 'Invalid period format. Use YYYY-MM e.g. 2026-02');
        }

        $month = (int) substr($period, 5, 2);

        if ($month < 1 || $month > 12) {
            abort(422, 'Invalid month. Must be 01 to 12');
        }
    }
}