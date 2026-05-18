<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Quotation extends Model
{
    use HasUuids;

    protected $fillable = [
        'folder_id',
        'supplier_id',
        'is_winning_bid',
        'delivery_period',
        'warranty_terms',
        'price_validity_to',
    ];

    protected $casts = [
        'price_validity_to' => 'date',
        'is_winning_bid' => 'boolean',
    ];

    public function folder()
    {
        return $this->belongsTo(ProcurementFolder::class, 'folder_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }
}
