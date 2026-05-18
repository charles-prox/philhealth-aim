<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PrItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'folder_id',
        'cob_item_id',
        'item_description_override',
        'total_qty',
        'unit_cost',
    ];

    public function folder()
    {
        return $this->belongsTo(ProcurementFolder::class, 'folder_id');
    }

    public function cobItem()
    {
        return $this->belongsTo(CobItem::class, 'cob_item_id');
    }

    public function distributionPlans()
    {
        return $this->hasMany(DistributionPlan::class, 'pr_item_id');
    }

    /**
     * Determines accountability based on 50k Threshold Rule.
     */
    public function getAccountabilityTypeAttribute()
    {
        return $this->unit_cost >= 50000 ? 'PAR' : 'ICS';
    }
}
