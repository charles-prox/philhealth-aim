<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProcurementFolder extends Model
{
    use HasUuids;

    protected $fillable = [
        'tracking_number',
        'rfq_control_no',
        'status',
        'overall_purpose',
        'requesting_unit',
        'google_form_id',
        'google_sheet_id',
        'geps_posting_from',
        'geps_posting_to',
        'submission_due_date',
        'requested_by_id',
        'requested_by_designation',
        'approved_by_id',
        'approved_by_designation',
    ];

    protected $casts = [
        'geps_posting_from' => 'date',
        'geps_posting_to' => 'date',
        'submission_due_date' => 'date',
    ];

    public function prItems()
    {
        return $this->hasMany(PrItem::class, 'folder_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'folder_id');
    }

    public function distributionPlans()
    {
        return $this->hasMany(DistributionPlan::class, 'folder_id');
    }

    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class, 'folder_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(Employee::class, 'requested_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by_id');
    }
}
