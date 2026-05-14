<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLedger extends Model
{
    protected $table = 'inventory_ledgers';

    protected $fillable = [
        'stock_id',
        'unit_id',
        'type', // IN, OUT, RETURN, ADJUST
        'qty',
        'reference_no', // IAR/RIS/PRS
        'recipient_id',
        'transaction_date',
    ];

    public function stock()
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }

    public function unit()
    {
        return $this->belongsTo(InventoryUnit::class, 'unit_id');
    }

    public function recipient()
    {
        return $this->belongsTo(Employee::class, 'recipient_id');
    }

    /**
     * Get the running balance for a specific stock ID.
     */
    public static function getRunningBalance($stockId)
    {
        $in = self::where('stock_id', $stockId)
            ->whereIn('type', ['IN', 'RETURN', 'ADJUST'])
            ->sum('qty');

        $out = self::where('stock_id', $stockId)
            ->where('type', 'OUT')
            ->sum('qty');

        return $in - $out;
    }
}
