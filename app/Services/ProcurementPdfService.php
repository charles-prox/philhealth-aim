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
        $prPath    = "{$folderName}/generated/PR_{$folder->id}.pdf";
        $coverPath = "{$folderName}/generated/COVER_{$folder->id}.pdf";
        $abcPath   = "{$folderName}/generated/ABC_{$folder->id}.pdf";

        $disk = Storage::disk('secure_procurement');

        // Spatie PDF handles view rendering and saving directly to the private secure disk paths
        Pdf::view('pdf.templates.purchase-request', compact('folder'))
            ->save($disk->path($prPath));
            
        Pdf::view('pdf.templates.cover-letter', compact('folder'))
            ->save($disk->path($coverPath));

        // Dynamically check if there is an ABC value (i.e. unit price/cost is greater than zero)
        $hasABC = $folder->prItems->sum(fn($item) => (float) ($item->estimated_unit_cost ?? $item->unit_cost ?? 0.0)) > 0.0;

        if ($hasABC) {
            Pdf::view('pdf.templates.approved-budget-contract', compact('folder'))
                ->save($disk->path($abcPath));
        } else {
            // Delete old SYSTEM_ABC attachment if it exists
            $folder->attachments()->where('attachment_type', 'SYSTEM_ABC')->delete();
        }

        // Purge any existing SYSTEM_RFQ attachments to clear them from UI tabs
        $folder->attachments()->where('attachment_type', 'SYSTEM_RFQ')->delete();

        $docName = $folder->pr_number ?: $folder->tracking_number;

        // Create or update log records uniquely to prevent duplication
        $attachments = [
            'SYSTEM_PR' => [
                'file_path' => $prPath, 
                'original_name' => "PR_{$docName}.pdf", 
                'mime_type' => 'application/pdf', 
                'file_size' => $disk->exists($prPath) ? $disk->size($prPath) : 0, 
                'uploaded_by_employee_id' => $employeeId
            ],
            'SYSTEM_COVER_LETTER' => [
                'file_path' => $coverPath, 
                'original_name' => "Cover_Letter_{$docName}.pdf", 
                'mime_type' => 'application/pdf', 
                'file_size' => $disk->exists($coverPath) ? $disk->size($coverPath) : 0, 
                'uploaded_by_employee_id' => $employeeId
            ],
        ];

        if ($hasABC) {
            $attachments['SYSTEM_ABC'] = [
                'file_path' => $abcPath, 
                'original_name' => "ABC_Summary_{$docName}.pdf", 
                'mime_type' => 'application/pdf', 
                'file_size' => $disk->exists($abcPath) ? $disk->size($abcPath) : 0, 
                'uploaded_by_employee_id' => $employeeId
            ];
        }

        foreach ($attachments as $type => $data) {
            $folder->attachments()->updateOrCreate(
                ['attachment_type' => $type],
                $data
            );
        }
    }
}
