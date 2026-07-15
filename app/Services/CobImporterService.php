<?php

namespace App\Services;

use App\Models\CobItem;
use App\Models\CobVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class CobImporterService
{
    /**
     * Parse and import the WFP Excel file into the specified COB Version.
     */
    public function import(string $filePath, string $versionId): int
    {
        $version = CobVersion::findOrFail($versionId);
        $totalImported = 0;

        // Update status to processing (optional if we had a status field, but currently we just use is_active)
        // For our current schema, it's inactive until activated.

        $itemsToInsert = [];
        $chunkSize = 500;

        try {
            DB::transaction(function () use ($filePath, $versionId, &$itemsToInsert, $chunkSize, &$totalImported) {
                // Delete any existing items for this version if re-uploading
                CobItem::where('version_id', $versionId)->delete();

                (new FastExcel)->import($filePath, function ($line) use (&$itemsToInsert, $versionId, $chunkSize, &$totalImported) {
                    // Log first row headers for debugging if totalImported is 0
                    if ($totalImported === 0) {
                        Log::info('COB Import Headers detected: ' . implode(', ', array_keys($line)));
                    }

                    // Extract values with fallback for common column naming variations
                    $ppaCode = $this->extractValue($line, ['PPACode', 'PPA Code', 'ppa_code']);
                    $ppaDesc = $this->extractValue($line, ['PPADesc', 'PPA Description', 'ppa_desc']);

                    // Skip empty rows
                    if (empty($ppaCode) && empty($ppaDesc)) {
                        return;
                    }

                    $recomAmount = $this->parseAmount($this->extractValue($line, ['RecomAmount', 'Recom Amount', 'Amount']));

                    // Determine if ICT from description or explicit flag
                    $isIctFlag = $this->extractValue($line, ['IsICT', 'Is ICT', 'ICT']);
                    $isIct = false;
                    if (!empty($isIctFlag) && in_array(strtolower((string) $isIctFlag), ['yes', '1', 'true', 'y'])) {
                        $isIct = true;
                    } elseif (Str::contains(strtolower($ppaDesc ?? ''), ['ict', 'computer', 'software', 'hardware', 'information technology'])) {
                        $isIct = true;
                    }

                    $itemsToInsert[] = [
                        'id' => (string) Str::uuid(),
                        'version_id' => $versionId,

                        // Financials
                        'recom_amount' => $recomAmount,
                        'encumbered_amount' => 0,
                        'actual_spent' => 0,
                        'current_balance' => $recomAmount, // Initially balance is the recommended amount

                        // Classification
                        'ppa_code' => $ppaCode,
                        'ppa_desc' => $ppaDesc,
                        'sub_ppa_code' => $this->extractValue($line, ['SubPPACode', 'Sub PPA Code']),
                        'sub_ppa_desc' => $this->extractValue($line, ['SubPPADesc', 'Sub PPA Description']),
                        'exp_desc' => $this->extractValue($line, ['ExpDesc', 'Expense Description', 'Expense Class']),
                        'is_ict' => $isIct,
                        'base' => $this->extractValue($line, ['Base']),
                        'account' => $this->extractValue($line, ['Account', 'Account Code']),
                        'tier' => $this->extractValue($line, ['Tier']),
                        'class' => $this->extractValue($line, ['CLASS', 'Class']),
                        'gass' => $this->extractValue($line, ['GASS']),

                        // Identifiers
                        'transaction_id' => $this->extractValue($line, ['TransactionID', 'Transaction ID']),
                        'work_and_financial_plan_id' => $this->extractValue($line, ['WorkAndFinancialPlanID', 'WFP ID']),
                        'office_id' => $this->extractValue($line, ['OfficeID', 'Office']),
                        'sector' => $this->extractValue($line, ['SECTOR', 'Sector']),

                        // Particulars
                        'full_particulars' => $this->extractValue($line, ['FullParticulars', 'Full Particulars', 'Particulars']),
                        'particulars1' => $this->extractValue($line, ['Particular1', 'Particular 1']),
                        'particulars2' => $this->extractValue($line, ['Particular2', 'Particular 2']),
                        'unit' => $this->extractValue($line, ['Unit', 'Unit of Measure']),
                        'recom_qty' => $this->parseAmount($this->extractValue($line, ['RecomQty', 'Recom Qty', 'Quantity'])),

                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($itemsToInsert) >= $chunkSize) {
                        CobItem::insert($itemsToInsert);
                        $totalImported += count($itemsToInsert);
                        $itemsToInsert = [];
                    }
                });

                // Insert remaining rows
                if (count($itemsToInsert) > 0) {
                    CobItem::insert($itemsToInsert);
                    $totalImported += count($itemsToInsert);
                }
            });

            return $totalImported;

        } catch (\Exception $e) {
            Log::error('COB Import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper to reliably extract values despite case/spacing variations in headers.
     */
    private function extractValue(array $row, array $possibleKeys): ?string
    {
        // Normalize row keys for easier lookup (lowercase, trimmed)
        $normalizedRow = [];
        foreach ($row as $key => $value) {
            $normalizedRow[trim(strtolower((string) $key))] = $value;
        }

        foreach ($possibleKeys as $key) {
            $normKey = trim(strtolower($key));
            if (isset($normalizedRow[$normKey])) {
                return (string) $normalizedRow[$normKey];
            }
        }

        return null;
    }

    private function parseAmount(?string $val): float
    {
        if (empty($val)) {
            return 0.0;
        }
        // Remove commas and PHP currency symbols if any
        $val = preg_replace('/[^\d\.\-]/', '', $val);

        return (float) $val;
    }
}
