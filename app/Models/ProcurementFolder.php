<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProcurementFolder extends Model
{
    use HasUuids;

    protected static function booted()
    {
        static::saving(function ($folder) {
            if ($folder->status === 'ROUTING') {
                if (empty($folder->recommended_signed_at)) {
                    $folder->current_signatory_id = $folder->recommended_by_id;
                } elseif (empty($folder->approved_signed_at)) {
                    $folder->current_signatory_id = $folder->approved_by_id;
                } else {
                    $folder->current_signatory_id = null;
                }
            } else {
                $folder->current_signatory_id = null;
            }
        });

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

        static::saved(function ($folder) {
            if ($folder->status === 'ROUTING') {
                $targetId = $folder->current_signatory_id;
                if ($targetId) {
                    $pendingTask = \App\Models\ApprovalTask::where([
                        'document_type' => get_class($folder),
                        'document_id' => $folder->id,
                        'status' => 'PENDING',
                    ])->first();

                    if ($pendingTask) {
                        if ($pendingTask->target_employee_id !== $targetId) {
                            $pendingTask->update(['status' => 'BYPASSED']);
                            
                            \App\Models\ApprovalTask::create([
                                'target_employee_id' => $targetId,
                                'document_type'      => get_class($folder),
                                'document_id'        => $folder->id,
                                'tracking_number'    => $folder->pr_number ?: $folder->tracking_number,
                                'document_label'     => 'Purchase Request',
                                'originating_office' => $folder->requesting_unit ?? 'Unknown',
                                'status'             => 'PENDING',
                            ]);
                        }
                    } else {
                        \App\Models\ApprovalTask::create([
                            'target_employee_id' => $targetId,
                            'document_type'      => get_class($folder),
                            'document_id'        => $folder->id,
                            'tracking_number'    => $folder->pr_number ?: $folder->tracking_number,
                            'document_label'     => 'Purchase Request',
                            'originating_office' => $folder->requesting_unit ?? 'Unknown',
                            'status'             => 'PENDING',
                        ]);
                    }
                }
            } else {
                $taskStatus = $folder->status === 'APPROVED' ? 'SIGNED' : 'REJECTED';
                
                if (in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER', 'RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE'])) {
                    $taskStatus = 'REJECTED';
                }

                \App\Models\ApprovalTask::where([
                    'document_type' => get_class($folder),
                    'document_id' => $folder->id,
                    'status' => 'PENDING',
                ])->update(['status' => $taskStatus]);
            }
        });

        static::created(function ($folder) {
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($folder) {
                \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($folder);
            });
        });
    }

    public function attachments()
    {
        return $this->hasMany(ProcurementAttachment::class, 'procurement_folder_id');
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
        'current_signatory_id',
        'gsu_accepted_at',
        'gsu_accepted_by_id',
    ];

    protected $casts = [
        'geps_posting_from' => 'date',
        'geps_posting_to' => 'date',
        'submission_due_date' => 'date',
        'requested_signed_at' => 'datetime',
        'recommended_signed_at' => 'datetime',
        'approved_signed_at' => 'datetime',
        'gsu_accepted_at' => 'datetime',
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

    public function currentSignatory()
    {
        return $this->belongsTo(Employee::class, 'current_signatory_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->status === 'ROUTING') {
            if (empty($this->recommended_signed_at)) {
                $name = $this->currentSignatory?->fullname ?? 'Recommender';
                return "Pending Recommendation — {$name}";
            } else {
                $name = $this->currentSignatory?->fullname ?? 'Approver';
                return "Pending Approval — {$name}";
            }
        }

        $statuses = [
            'DRAFT'                    => 'Draft',
            'SUBMITTED_TO_GSU'         => 'Pending GSU Evaluation',
            'RETURNED_FOR_EDIT'        => 'Returned for Edit',
            'RETURNED_FOR_COMPLIANCE'  => 'Returned for Compliance',
            'APPROVED'                 => 'Approved & Signed',
            'RFQ'                      => 'RFQ Generation',
            'RFQ_SENT'                 => 'RFQ Sent',
            'AWARDED'                  => 'Awarded',
            'PO_RELEASED'              => 'PO Released',
            'CANCELLED'                => 'Cancelled',
            'CANCELLED_BY_USER'        => 'Cancelled by User',
        ];

        return $statuses[$this->status] ?? str_replace('_', ' ', $this->status);
    }

    public function applySignature(int $employeeId): void
    {
        if ($this->current_signatory_id !== $employeeId) {
            throw new \Exception("Security Exception: You are not the active signatory for this document.");
        }

        if (empty($this->recommended_signed_at)) {
            $this->update([
                'recommended_signed_at' => now(),
            ]);

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $this->id,
                'action' => 'RECOMMENDED',
                'actor_id' => $employeeId,
                'remarks' => 'PR recommended via Unified Approval Desk.',
                'created_at' => now(),
            ]);

            // Re-compile core documents to stamp recommendation signature
            \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($this);
        } elseif (empty($this->approved_signed_at)) {
            $this->update([
                'status' => 'APPROVED',
                'approved_signed_at' => now(),
            ]);

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $this->id,
                'action' => 'APPROVED',
                'actor_id' => $employeeId,
                'remarks' => 'PR approved via Unified Approval Desk.',
                'created_at' => now(),
            ]);

            // Re-compile core documents to stamp approval signature
            \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($this);
        }
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
