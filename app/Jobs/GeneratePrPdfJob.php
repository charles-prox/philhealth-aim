<?php

namespace App\Jobs;

use App\Models\ProcurementFolder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

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
    public function handle(): void
    {
        Log::info("Job GeneratePrPdfJob started for PR: {$this->folder->pr_number}");
        try {
            $storagePath = "pr/{$this->folder->pr_number}.pdf";
            $disk = Storage::disk('public');
            
            if (!$disk->exists('pr')) {
                $disk->makeDirectory('pr');
            }
            
            Pdf::view('pdf.pr-form', ['folder' => $this->folder])
                ->save($disk->path($storagePath));
                
            Log::info("Successfully generated PDF: {$storagePath}");
        } catch (\Exception $e) {
            Log::error("Failed to generate PR PDF for {$this->folder->pr_number}: " . $e->getMessage());
            throw $e;
        }
    }
}
