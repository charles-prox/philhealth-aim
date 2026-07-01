<?php

namespace App\Jobs;

use App\Models\ProcurementFolder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateProcurementDocumentsJob implements ShouldQueue
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
        $folderName = preg_replace('/[^A-Za-z0-9\-]/', '_', $this->folder->tracking_number);

        // 1. Physically provision the dual-channel subdirectories on the secure private disk
        Storage::disk('secure_procurement')->makeDirectory("{$folderName}/generated");
        Storage::disk('secure_procurement')->makeDirectory("{$folderName}/uploaded");

        // 2. Dispatch data to the PDF Compiler Service
        $pdfCompiler = app(\App\Services\ProcurementPdfService::class);
        $pdfCompiler->compileCoreComplianceTemplates($this->folder, $folderName);
    }
}
