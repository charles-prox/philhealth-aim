<?php

namespace App\Jobs;

use App\Models\ProcurementFolder;
use App\Services\GoogleIntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeneratePrPdfJob implements ShouldQueue
{
    use Queueable;

    public $folder;

    /**
     * Create a new job instance.
     */
    public function __construct(ProcurementFolder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleIntegrationService $googleService): void
    {
        Log::info("Job GeneratePrPdfJob started for PR: {$this->folder->pr_number}");
        try {
            $googleService->generatePrPdf($this->folder);
        } catch (\Exception $e) {
            Log::error("Failed to generate PR PDF for {$this->folder->pr_number}: " . $e->getMessage());
            throw $e;
        }
    }
}
