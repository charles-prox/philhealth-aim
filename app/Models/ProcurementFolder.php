<?php

namespace App\Models;

use App\Jobs\GenerateProcurementDocumentsJob;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProcurementFolder extends Model
{
    use HasUuids;

    protected static function booted()
    {
        static::saving(function ($folder) {
            if ($folder->status === 'ROUTING') {
                if (empty($folder->recommended_signed_at)) {
                    $folder->current_signatory_id = $folder->recommended_by_id;
                } elseif (empty($folder->budget_signed_at)) {
                    $folder->current_signatory_id = SignatoryRegistry::getActiveSignatoryFor('BUDGET_OFFICER');
                } elseif (empty($folder->approved_signed_at)) {
                    $folder->current_signatory_id = $folder->approved_by_id;
                } else {
                    $folder->current_signatory_id = null;
                }
            } elseif ($folder->status === 'DRAFT') {
                $creator = User::find($folder->created_by_id ?? auth()->id());
                $folder->current_signatory_id = $creator?->employee_id ?? $folder->current_signatory_id;
            } else {
                $folder->current_signatory_id = null;
            }
        });

        static::updated(function ($folder) {
            if (in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER'])) {
                $disk = Storage::disk('public');
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
                    $pendingTask = ApprovalTask::where([
                        'document_type' => get_class($folder),
                        'document_id' => $folder->id,
                        'status' => 'PENDING',
                    ])->first();

                    if ($pendingTask) {
                        if ($pendingTask->target_employee_id !== $targetId) {
                            $pendingTask->update(['status' => 'BYPASSED']);

                            ApprovalTask::create([
                                'target_employee_id' => $targetId,
                                'document_type' => get_class($folder),
                                'document_id' => $folder->id,
                                'tracking_number' => $folder->pr_number ?: $folder->tracking_number,
                                'document_label' => 'Purchase Request',
                                'originating_office' => $folder->requesting_unit ?? 'Unknown',
                                'status' => 'PENDING',
                            ]);
                        }
                    } else {
                        ApprovalTask::create([
                            'target_employee_id' => $targetId,
                            'document_type' => get_class($folder),
                            'document_id' => $folder->id,
                            'tracking_number' => $folder->pr_number ?: $folder->tracking_number,
                            'document_label' => 'Purchase Request',
                            'originating_office' => $folder->requesting_unit ?? 'Unknown',
                            'status' => 'PENDING',
                        ]);
                    }
                }
            } else {
                $taskStatus = $folder->status === 'APPROVED' ? 'SIGNED' : 'REJECTED';

                if (in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER', 'RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE'])) {
                    $taskStatus = 'REJECTED';
                }

                ApprovalTask::where([
                    'document_type' => get_class($folder),
                    'document_id' => $folder->id,
                    'status' => 'PENDING',
                ])->update(['status' => $taskStatus]);
            }
        });

        static::created(function ($folder) {
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($folder) {
                GenerateProcurementDocumentsJob::dispatchSync($folder);
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
        'budget_signed_at',
        'budget_signed_by_id',
        'budget_ppa_code',
        'budget_code',
        'budget_cob_year',
        'budget_remarks',
        'procurement_category',
        'event_start_date',
        'event_end_date',
    ];

    protected $casts = [
        'geps_posting_from' => 'date',
        'geps_posting_to' => 'date',
        'submission_due_date' => 'date',
        'requested_signed_at' => 'datetime',
        'recommended_signed_at' => 'datetime',
        'approved_signed_at' => 'datetime',
        'gsu_accepted_at' => 'datetime',
        'budget_signed_at' => 'datetime',
        'event_start_date' => 'date',
        'event_end_date' => 'date',
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

    public function budgetSignedBy()
    {
        return $this->belongsTo(Employee::class, 'budget_signed_by_id');
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
            if (empty($this->budget_signed_at)) {
                $name = $this->currentSignatory?->fullname ?? 'Budget Officer';

                return "Pending Budget Check — {$name}";
            } elseif (empty($this->recommended_signed_at)) {
                $name = $this->currentSignatory?->fullname ?? 'Recommender';

                return "Pending Recommendation — {$name}";
            } else {
                $name = $this->currentSignatory?->fullname ?? 'Approver';

                return "Pending Approval — {$name}";
            }
        }

        $statuses = [
            'DRAFT' => 'Draft',
            'SUBMITTED_TO_GSU' => 'Pending GSU Evaluation',
            'RETURNED_FOR_EDIT' => 'Returned for Edit',
            'RETURNED_FOR_COMPLIANCE' => 'Returned for Compliance',
            'APPROVED' => 'Approved & Signed',
            'RFQ' => 'RFQ Generation',
            'RFQ_SENT' => 'RFQ Sent',
            'AWARDED' => 'Awarded',
            'PO_RELEASED' => 'PO Released',
            'CANCELLED' => 'Cancelled',
            'CANCELLED_BY_USER' => 'Cancelled by User',
        ];

        return $statuses[$this->status] ?? str_replace('_', ' ', $this->status);
    }

    public function applySignature(int $employeeId): void
    {
        if ($this->current_signatory_id !== $employeeId) {
            throw new \Exception('Security Exception: You are not the active signatory for this document.');
        }

        if (empty($this->recommended_signed_at)) {
            $this->update([
                'recommended_signed_at' => now(),
            ]);

            ProcurementLog::create([
                'procurement_folder_id' => $this->id,
                'action' => 'RECOMMENDED',
                'actor_id' => $employeeId,
                'remarks' => 'PR recommended via Unified Approval Desk. Digitally signed: Purchase Request (PR).',
                'created_at' => now(),
            ]);

            // Re-compile core documents to stamp recommendation signature
            GenerateProcurementDocumentsJob::dispatchSync($this);
        } elseif (empty($this->budget_signed_at)) {
            $this->update([
                'budget_signed_at' => now(),
                'budget_signed_by_id' => $employeeId,
            ]);

            $hasABC = $this->prItems->sum(fn ($item) => (float) ($item->estimated_unit_cost ?? $item->unit_cost ?? 0.0)) > 0.0;
            $signedDocs = $hasABC ? 'Purchase Request (PR) and Approved Budget for the Contract (ABC)' : 'Purchase Request (PR)';

            ProcurementLog::create([
                'procurement_folder_id' => $this->id,
                'action' => 'BUDGET_VERIFIED',
                'actor_id' => $employeeId,
                'remarks' => "Budget checked and confirmed on {$signedDocs}. Digitally signed: {$signedDocs}.",
                'created_at' => now(),
            ]);

            // Re-compile core documents to stamp budget certification signature
            GenerateProcurementDocumentsJob::dispatchSync($this);
        } elseif (empty($this->approved_signed_at)) {
            $this->update([
                'status' => 'APPROVED',
                'approved_signed_at' => now(),
            ]);

            $hasABC = $this->prItems->sum(fn ($item) => (float) ($item->estimated_unit_cost ?? $item->unit_cost ?? 0.0)) > 0.0;
            $rvpSignerId = SignatoryRegistry::getActiveSignatoryFor('RVP');
            $isRvp = ($employeeId === $rvpSignerId);
            $signedDocs = ($hasABC && $isRvp) ? 'Purchase Request (PR) and Approved Budget for the Contract (ABC)' : 'Purchase Request (PR)';

            ProcurementLog::create([
                'procurement_folder_id' => $this->id,
                'action' => 'APPROVED',
                'actor_id' => $employeeId,
                'remarks' => "PR approved via Unified Approval Desk. Digitally signed: {$signedDocs}.",
                'created_at' => now(),
            ]);

            // Re-compile core documents to stamp approval signature
            GenerateProcurementDocumentsJob::dispatchSync($this);
        }
    }

    public function logs()
    {
        return $this->hasMany(ProcurementLog::class, 'procurement_folder_id')->orderBy('created_at', 'desc');
    }

    public static function generateNextPrNumber(): string
    {
        $year2 = substr(now()->format('y'), -2); // e.g. "26"
        $month2 = now()->format('m'); // e.g. "01"
        $static = 'PR';

        $likePattern = $year2 . '__' . $static . '-%';

        $maxSeq = self::where('pr_number', 'like', $likePattern)
            ->get()
            ->map(function ($folder) {
                $parts = explode('-', $folder->pr_number);
                $seq = end($parts);

                return is_numeric($seq) ? (int) $seq : 0;
            })
            ->max() ?? 0;

        $nextSeq = $maxSeq + 1;

        return $year2 . $month2 . $static . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }

    public function cancelAndPurge(string $statusTarget): void
    {
        if (!in_array($statusTarget, ['CANCELLED', 'CANCELLED_BY_USER'])) {
            throw new \InvalidArgumentException('Invalid cancellation status state.');
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
            $disk = Storage::disk('public');

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

    public function scopeRecentHistoryForAppLine($query, $appLineItemId, $officeId)
    {
        return $query->join('pr_items', 'procurement_folders.id', '=', 'pr_items.folder_id')
            ->leftJoin('app_line_items', 'pr_items.app_line_item_id', '=', 'app_line_items.id')
            ->where('pr_items.app_line_item_id', $appLineItemId)
            ->where('procurement_folders.office_id', $officeId)
            ->whereNotIn('procurement_folders.status', ['CANCELLED', 'CANCELLED_BY_USER'])
            ->select(
                'procurement_folders.id as folder_id',
                'procurement_folders.tracking_number',
                'procurement_folders.pr_number',
                'procurement_folders.status',
                'procurement_folders.overall_purpose',
                \Illuminate\Support\Facades\DB::raw("COALESCE(pr_items.item_description_override, app_line_items.description, 'Unknown Item') as item_desc"),
                'pr_items.total_qty as quantity',
                \Illuminate\Support\Facades\DB::raw('COALESCE(pr_items.estimated_unit_cost, pr_items.unit_cost, 0) as unit_price'),
                'procurement_folders.created_at'
            )
            ->latest('procurement_folders.created_at');
    }
}
