<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'folder_id',
        'po_number',
        'supplier_id',
        'total_amount',
        'mode_of_procurement',
    ];

    public function folder()
    {
        return $this->belongsTo(ProcurementFolder::class, 'folder_id');
    }
}
