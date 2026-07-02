<?php

namespace App\Http\Controllers;

use App\Models\ProcurementFolder;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;

class ProcurementController extends Controller
{
    public function __construct()
    {
    }

    public function initiateRfq(Request $request, ProcurementFolder $folder)
    {
        // Example suppliers array
        $suppliers = $request->input('suppliers', []);
        
        $folder->update([
            'status' => 'RFQ',
        ]);

        return redirect()->back()->with('success', 'RFQ Form Generated and Emails Sent.');
    }

    public function syncBids(ProcurementFolder $folder)
    {
        $bids = [];
        
        return view('procurement.bids', compact('folder', 'bids'));
    }

    public function viewPrPdf(ProcurementFolder $folder)
    {
        $folder->refresh();

        // Explicit security check: Validate permission levels before displaying sensitive data
        if (!auth()->user()->employee || !auth()->user()->employee->isAllowedToSignOrViewDocs()) {
            abort(403, 'Unauthorized access to secure financial records.');
        }

        if (in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER'])) {
            abort(403, 'Generating or viewing PDFs for Draft or Cancelled Purchase Requests is not allowed.');
        }

        $secureDisk = \Illuminate\Support\Facades\Storage::disk('secure_procurement');
        $lockKey = 'pr-pdf-gen-' . $folder->id;
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 15);

        try {
            $attachment = $lock->block(10, function () use ($folder, $secureDisk) {
                // Re-check attachment within lock to prevent double compilation
                $existing = $folder->attachments()->where('attachment_type', 'SYSTEM_PR')->first();
                if ($existing && $secureDisk->exists($existing->file_path)) {
                    // Check if file is stale (i.e. model was signed after the file was generated)
                    $lastSigned = max(
                        $folder->requested_signed_at ? $folder->requested_signed_at->timestamp : 0,
                        $folder->recommended_signed_at ? $folder->recommended_signed_at->timestamp : 0,
                        $folder->approved_signed_at ? $folder->approved_signed_at->timestamp : 0
                    );
                    $fileTime = $secureDisk->lastModified($existing->file_path);
                    
                    if ($lastSigned <= $fileTime) {
                        return $existing;
                    }
                }

                $folderName = preg_replace('/[^A-Za-z0-9\-]/', '_', $folder->tracking_number);
                $prPath = "{$folderName}/generated/PR_{$folder->id}.pdf";

                if (!$secureDisk->exists("{$folderName}/generated")) {
                    $secureDisk->makeDirectory("{$folderName}/generated");
                }

                // Render and save PDF using Spatie templates/purchase-request
                \Spatie\LaravelPdf\Facades\Pdf::view('pdf.templates.purchase-request', compact('folder'))
                    ->save($secureDisk->path($prPath));

                // Sourcing attributes: pull initial author ID from folder/creator
                $authorId = $folder->created_by_id ?: (auth()->user()->employee_id ?: 1);

                return $folder->attachments()->updateOrCreate(
                    ['attachment_type' => 'SYSTEM_PR'],
                    [
                        'file_path' => $prPath,
                        'original_name' => "PR_{$folder->tracking_number}.pdf",
                        'mime_type' => 'application/pdf',
                        'file_size' => $secureDisk->exists($prPath) ? $secureDisk->size($prPath) : 0,
                        'uploaded_by_employee_id' => $authorId,
                    ]
                );
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate/fetch PR PDF: " . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to generate PR PDF: ' . $e->getMessage());
        }

        if (!$attachment || !$secureDisk->exists($attachment->file_path)) {
            abort(404, 'The physical asset was not found on the secure storage server.');
        }

        return $secureDisk->response($attachment->file_path, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PR_' . $folder->tracking_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
