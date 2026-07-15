<?php

namespace App\Services;

use App\Jobs\GenerateProcurementDocumentsJob;
use App\Models\AppHeader;
use App\Models\AppLineItem;
use App\Models\BudgetYear;
use App\Models\CobItemDistribution;
use App\Models\Employee;
use App\Models\PrItem;
use App\Models\ProcurementFolder;
use App\Models\ProcurementLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcurementService
{
    /**
     * Compile and save/update the Purchase Request folder, item lines, attachments, and logs in a transaction.
     */
    public function compileAndSavePr(
        array $basket,
        array $stagedFiles,
        array $stagedFileNames,
        array $folderData,
        ?string $folderId = null
    ): ProcurementFolder {
        $requestedEmployee = auth()->user()->employee;
        $requestedById = $requestedEmployee?->id;
        $requestedByDesignation = $requestedEmployee?->designation ?? 'Requesting Officer';

        $recommendedEmployee = Employee::findOrFail((int) $folderData['recommendedById']);
        $approvedEmployee = Employee::findOrFail((int) $folderData['approvedById']);

        $status = 'DRAFT';

        $folder = DB::transaction(function () use ($status, $requestedEmployee, $requestedById, $requestedByDesignation, $recommendedEmployee, $approvedEmployee, $folderData, $basket, $stagedFiles, $stagedFileNames, $folderId) {
            if ($folderId) {
                $folder = ProcurementFolder::findOrFail($folderId);

                // Release old utilized budgets
                foreach ($folder->prItems as $oldItem) {
                    if ($oldItem->app_line_item_id) {
                        $appLineItem = AppLineItem::find($oldItem->app_line_item_id);
                        if ($appLineItem) {
                            $appLineItem->decrement('utilized_budget', $oldItem->estimated_total_cost);
                        }
                    }
                }

                $folder->prItems()->delete();

                $folder->update([
                    'tracking_number' => $folderData['trackingNumber'],
                    'overall_purpose' => $folderData['purpose'],
                    'status' => $status,
                    'requested_signed_at' => null,
                    'requesting_unit' => $requestedEmployee?->office_division,
                    'requested_by_id' => $requestedById,
                    'requested_by_designation' => $requestedByDesignation,
                    'recommended_by_id' => $folderData['recommendedById'],
                    'recommended_by_designation' => $recommendedEmployee->designation,
                    'approved_by_id' => $folderData['approvedById'],
                    'approved_by_designation' => $approvedEmployee->designation,
                    'office_id' => auth()->user()->office_id,
                    'created_by_id' => auth()->id(),
                    'procurement_category' => $folderData['procurementCategory'],
                    'event_start_date' => $folderData['isTiedToEvent'] ? ($folderData['eventDate'] ?? null) : null,
                    'event_end_date' => $folderData['isTiedToEvent'] ? ($folderData['eventDate'] ?? null) : null,
                ]);
            } else {
                $folder = ProcurementFolder::create([
                    'tracking_number' => $folderData['trackingNumber'],
                    'project_title' => 'PR compiled from APP on ' . now()->format('Y-m-d H:i'),
                    'procurement_method' => 'Shopping',
                    'overall_purpose' => $folderData['purpose'],
                    'status' => $status,
                    'requested_signed_at' => null,
                    'requesting_unit' => $requestedEmployee?->office_division,
                    'requested_by_id' => $requestedById,
                    'requested_by_designation' => $requestedByDesignation,
                    'recommended_by_id' => $folderData['recommendedById'],
                    'recommended_by_designation' => $recommendedEmployee->designation,
                    'approved_by_id' => $folderData['approvedById'],
                    'approved_by_designation' => $approvedEmployee->designation,
                    'office_id' => auth()->user()->office_id,
                    'created_by_id' => auth()->id(),
                    'procurement_category' => $folderData['procurementCategory'],
                    'event_start_date' => $folderData['isTiedToEvent'] ? ($folderData['eventDate'] ?? null) : null,
                    'event_end_date' => $folderData['isTiedToEvent'] ? ($folderData['eventDate'] ?? null) : null,
                ]);
            }

            if (!empty($stagedFiles)) {
                $folderName = preg_replace('/[^A-Za-z0-9\-]/', '_', $folder->tracking_number);
                $employeeId = auth()->user()->employee_id ?? 1;

                Storage::disk('secure_procurement')->makeDirectory("{$folderName}/uploaded");

                foreach ($stagedFiles as $index => $extraFile) {
                    $fileName = 'SUPPORTING_' . ($index + 1) . '_' . time() . '.' . $extraFile->getClientOriginalExtension();

                    // Stream the user data straight to the private uploaded directory channel
                    $storedPath = $extraFile->storeAs(
                        "{$folderName}/uploaded",
                        $fileName,
                        'secure_procurement'
                    );

                    $customName = trim($stagedFileNames[$index]);
                    $extension = $extraFile->getClientOriginalExtension();
                    if (!str_ends_with(strtolower($customName), '.' . strtolower($extension))) {
                        $customName .= '.' . $extension;
                    }

                    // Catalog file metadata
                    $folder->attachments()->create([
                        'attachment_type' => 'USER_OTHER',
                        'file_path' => $storedPath,
                        'original_name' => $customName,
                        'mime_type' => $extraFile->getMimeType(),
                        'file_size' => $extraFile->getSize(),
                        'uploaded_by_employee_id' => $employeeId,
                    ]);
                }
            }

            foreach ($basket as $basketKey => $itemData) {
                $prItem = PrItem::create([
                    'folder_id' => $folder->id,
                    'cob_item_id' => null,
                    'app_line_item_id' => $itemData['app_line_item_id'],
                    'item_description_override' => $itemData['description'],
                    'total_qty' => $itemData['qty'],
                    'unit' => $itemData['unit'] ?? 'pcs',
                    'unit_cost' => $itemData['unit_cost'],
                    'estimated_unit_cost' => $itemData['unit_cost'],
                ]);

                $appLineItem = AppLineItem::find($itemData['app_line_item_id']);
                if ($appLineItem) {
                    $appLineItem->increment('utilized_budget', $prItem->estimated_total_cost);
                }
            }

            // Create Log
            $logAction = ($folderId && in_array($folder->status ?? '', ['RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE'])) ? 'RESUBMITTED' : 'CREATED';
            $logRemarks = 'PR draft compiled and successfully saved. Pending custodian signature and submission.';

            ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => $logAction,
                'actor_id' => $requestedById,
                'remarks' => $logRemarks,
            ]);

            return $folder;
        });

        GenerateProcurementDocumentsJob::dispatchSync($folder);

        return $folder;
    }

    /**
     * Validate basket items — checks field completeness and budget availability.
     *
     * Returns an array of [basketKey => errorMessage] pairs.
     * An empty array means the basket is valid.
     */
    public function validateBasket(array $basket, ?string $folderId = null): array
    {
        $errors = [];

        foreach ($basket as $basketKey => $itemData) {
            $qty = (int) ($itemData['qty'] ?? 0);
            $unitCost = (float) ($itemData['unit_cost'] ?? 0.0);
            $desc = $itemData['description'] ?? '';
            $unit = $itemData['unit'] ?? '';

            if (empty($desc)) {
                $errors["{$basketKey}.description"] = 'Particulars/description is required.';
            }
            if (empty($unit)) {
                $errors["{$basketKey}.unit"] = 'Unit is required.';
            }
            if ($qty <= 0) {
                $errors["{$basketKey}.qty"] = 'Quantity must be at least 1.';
            }
            if ($unitCost < 0.0) {
                $errors["{$basketKey}.unit_cost"] = 'Estimated unit cost cannot be negative.';
            }
        }

        // Budget availability check — grouped by APP line item
        $totalsByAppLine = collect($basket)
            ->groupBy('app_line_item_id')
            ->map(fn ($items) => $items->sum(fn ($i) => (int) $i['qty'] * (float) $i['unit_cost']));

        foreach ($totalsByAppLine as $appLineItemId => $totalCost) {
            $appLineItem = AppLineItem::find($appLineItemId);
            if (!$appLineItem) {
                continue;
            }

            $availableBudget = $appLineItem->approved_budget - $appLineItem->utilized_budget;

            // Add back what the current folder already committed (editing scenario)
            if ($folderId) {
                $alreadyUtilized = PrItem::where('folder_id', $folderId)
                    ->where('app_line_item_id', $appLineItemId)
                    ->sum('estimated_total_cost');
                $availableBudget += $alreadyUtilized;
            }

            if ($totalCost > $availableBudget) {
                $firstKey = collect($basket)
                    ->where('app_line_item_id', $appLineItemId)
                    ->keys()
                    ->first();

                $errors["{$firstKey}.unit_cost"] = sprintf(
                    'Combined items cost (₱%s) under %s exceeds available budget of ₱%s.',
                    number_format($totalCost, 2),
                    $appLineItem->project_title,
                    number_format($availableBudget, 2)
                );
            }
        }

        return $errors;
    }

    /**
     * Compiles a Purchase Request from selected COB Item Distributions.
     */
    public function compilePrFromCob(
        array $selectedIds,
        array $folderData,
        ?string $folderId = null
    ): ProcurementFolder {
        // Resolve the requesting employee via verified FK link
        $requestedEmployee = auth()->user()->employee;
        $requestedById = $requestedEmployee?->id;
        $requestedByDesignation = $requestedEmployee?->designation ?? 'Requesting Officer';

        $recommendedEmployee = Employee::findOrFail((int) $folderData['recommendedById']);
        $approvedEmployee = Employee::findOrFail((int) $folderData['approvedById']);

        $distributions = CobItemDistribution::whereIn('id', $selectedIds)
            ->where(function ($q) use ($folderId) {
                $q->whereNull('pr_item_id')
                    ->orWhereHas('prItem', function ($sq) use ($folderId) {
                        $sq->where('folder_id', $folderId);
                    });
            })
            ->whereNull('deleted_at')
            ->with(['cobItem'])
            ->get();

        if ($distributions->isEmpty()) {
            throw new \RuntimeException('All selected allocations are already locked into a PR.');
        }

        $folder = DB::transaction(function () use ($distributions, $requestedEmployee, $requestedById, $requestedByDesignation, $recommendedEmployee, $approvedEmployee, $folderData, $folderId) {
            if ($folderId) {
                $folder = ProcurementFolder::findOrFail($folderId);

                // Release old utilized budgets
                foreach ($folder->prItems as $oldItem) {
                    if ($oldItem->app_line_item_id) {
                        $appLineItem = AppLineItem::find($oldItem->app_line_item_id);
                        if ($appLineItem) {
                            $appLineItem->decrement('utilized_budget', $oldItem->estimated_total_cost);
                        }
                    }
                }

                // Release old allocations
                CobItemDistribution::whereHas('prItem', function ($q) use ($folderId) {
                    $q->where('folder_id', $folderId);
                })->update([
                    'pr_item_id' => null,
                    'procured_quantity' => 0,
                ]);

                // Delete old items
                $folder->prItems()->delete();

                // Update folder
                $folder->update([
                    'tracking_number' => $folderData['trackingNumber'],
                    'pr_number' => $folderData['prNumber'],
                    'overall_purpose' => $folderData['purpose'],
                    'requesting_unit' => $requestedEmployee?->office_division,
                    'requested_by_id' => $requestedById,
                    'requested_by_designation' => $requestedByDesignation,
                    'recommended_by_id' => $folderData['recommendedById'],
                    'recommended_by_designation' => $recommendedEmployee->designation,
                    'approved_by_id' => $folderData['approvedById'],
                    'approved_by_designation' => $approvedEmployee->designation,
                    'office_id' => auth()->user()->office_id,
                    'created_by_id' => auth()->id(),
                ]);
            } else {
                $folder = ProcurementFolder::create([
                    'tracking_number' => $folderData['trackingNumber'],
                    'pr_number' => $folderData['prNumber'],
                    'project_title' => 'PR compiled from COB on ' . now()->format('Y-m-d H:i'),
                    'procurement_method' => 'Shopping',
                    'overall_purpose' => $folderData['purpose'],
                    'status' => 'DRAFT',
                    'requesting_unit' => $requestedEmployee?->office_division,
                    'requested_by_id' => $requestedById,
                    'requested_by_designation' => $requestedByDesignation,
                    'recommended_by_id' => $folderData['recommendedById'],
                    'recommended_by_designation' => $recommendedEmployee->designation,
                    'approved_by_id' => $folderData['approvedById'],
                    'approved_by_designation' => $approvedEmployee->designation,
                    'office_id' => auth()->user()->office_id,
                    'created_by_id' => auth()->id(),
                ]);
            }

            // Group by cob_item_id
            $grouped = $distributions->groupBy('cob_item_id');

            foreach ($grouped as $cobItemId => $distGroup) {
                $cobItem = $distGroup->first()->cobItem;
                $totalQty = $distGroup->sum('allocated_quantity');
                $recomQty = $cobItem?->recom_qty ?? 0;
                $unitCost = $recomQty > 0 ? ((float) ($cobItem?->recom_amount ?? 0) / $recomQty) : 0.0;

                $appLineItemId = null;
                $currentYear = BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
                $header = AppHeader::where('fiscal_year', $currentYear)
                    ->where('is_approved', true)
                    ->first();
                if ($header) {
                    $matchedLine = AppLineItem::where('app_header_id', $header->id)
                        ->where(function ($q) use ($cobItem) {
                            $q->where('description', 'like', '%' . $cobItem->full_particulars . '%')
                                ->orWhere('project_title', 'like', '%' . $cobItem->full_particulars . '%')
                                ->orWhere('description', 'like', '%' . $cobItem->exp_desc . '%');
                        })
                        ->first();
                    $appLineItemId = $matchedLine?->id;
                }

                $prItem = PrItem::create([
                    'folder_id' => $folder->id,
                    'cob_item_id' => $cobItemId,
                    'app_line_item_id' => $appLineItemId,
                    'total_qty' => $totalQty,
                    'unit_cost' => $unitCost,
                    'estimated_unit_cost' => $unitCost,
                ]);

                if ($appLineItemId) {
                    $matchedLine->increment('utilized_budget', $prItem->estimated_total_cost);
                }

                CobItemDistribution::whereIn('id', $distGroup->pluck('id'))
                    ->update([
                        'pr_item_id' => $prItem->id,
                        'procured_quantity' => DB::raw('allocated_quantity'),
                    ]);
            }

            return $folder;
        });

        GenerateProcurementDocumentsJob::dispatchSync($folder);

        return $folder;
    }
}
