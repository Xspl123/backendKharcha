<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;

class GstEInvoiceService
{
    /**
     * Generate NIC E-Way Bill JSON Payload (Version 1.0.02)
     * Compatible with ewaybillgst.gov.in bulk/single upload
     */
    public function generateEWayBillPayload(Invoice $invoice, array $transDetails = []): array
    {
        $invoice->loadMissing(['company', 'client', 'items']);

        $company = $invoice->company;
        $client  = $invoice->client;

        $fromGstin     = strtoupper(trim($company->gstin ?? ''));
        $fromStateCode = !empty($fromGstin) && strlen($fromGstin) >= 2 && is_numeric(substr($fromGstin, 0, 2))
            ? (int) substr($fromGstin, 0, 2)
            : 9;

        $toGstin     = strtoupper(trim($client->gstin ?? ''));
        $toStateCode = !empty($toGstin) && strlen($toGstin) >= 2 && is_numeric(substr($toGstin, 0, 2))
            ? (int) substr($toGstin, 0, 2)
            : (int) ($invoice->place_of_supply ?? $fromStateCode);

        $itemList = [];
        $itemIndex = 1;
        foreach ($invoice->items as $item) {
            $hsnClean = (int) preg_replace('/\D/', '', $item->hsn_code ?? '9983');
            $taxRate  = (float) ($item->tax_rate ?? 18);
            $isIntra  = ($fromStateCode === $toStateCode) || ($invoice->supply_type === 'intra');

            $cgstRate = $isIntra ? round($taxRate / 2, 2) : 0.0;
            $sgstRate = $isIntra ? round($taxRate / 2, 2) : 0.0;
            $igstRate = !$isIntra ? round($taxRate, 2) : 0.0;

            $taxableAmt = (float) ($item->amount ?? ($item->qty * $item->rate));

            $itemList[] = [
                'itemNo'        => $itemIndex++,
                'productName'   => substr($item->item_name ?? 'Goods / Service', 0, 100),
                'productDesc'   => substr($item->description ?: ($item->item_name ?? ''), 0, 100),
                'hsnCode'       => $hsnClean > 0 ? $hsnClean : 9983,
                'quantity'      => (float) ($item->qty ?? 1),
                'qtyUnit'       => strtoupper(trim($item->unit ?? 'NOS')) ?: 'NOS',
                'cgstRate'      => $cgstRate,
                'sgstRate'      => $sgstRate,
                'igstRate'      => $igstRate,
                'cessRate'      => 0.0,
                'taxableAmount' => round($taxableAmt, 2),
            ];
        }

        $transMode       = $transDetails['trans_mode'] ?? $invoice->trans_mode ?? '1';
        $transporterId   = strtoupper(trim($transDetails['transporter_id'] ?? $invoice->transporter_id ?? ''));
        $transporterName = trim($transDetails['transporter_name'] ?? $invoice->transporter_name ?? '');
        $transDocNo      = trim($transDetails['trans_doc_no'] ?? $invoice->trans_doc_no ?? '');
        $transDocDate    = !empty($transDetails['trans_doc_date'] ?? $invoice->trans_doc_date)
            ? Carbon::parse($transDetails['trans_doc_date'] ?? $invoice->trans_doc_date)->format('d/m/Y')
            : Carbon::parse($invoice->invoice_date)->format('d/m/Y');
        $vehicleNo       = strtoupper(str_replace(' ', '', $transDetails['vehicle_no'] ?? $invoice->vehicle_no ?? ''));
        $vehicleType     = strtoupper($transDetails['vehicle_type'] ?? $invoice->vehicle_type ?? 'R');
        $distanceKm      = (int) ($transDetails['distance_km'] ?? $invoice->distance_km ?? 50);

        return [
            'version'            => '1.0.02',
            'userGstin'          => $fromGstin ?: 'URP',
            'supplyType'         => 'O',
            'subSupplyType'      => '1',
            'subSupplyDesc'      => '',
            'docType'            => $invoice->is_return ? 'CRN' : 'INV',
            'docNo'              => $invoice->invoice_no,
            'docDate'            => Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
            'fromGstin'          => $fromGstin ?: 'URP',
            'fromTrdName'        => $company->company_name ?? 'Merchant',
            'fromAddr1'          => substr($company->address ?? 'Main Office', 0, 100),
            'fromAddr2'          => '',
            'fromPlace'          => $company->city ?? 'Ghaziabad',
            'fromPincode'        => (int) ($company->pincode ?? 201001),
            'actFromStateCode'   => $fromStateCode,
            'fromStateCode'      => $fromStateCode,
            'toGstin'            => $toGstin ?: 'URP',
            'toTrdName'          => $client->company_name ?? 'Customer',
            'toAddr1'            => substr($client->address ?? 'Client Address', 0, 100),
            'toAddr2'            => '',
            'toPlace'            => $client->city ?? 'Delhi',
            'toPincode'          => (int) ($client->pincode ?? 110001),
            'actToStateCode'     => $toStateCode,
            'toStateCode'        => $toStateCode,
            'totalValue'         => round((float) $invoice->sub_total, 2),
            'cgstValue'          => round((float) $invoice->cgst, 2),
            'sgstValue'          => round((float) $invoice->sgst, 2),
            'igstValue'          => round((float) $invoice->igst, 2),
            'cessValue'          => 0.0,
            'totInvValue'        => round((float) $invoice->total_amount, 2),
            'transporterId'      => $transporterId,
            'transporterName'    => $transporterName,
            'transDocNo'         => $transDocNo,
            'transDocDate'       => $transDocDate,
            'transMode'          => (string) $transMode,
            'vehicleNo'          => $vehicleNo,
            'vehicleType'        => $vehicleType,
            'distance'           => $distanceKm > 0 ? $distanceKm : 50,
            'itemList'           => $itemList,
        ];
    }

    /**
     * Generate NIC E-Invoice JSON Payload (Schema Version 1.03)
     */
    public function generateEInvoicePayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['company', 'client', 'items']);

        $company = $invoice->company;
        $client  = $invoice->client;

        $fromGstin = strtoupper(trim($company->gstin ?? '09AAACK1234M1Z5'));
        $toGstin   = strtoupper(trim($client->gstin ?? 'URP'));

        $fromState = strlen($fromGstin) >= 2 && is_numeric(substr($fromGstin, 0, 2)) ? substr($fromGstin, 0, 2) : '09';
        $toState   = strlen($toGstin) >= 2 && is_numeric(substr($toGstin, 0, 2)) ? substr($toGstin, 0, 2) : ($invoice->place_of_supply ?? $fromState);

        $itemList = [];
        $idx = 1;
        foreach ($invoice->items as $item) {
            $taxRate   = (float) ($item->tax_rate ?? 18);
            $isIntra   = ($fromState === $toState) || ($invoice->supply_type === 'intra');
            $taxable   = round((float) ($item->amount ?? ($item->qty * $item->rate)), 2);

            $cgstVal   = $isIntra ? round(($taxable * ($taxRate / 2)) / 100, 2) : 0.0;
            $sgstVal   = $isIntra ? round(($taxable * ($taxRate / 2)) / 100, 2) : 0.0;
            $igstVal   = !$isIntra ? round(($taxable * $taxRate) / 100, 2) : 0.0;

            $itemList[] = [
                'SlNo'        => (string) $idx++,
                'PrdDesc'     => substr($item->item_name ?? 'Goods', 0, 100),
                'IsServc'     => (str_starts_with($item->hsn_code ?? '', '99') || ($item->unit ?? '') === 'SVC') ? 'Y' : 'N',
                'HsnCd'       => (string) (preg_replace('/\D/', '', $item->hsn_code ?? '') ?: '998311'),
                'Qty'         => (float) ($item->qty ?? 1),
                'Unit'        => strtoupper(trim($item->unit ?? 'NOS')) ?: 'NOS',
                'UnitPrice'   => round((float) ($item->rate ?? 0), 2),
                'TotAmt'      => round((float) ($item->qty * $item->rate), 2),
                'Discount'    => 0.0,
                'PreTaxVal'   => $taxable,
                'AssAmt'      => $taxable,
                'GstRt'       => $taxRate,
                'IgstAmt'     => $igstVal,
                'CgstAmt'     => $cgstVal,
                'SgstAmt'     => $sgstVal,
                'CesRt'       => 0.0,
                'CesAmt'      => 0.0,
                'TotItemVal'  => round($taxable + $cgstVal + $sgstVal + $igstVal, 2),
            ];
        }

        return [
            'Version'  => '1.1',
            'TranDtls' => [
                'TaxSch'      => 'GST',
                'SupTyp'      => strtoupper($invoice->invoice_type ?: 'B2B'),
                'RegRev'      => $invoice->is_reverse_charge ? 'Y' : 'N',
                'EcmGstin'    => null,
                'IgstOnIntra' => 'N',
            ],
            'DocDtls' => [
                'Typ' => $invoice->is_return ? 'CRN' : 'INV',
                'No'  => $invoice->invoice_no,
                'Dt'  => Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
            ],
            'SellerDtls' => [
                'Gstin' => $fromGstin,
                'LglNm' => $company->company_name ?? 'Merchant',
                'TrdNm' => $company->company_name ?? 'Merchant',
                'Pos'   => $toState,
                'Addr1' => substr($company->address ?? 'Office Address', 0, 100),
                'Loc'   => $company->city ?? 'City',
                'Pin'   => (int) ($company->pincode ?? 201001),
                'Stcd'  => $fromState,
                'Ph'    => $company->phone,
                'Em'    => $company->email,
            ],
            'BuyerDtls' => [
                'Gstin' => $toGstin,
                'LglNm' => $client->company_name ?? 'Customer',
                'TrdNm' => $client->company_name ?? 'Customer',
                'Pos'   => $toState,
                'Addr1' => substr($client->address ?? 'Client Address', 0, 100),
                'Loc'   => $client->city ?? 'City',
                'Pin'   => (int) ($client->pincode ?? 110001),
                'Stcd'  => $toState,
                'Ph'    => $client->phone,
                'Em'    => $client->email,
            ],
            'ValDtls' => [
                'AssVal'    => round((float) $invoice->sub_total, 2),
                'CgstVal'   => round((float) $invoice->cgst, 2),
                'SgstVal'   => round((float) $invoice->sgst, 2),
                'IgstVal'   => round((float) $invoice->igst, 2),
                'CesVal'    => 0.0,
                'StCesVal'  => 0.0,
                'Discount'  => 0.0,
                'OthChrg'   => 0.0,
                'RndOffAmt' => 0.0,
                'TotInvVal' => round((float) $invoice->total_amount, 2),
            ],
            'ItemList' => $itemList,
        ];
    }

    /**
     * Canonical 64-character SHA-256 IRN hash according to GST rules
     */
    public function generateIrn(Invoice $invoice): array
    {
        $invoice->loadMissing(['company', 'client']);

        $sellerGstin = strtoupper(trim($invoice->company->gstin ?? '09AAACK1234M1Z5'));
        $docType     = $invoice->is_return ? 'CRN' : 'INV';
        $docNo       = $invoice->invoice_no;

        $invoiceDate = Carbon::parse($invoice->invoice_date);
        $year        = $invoiceDate->year;
        $month       = $invoiceDate->month;
        $fyStart     = $month >= 4 ? $year : $year - 1;
        $fyEndShort  = substr((string) ($fyStart + 1), -2);
        $fy          = "{$fyStart}-{$fyEndShort}";

        $canonical = "{$sellerGstin}|{$fy}|{$docType}|{$docNo}";
        $irn       = hash('sha256', $canonical);

        $ackNo   = '11' . $invoiceDate->format('ymd') . str_pad((string) $invoice->id, 7, '0', STR_PAD_LEFT);
        $ackDate = now();

        $qrData = implode('|', [
            $sellerGstin,
            $invoice->client->gstin ?? 'URP',
            $docNo,
            $invoiceDate->format('d/m/Y'),
            number_format((float) $invoice->total_amount, 2, '.', ''),
            count($invoice->items),
            $invoice->items->first()->hsn_code ?? '998311',
            $irn,
            $ackNo,
            $ackDate->format('Y-m-d H:i:s'),
        ]);

        $invoice->update([
            'irn'             => $irn,
            'ack_no'          => $ackNo,
            'ack_date'        => $ackDate,
            'signed_qr_data'  => $qrData,
            'einvoice_status' => 'generated',
        ]);

        return [
            'irn'             => $irn,
            'ack_no'          => $ackNo,
            'ack_date'        => $ackDate->format('Y-m-d H:i:s'),
            'signed_qr_data'  => $qrData,
            'einvoice_status' => 'generated',
        ];
    }

    public function cancelIrn(Invoice $invoice, string $reason = '1'): bool
    {
        $invoice->update([
            'einvoice_status' => 'cancelled',
        ]);
        return true;
    }

    public function saveTransportationDetails(Invoice $invoice, array $data): Invoice
    {
        $fillData = [
            'transporter_id'   => $data['transporter_id'] ?? $invoice->transporter_id,
            'transporter_name' => $data['transporter_name'] ?? $invoice->transporter_name,
            'trans_doc_no'     => $data['trans_doc_no'] ?? $invoice->trans_doc_no,
            'trans_doc_date'   => !empty($data['trans_doc_date']) ? Carbon::parse($data['trans_doc_date'])->format('Y-m-d') : $invoice->trans_doc_date,
            'trans_mode'       => $data['trans_mode'] ?? $invoice->trans_mode ?? '1',
            'vehicle_no'       => !empty($data['vehicle_no']) ? strtoupper(str_replace(' ', '', $data['vehicle_no'])) : $invoice->vehicle_no,
            'vehicle_type'     => $data['vehicle_type'] ?? $invoice->vehicle_type ?? 'R',
            'distance_km'      => isset($data['distance_km']) ? (int) $data['distance_km'] : $invoice->distance_km,
        ];

        if (!empty($data['eway_bill_no'])) {
            $fillData['eway_bill_no']   = $data['eway_bill_no'];
            $fillData['eway_bill_date'] = !empty($data['eway_bill_date']) ? Carbon::parse($data['eway_bill_date']) : now();
            $fillData['eway_status']    = 'active';

            $dist = (int) ($data['distance_km'] ?? $invoice->distance_km ?? 50);
            $validDays = max(1, (int) ceil($dist / 200));
            $fillData['eway_valid_upto'] = now()->addDays($validDays);
        }

        $invoice->update($fillData);
        return $invoice->fresh();
    }
}