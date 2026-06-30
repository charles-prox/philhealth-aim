<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProcurementFolder extends Model
{
    use HasUuids;

    protected static function booted()
    {
        static::updated(function ($folder) {
            if (in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER'])) {
                $disk = \Illuminate\Support\Facades\Storage::disk('public');
                if ($folder->pdf_attachment_path && $disk->exists($folder->pdf_attachment_path)) {
                    $disk->delete($folder->pdf_attachment_path);
                    $folder->quietly()->update(['pdf_attachment_path' => null]);
                }
                
                $identifier = $folder->pr_number ?: $folder->tracking_number;
                if ($identifier) {
                    $dynamicPath = "pr/{$identifier}.pdf";
                    if ($disk->exists($dynamicPath)) {
                        $disk->delete($dynamicPath);
                    }
                }
            }
        });
    }

    protected $fillable = [
        // Legacy columns (retained for backward compat)
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
        'requested_signed_at',
        'approved_by_id',
        'approved_by_designation',
        'approved_signed_at',
        'recommended_by_id',
        'recommended_by_designation',
        'recommended_signed_at',
        // Distribution-First fields
        'pr_number',
        'project_title',
        'procurement_method',
        'office_id',
        'created_by_id',
        'pdf_attachment_path',
    ];

    protected $casts = [
        'geps_posting_from' => 'date',
        'geps_posting_to' => 'date',
        'submission_due_date' => 'date',
        'requested_signed_at' => 'datetime',
        'recommended_signed_at' => 'datetime',
        'approved_signed_at' => 'datetime',
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

    public function recommendedBy()
    {
        return $this->belongsTo(Employee::class, 'recommended_by_id');
    }

    public function logs()
    {
        return $this->hasMany(ProcurementLog::class, 'procurement_folder_id')->orderBy('created_at', 'desc');
    }

    public static function generateNextPrNumber(): string
    {
        $currentYear = now()->year;
        $prefix = "PR-{$currentYear}-";
        
        $highestPr = self::where('pr_number', 'like', $prefix . '%')
            ->select('pr_number')
            ->orderBy('pr_number', 'desc')
            ->first();
            
        $nextSeq = 1;
        if ($highestPr) {
            $parts = explode('-', $highestPr->pr_number);
            $seq = end($parts);
            if (is_numeric($seq)) {
                $nextSeq = (int) $seq + 1;
            }
        }
        
        return $prefix . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
    }

    public function cancelAndPurge(string $statusTarget): void
    {
        if (!in_array($statusTarget, ['CANCELLED', 'CANCELLED_BY_USER'])) {
            throw new \InvalidArgumentException("Invalid cancellation status state.");
        }

        \DB::transaction(function () use ($statusTarget) {
            // 1. Update folder status string (Preserves the row for COA sequential auditing)
            $this->update(['status' => $statusTarget]);

            // 2. Loop through items and run the budget rollback using your custom accessor
            foreach ($this->prItems as $item) {
                if ($item->appLineItem) {
                    // Uses your mapped quantity accessor flawlessly
                    $item->appLineItem->decrement(
                        'utilized_budget', 
                        ($item->quantity * $item->estimated_unit_cost)
                    );
                }
            }

            // 3. STORAGE PURGE: Destroy heavy file from local storage disk
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            
            if ($this->pdf_attachment_path && $disk->exists($this->pdf_attachment_path)) {
                $disk->delete($this->pdf_attachment_path);
            }
            
            $identifier = $this->pr_number ?: $this->tracking_number;
            if ($identifier) {
                $dynamicPath = "pr/{$identifier}.pdf";
                if ($disk->exists($dynamicPath)) {
                    $disk->delete($dynamicPath);
                }
            }

            $this->update(['pdf_attachment_path' => null]);
        });
    }
}
