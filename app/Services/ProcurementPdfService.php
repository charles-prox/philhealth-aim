<?php

namespace App\Services;

use App\Models\ProcurementFolder;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

class ProcurementPdfService
{
    public function compileCoreComplianceTemplates(ProcurementFolder $folder, string $folderName): void
    {
        // Fallback employee ID if running in a background queue context without an active auth user session
        $employeeId = auth()->user()->employee_id ?? $folder->created_by_id ?? 1;

        // Base paths for target folder allocation
        $prPath  = "{$folderName}/generated/PR_{$folder->id}.pdf";
        $rfqPath = "{$folderName}/generated/RFQ_{$folder->id}.pdf";
        $abcPath = "{$folderName}/generated/ABC_{$folder->id}.pdf";

        $disk = Storage::disk('secure_procurement');

        // Spatie PDF handles view rendering and saving directly to the private secure disk paths
        Pdf::view('pdf.templates.purchase-request', compact('folder'))
            ->save($disk->path($prPath));
            
        Pdf::view('pdf.templates.request-for-quotation', compact('folder'))
            ->save($disk->path($rfqPath));
            
        Pdf::view('pdf.templates.approved-budget-contract', compact('folder'))
            ->save($disk->path($abcPath));

        // Create log records
        $folder->attachments()->createMany([
            [
                'attachment_type' => 'SYSTEM_PR', 
                'file_path' => $prPath, 
                'original_name' => "PR_{$folder->tracking_number}.pdf", 
                'mime_type' => 'application/pdf', 
                'file_size' => $disk->exists($prPath) ? $disk->size($prPath) : 0, 
                'uploaded_by_employee_id' => $employeeId
            ],
            [
                'attachment_type' => 'SYSTEM_RFQ', 
                'file_path' => $rfqPath, 
                'original_name' => "RFQ_Canvas_{$folder->tracking_number}.pdf", 
                'mime_type' => 'application/pdf', 
                'file_size' => $disk->exists($rfqPath) ? $disk->size($rfqPath) : 0, 
                'uploaded_by_employee_id' => $employeeId
            ],
            [
                'attachment_type' => 'SYSTEM_ABC', 
                'file_path' => $abcPath, 
                'original_name' => "ABC_Summary_{$folder->tracking_number}.pdf", 
                'mime_type' => 'application/pdf', 
                'file_size' => $disk->exists($abcPath) ? $disk->size($abcPath) : 0, 
                'uploaded_by_employee_id' => $employeeId
            ],
        ]);
    }
}
