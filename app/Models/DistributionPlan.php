<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DistributionPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'folder_id',
        'employee_id',
        'pr_item_id',
        'planned_qty',
        'serial_no',
    ];

    public function folder()
    {
        return $this->belongsTo(ProcurementFolder::class, 'folder_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function prItem()
    {
        return $this->belongsTo(PrItem::class, 'pr_item_id');
    }
}
