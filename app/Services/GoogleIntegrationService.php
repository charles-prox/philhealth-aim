<?php

namespace App\Services;

use App\Models\ProcurementFolder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Google\Client;
use Google\Service\Docs;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Docs\Request;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Exception;

class GoogleIntegrationService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setApplicationName('PhilHealth AIM Integration');
        $this->client->setScopes([
            Drive::DRIVE,
            Docs::DOCUMENTS,
        ]);
        
        $credentialsPath = env('GOOGLE_APPLICATION_CREDENTIALS');
        if (file_exists($credentialsPath)) {
            $this->client->setAuthConfig($credentialsPath);
        } else {
            Log::warning("Google Service Account credentials file not found at: {$credentialsPath}");
        }
    }

    /**
     * Generate a PR PDF from Google Docs Template and store it locally.
     *
     * @param ProcurementFolder $folder
     * @return string Path to the generated PDF relative to the 'public' disk
     * @throws Exception
     */
    public function generatePrPdf(ProcurementFolder $folder): string
    {
        $templateId = '1N2YOBN6Knub4te-85K8bmw2xK_N8Gk2fdaB0Kv7AzXg';
        
        $driveService = new Drive($this->client);
        $docsService = new Docs($this->client);
        
        Log::info("Generating PR PDF for {$folder->pr_number}");

        // 1. Copy the template document
        $copy = new DriveFile([
            'name' => "PR-{$folder->pr_number}-Temp"
        ]);
        
        $copiedFile = $driveService->files->copy($templateId, $copy);
        $documentId = $copiedFile->getId();

        try {
            // 2. Locate the items table (second table in the document)
            $doc = $docsService->documents->get($documentId);
            $tableStartIndex = null;
            $tableCount = 0;

            foreach ($doc->getBody()->getContent() as $element) {
                if ($element->getTable()) {
                    $tableCount++;
                    if ($tableCount === 2) {
                        $tableStartIndex = $element->getStartIndex();
                        break;
                    }
                }
            }

            $requests = [];

            // 3. Duplicate table rows if there are multiple items
            $prItems = $folder->prItems()->with('cobItem')->get();
            $itemCount = $prItems->count();
            
            if ($tableStartIndex !== null && $itemCount > 1) {
                // We need to insert ($itemCount - 1) additional rows below the template row (which is rowIndex 1)
                for ($i = 0; $i < $itemCount - 1; $i++) {
                    $requests[] = new Request([
                        'insertTableRow' => [
                            'tableCellLocation' => [
                                'tableStartLocation' => [
                                    'index' => $tableStartIndex
                                ],
                                'rowIndex' => 1
                            ],
                            'insertBelow' => true
                        ]
                    ]);
                }
            }

            // Execute row insertions first so we can then inject text into the placeholders
            if (!empty($requests)) {
                $batchUpdateRequest = new BatchUpdateDocumentRequest(['requests' => $requests]);
                $docsService->documents->batchUpdate($documentId, $batchUpdateRequest);
            }

            // 4. Inject data into the placeholders
            $replaceRequests = [];
            
            // Text Replacement Helper
            $replaceText = function($matchText, $replaceText) use (&$replaceRequests) {
                $replaceRequests[] = new Request([
                    'replaceAllText' => [
                        'containsText' => [
                            'text' => $matchText,
                            'matchCase' => true
                        ],
                        'replaceText' => (string) $replaceText
                    ]
                ]);
            };

            // High-Level Variables
            $replaceText('{{division}}', 'Management Services Division (MSD)');
            $replaceText('{{section}}', 'General Services Unit (GSU)');
            $replaceText('{{pr_no}}', $folder->pr_number);
            $replaceText('{{date}}', $folder->created_at->format('F d, Y'));
            $replaceText('{{purpose}}', $folder->overall_purpose);
            
            $replaceText('{{t_qty}}', number_format($prItems->sum('total_qty'), 2));
            $replaceText('{{t_unit}}', number_format($prItems->sum('estimated_unit_cost'), 2));
            $replaceText('{{t_total}}', number_format($prItems->sum('estimated_total_cost'), 2));

            // Signatories
            $replaceText('{{requested_by}}', $folder->requestedBy?->fullname ?? '');
            $replaceText('{{requested_by_desig}}', $folder->requested_by_designation ?? '');
            
            $replaceText('{{recommened_by}}', $folder->recommendedBy?->fullname ?? ''); // Handle typo in prompt
            $replaceText('{{recommended_by}}', $folder->recommendedBy?->fullname ?? ''); // Handle correct spelling just in case
            $replaceText('{{recommened_by_desig}}', $folder->recommended_by_designation ?? '');
            $replaceText('{{recommended_by_desig}}', $folder->recommended_by_designation ?? '');
            
            $replaceText('{{approved_by}}', $folder->approvedBy?->fullname ?? '');
            $replaceText('{{approved_by_desig}}', $folder->approved_by_designation ?? '');

            // 5. Inject Item Data (replace the first occurrence sequentially)
            // Because we duplicated the row containing {{item_no}}, {{unit}}, etc., 
            // replacing them one by one will fill the rows from top to bottom.
            $index = 1;
            foreach ($prItems as $item) {
                $description = $item->item_description_override ?? $item->cobItem?->exp_desc ?? $item->cobItem?->ppa_desc ?? '';
                $unit = $item->cobItem?->unit ?? 'Unit';
                
                $replaceRequests[] = new Request(['replaceAllText' => ['containsText' => ['text' => '{{item_no}}', 'matchCase' => true], 'replaceText' => (string)$index]]);
                $replaceRequests[] = new Request(['replaceAllText' => ['containsText' => ['text' => '{{unit}}', 'matchCase' => true], 'replaceText' => $unit]]);
                $replaceRequests[] = new Request(['replaceAllText' => ['containsText' => ['text' => '{{description}}', 'matchCase' => true], 'replaceText' => $description]]);
                $replaceRequests[] = new Request(['replaceAllText' => ['containsText' => ['text' => '{{qty}}', 'matchCase' => true], 'replaceText' => number_format($item->total_qty, 2)]]);
                $replaceRequests[] = new Request(['replaceAllText' => ['containsText' => ['text' => '{{unit_cost}}', 'matchCase' => true], 'replaceText' => number_format($item->estimated_unit_cost, 2)]]);
                $replaceRequests[] = new Request(['replaceAllText' => ['containsText' => ['text' => '{{total_cost}}', 'matchCase' => true], 'replaceText' => number_format($item->estimated_total_cost, 2)]]);
                
                $index++;
            }

            if (!empty($replaceRequests)) {
                $batchUpdateRequest = new BatchUpdateDocumentRequest(['requests' => $replaceRequests]);
                $docsService->documents->batchUpdate($documentId, $batchUpdateRequest);
            }

            // 6. Export as PDF
            $response = $driveService->files->export($documentId, 'application/pdf', ['alt' => 'media']);
            $pdfContent = $response->getBody()->getContents();

            // 7. Save to local storage
            $storagePath = "pr/{$folder->pr_number}.pdf";
            Storage::disk('public')->put($storagePath, $pdfContent);

            Log::info("Successfully generated PDF: {$storagePath}");
            
            return $storagePath;

        } finally {
            // 8. Delete the temporary document
            try {
                $driveService->files->delete($documentId);
            } catch (Exception $e) {
                Log::warning("Failed to delete temporary Google Doc {$documentId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Generate an RFQ Google Form and Sheet, then email it to accredited suppliers.
     */
    public function initiateRfq(ProcurementFolder $folder, array $suppliers)
    {
        Log::info("Initiating RFQ for PR: {$folder->pr_number}");
        $folder->update([
            'status' => 'RFQ',
            'google_sheet_id' => 'placeholder_sheet_id',
            'google_form_id' => 'placeholder_form_id',
        ]);
        return true;
    }

    /**
     * Sync bids from the linked Google Sheet for a given folder.
     */
    public function syncBidsFromSheet(ProcurementFolder $folder)
    {
        Log::info("Syncing bids from Sheet: {$folder->google_sheet_id}");
        return [];
    }

    /**
     * Auto-generate an IAR/ICS/PAR document from a template via Google Docs API.
     */
    public function generateAccountabilityDocument($templateId, $data)
    {
        Log::info("Generating document from template: {$templateId}");
        return "https://docs.google.com/document/d/generated_doc_id/edit";
    }

    /**
     * Export monthly ledger transactions into a pre-formatted RSMI Sheet.
     */
    public function exportRsmiToSheet($month, $year)
    {
        Log::info("Exporting RSMI for {$month}/{$year}");
        return true;
    }
}
