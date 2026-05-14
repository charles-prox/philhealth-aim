<?php

namespace App\Repositories;

use App\Models\InventoryLedger;
use App\Models\InventoryUnit;
use App\Models\InventoryStock;

class InventoryRepository
{
    public function getRunningBalance($stockId)
    {
        return InventoryLedger::getRunningBalance($stockId);
    }

    public function getAvailableUnits($stockId)
    {
        return InventoryUnit::where('stock_id', $stockId)
            ->where('status', 'STOCK')
            ->get();
    }

    public function findUnitBySerialNumber($serialNumber)
    {
        return InventoryUnit::where('serial_number', $serialNumber)->first();
    }
}
