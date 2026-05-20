<?php

namespace App\Http\Controllers;

use App\Models\ProcurementFolder;
use App\Services\GoogleIntegrationService;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    protected $googleService;

    public function __construct(GoogleIntegrationService $googleService)
    {
        $this->googleService = $googleService;
    }

    public function initiateRfq(Request $request, ProcurementFolder $folder)
    {
        // Example suppliers array
        $suppliers = $request->input('suppliers', []);
        
        $this->googleService->initiateRfq($folder, $suppliers);

        return redirect()->back()->with('success', 'RFQ Form Generated and Emails Sent.');
    }

    public function syncBids(ProcurementFolder $folder)
    {
        $bids = $this->googleService->syncBidsFromSheet($folder);
        
        return view('procurement.bids', compact('folder', 'bids'));
    }

    public function viewPrPdf(ProcurementFolder $folder)
    {
        $storagePath = "pr/{$folder->pr_number}.pdf";
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        // Check if PDF exists in storage, if not generate it on-the-fly
        if (!$disk->exists($storagePath)) {
            try {
                $this->googleService->generatePrPdf($folder);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate PR PDF: ' . $e->getMessage());
            }
        }

        if (!$disk->exists($storagePath)) {
            abort(404, 'PDF file could not be generated.');
        }

        return response()->file($disk->path($storagePath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $folder->pr_number . '.pdf"'
        ]);
    }
}
