<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class PurchaseOrder extends Model
{
    use HasUuids;

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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Converts the total amount into words for strict PO compliance.
     */
    public function getTotalInWordsAttribute()
    {
        $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);

        return ucwords($formatter->format($this->total_amount)) . ' Pesos Only';
    }
}
