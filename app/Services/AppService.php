<?php

namespace App\Services;

use App\Models\AppHeader;
use App\Models\AppLineItem;
use App\Models\Employee;
use App\Models\PrItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AppService
{
    /**
     * Ingest and parse Annual Procurement Plan CSV data.
     */
    public function ingestCsv(int $fiscalYear, $csvFile, ?Employee $actor): void
    {
        DB::transaction(function () use ($fiscalYear, $csvFile, $actor) {
            $header = AppHeader::updateOrCreate(
                ['fiscal_year' => $fiscalYear],
                [
                    'is_approved' => false,
                    'approved_at' => null,
                    'uploaded_by_id' => $actor?->id,
                ]
            );

            // Wipe previous unapproved/approved items for this year to prevent row stacking
            AppLineItem::where('app_header_id', $header->id)->delete();

            // Ingest CSV Rows
            $path = $csvFile->getRealPath();
            if (($handle = fopen($path, 'r')) !== false) {
                // Skip header row
                fgetcsv($handle, 2000, ',');

                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    if (count($data) < 10) {
                        continue;
                    }

                    // Extract and clean values
                    $projectTitle = trim($data[0] ?? '');
                    $implementingUnit = trim($data[1] ?? '');
                    $description = trim($data[2] ?? '');
                    $procurementMode = trim($data[3] ?? '');
                    $epaRaw = strtolower(trim($data[4] ?? ''));
                    $isEpa = $epaRaw === 'yes' || $epaRaw === '1' || $epaRaw === 'y' || $epaRaw === 'true';
                    $evaluationCriteria = trim($data[5] ?? '');
                    $activityStart = trim($data[6] ?? '');
                    $activityEnd = trim($data[7] ?? '');
                    $sourceOfFund = trim($data[8] ?? '');

                    $approvedBudgetRaw = str_replace([',', '₱', 'Php', 'PHP', ' ', '$'], '', $data[9] ?? '0');
                    $approvedBudget = (float) $approvedBudgetRaw;

                    $strategyTools = trim($data[10] ?? '');
                    $remarks = trim($data[11] ?? '');

                    AppLineItem::create([
                        'app_header_id' => $header->id,
                        'project_title' => $projectTitle,
                        'implementing_unit' => $implementingUnit,
                        'description' => $description,
                        'procurement_mode' => $procurementMode,
                        'is_epa' => $isEpa,
                        'evaluation_criteria' => $evaluationCriteria ?: null,
                        'activity_start' => $activityStart,
                        'activity_end' => $activityEnd,
                        'source_of_fund' => $sourceOfFund,
                        'approved_budget' => $approvedBudget,
                        'strategy_tools' => $strategyTools ?: null,
                        'remarks' => $remarks ?: null,
                        'utilized_budget' => 0.00,
                    ]);
                }
                fclose($handle);
            }

            // Store CSV file securely outside the public root
            $csvFilename = "app_{$fiscalYear}_" . time() . '.' . $csvFile->getClientOriginalExtension();
            $csvPathDb = $csvFile->storeAs('secure/app_csvs', $csvFilename, 'local');

            $header->update(['csv_file_path' => $csvPathDb]);
        });
    }

    /**
     * Attach legal PDF verification and unlock APP.
     */
    public function finalizeWithPdf(int $fiscalYear, $pdfFile): void
    {
        $appHeader = AppHeader::where('fiscal_year', $fiscalYear)->first();
        $lineItemsCount = $appHeader ? $appHeader->lineItems()->count() : 0;

        if (!$appHeader || $lineItemsCount === 0) {
            throw new \RuntimeException('Process Blocked: You must upload and parse the master APP CSV file before uploading the signed PDF scan.');
        }

        // Securely store the file outside the public web root directory
        $pdfFilename = "APP_{$fiscalYear}_" . time() . '.pdf';
        $pdfPath = $pdfFile->storeAs('secure/app_scans', $pdfFilename, 'local');

        // Hard-lock the approval states
        $appHeader->update([
            'scanned_pdf_path' => $pdfPath,
            'is_approved' => true,
            'approved_at' => now(),
        ]);
    }

    /**
     * Revoke APP approval state.
     */
    public function revokeApp(int $fiscalYear): void
    {
        $header = AppHeader::where('fiscal_year', $fiscalYear)->first();
        if (!$header) {
            return;
        }

        // Check if there are PRs using this APP
        $hasPrs = PrItem::whereIn('app_line_item_id', $header->lineItems()->pluck('id'))->exists();
        if ($hasPrs) {
            throw new \RuntimeException('Cannot revoke this APP. Some Purchase Requests have already linked to its line items.');
        }

        $header->update([
            'is_approved' => false,
            'approved_at' => null,
        ]);
    }

    /**
     * Delete entire APP and rollback files.
     */
    public function deleteApp(int $fiscalYear): void
    {
        $header = AppHeader::where('fiscal_year', $fiscalYear)->first();
        if (!$header) {
            return;
        }

        // Check if there are PRs using this APP
        $hasPrs = PrItem::whereIn('app_line_item_id', $header->lineItems()->pluck('id'))->exists();
        if ($hasPrs) {
            throw new \RuntimeException('Cannot delete this APP. Some Purchase Requests have already linked to its line items.');
        }

        DB::transaction(function () use ($header) {
            if ($header->scanned_pdf_path) {
                Storage::disk('local')->delete($header->scanned_pdf_path);
            }
            if ($header->csv_file_path) {
                Storage::disk('local')->delete($header->csv_file_path);
            }
            $header->delete();
        });
    }
}
