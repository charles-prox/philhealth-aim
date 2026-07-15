<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryUnit extends Model
{
    protected $fillable = [
        'stock_id',
        'serial_number',
        'property_number',
        'status', // STOCK, ISSUED, REPAIR, DISPOSED, RETURNED
    ];

    public function stock()
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id');
    }

    /**
     * Get the accountability type (PAR or ICS) based on the unit cost.
     * PAR if >= 50,000, else ICS.
     */
    public function getAccountabilityTypeAttribute()
    {
        if ($this->stock && $this->stock->unit_cost >= 50000) {
            return 'PAR';
        }

        return 'ICS';
    }
}
