<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\PropertyAccountability;
use App\Repositories\InventoryRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    protected $repository;

    public function __construct(InventoryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Receive delivery and add to ledger (supports partial).
     */
    public function receiveDelivery(InventoryStock $stock, $qty, $referenceNo, $transactionDate)
    {
        DB::beginTransaction();
        try {
            // Add IN ledger entry
            $ledger = InventoryLedger::create([
                'stock_id' => $stock->id,
                'type' => 'IN',
                'qty' => $qty,
                'reference_no' => $referenceNo,
                'transaction_date' => $transactionDate,
            ]);

            DB::commit();

            return $ledger;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Issue a unit to an end user.
     */
    public function issueUnit(InventoryUnit $unit, $endUserId, $subUserId, $location, $docNumber)
    {
        if ($unit->status !== 'STOCK') {
            throw new Exception('Unit is not in STOCK.');
        }

        DB::beginTransaction();
        try {
            $unit->update(['status' => 'ISSUED']);

            // Create Accountability
            $accountability = PropertyAccountability::create([
                'doc_number' => $docNumber,
                'doc_type' => $unit->accountability_type,
                'end_user_id' => $endUserId,
                'sub_user_id' => $subUserId,
                'location' => $location,
            ]);

            // Add OUT ledger entry
            InventoryLedger::create([
                'stock_id' => $unit->stock_id,
                'unit_id' => $unit->id,
                'type' => 'OUT',
                'qty' => 1,
                'reference_no' => $docNumber,
                'recipient_id' => $subUserId ?? $endUserId,
                'transaction_date' => now(),
            ]);

            DB::commit();

            return $accountability;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Return a unit to stock or transfer.
     */
    public function returnUnit(InventoryUnit $unit, $docNumber)
    {
        DB::beginTransaction();
        try {
            $unit->update(['status' => 'RETURNED']);

            InventoryLedger::create([
                'stock_id' => $unit->stock_id,
                'unit_id' => $unit->id,
                'type' => 'RETURN',
                'qty' => 1,
                'reference_no' => $docNumber,
                'transaction_date' => now(),
            ]);

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
