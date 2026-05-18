<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class QuotationItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'quotation_id',
        'pr_item_id',
        'unit_price',
        'total_price',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function prItem()
    {
        return $this->belongsTo(PrItem::class, 'pr_item_id');
    }
}
