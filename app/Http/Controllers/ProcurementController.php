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
        if (in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER'])) {
            abort(403, 'Generating or viewing PDFs for Draft or Cancelled Purchase Requests is not allowed.');
        }

        $identifier = $folder->pr_number ?: $folder->tracking_number;
        $storagePath = "pr/{$identifier}.pdf";
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        // Check if PDF exists in storage, if not generate it on-the-fly
        if (!$disk->exists($storagePath)) {
            try {
                if (!$disk->exists('pr')) {
                    $disk->makeDirectory('pr');
                }
                Pdf::view('pdf.pr-form', ['folder' => $folder])
                    ->save($disk->path($storagePath));
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                
                \Illuminate\Support\Facades\Log::error("Failed to generate PR PDF: " . $errorMessage, ['exception' => $e]);
                
                return back()->with('error', 'Failed to generate PR PDF: ' . $errorMessage);
            }
        }

        if (!$disk->exists($storagePath)) {
            abort(404, 'PDF file could not be generated.');
        }

        return response()->file($disk->path($storagePath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $identifier . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
