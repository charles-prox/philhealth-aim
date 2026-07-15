<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function receive(Request $request, InventoryStock $stock)
    {
        $request->validate([
            'qty' => 'required|integer|min:1',
            'reference_no' => 'required|string',
            'transaction_date' => 'required|date',
        ]);

        $this->inventoryService->receiveDelivery(
            $stock,
            $request->qty,
            $request->reference_no,
            $request->transaction_date
        );

        return redirect()->back()->with('success', 'Delivery received and IAR generated.');
    }

    public function issue(Request $request, InventoryUnit $unit)
    {
        $request->validate([
            'end_user_id' => 'required|exists:employees,id',
            'sub_user_id' => 'nullable|exists:employees,id',
            'location' => 'required|string',
            'doc_number' => 'required|string|unique:property_accountabilities,doc_number',
        ]);

        $this->inventoryService->issueUnit(
            $unit,
            $request->end_user_id,
            $request->sub_user_id,
            $request->location,
            $request->doc_number
        );

        return redirect()->back()->with('success', 'Unit issued. Document generated.');
    }

    public function return(Request $request, InventoryUnit $unit)
    {
        $request->validate([
            'doc_number' => 'required|string',
        ]);

        $this->inventoryService->returnUnit($unit, $request->doc_number);

        return redirect()->back()->with('success', 'Unit returned successfully.');
    }
}
