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
}
