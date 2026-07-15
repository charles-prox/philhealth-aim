<?php

namespace App\Services;

use App\Models\AppLineItem;
use App\Models\Employee;
use App\Models\PrItem;
use App\Models\ProcurementFolder;
use App\Models\ProcurementLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Jobs\GenerateProcurementDocumentsJob;

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
        $approvedEmployee    = Employee::findOrFail((int) $folderData['approvedById']);

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
                    'tracking_number'              => $folderData['trackingNumber'],
                    'overall_purpose'              => $folderData['purpose'],
                    'status'                       => $status,
                    'requested_signed_at'          => null,
                    'requesting_unit'              => $requestedEmployee?->office_division,
                    'requested_by_id'              => $requestedById,
                    'requested_by_designation'     => $requestedByDesignation,
                    'recommended_by_id'            => $folderData['recommendedById'],
                    'recommended_by_designation'   => $recommendedEmployee->designation,
                    'approved_by_id'               => $folderData['approvedById'],
                    'approved_by_designation'      => $approvedEmployee->designation,
                    'office_id'                    => auth()->user()->office_id,
                    'created_by_id'                => auth()->id(),
                    'procurement_category'         => $folderData['procurementCategory'],
                    'event_date'                   => $folderData['isTiedToEvent'] ? $folderData['eventDate'] : null,
                ]);
            } else {
                $folder = ProcurementFolder::create([
                    'tracking_number'              => $folderData['trackingNumber'],
                    'project_title'                => 'PR compiled from APP on ' . now()->format('Y-m-d H:i'),
                    'procurement_method'           => 'Shopping',
                    'overall_purpose'              => $folderData['purpose'],
                    'status'                       => $status,
                    'requested_signed_at'          => null,
                    'requesting_unit'              => $requestedEmployee?->office_division,
                    'requested_by_id'              => $requestedById,
                    'requested_by_designation'     => $requestedByDesignation,
                    'recommended_by_id'            => $folderData['recommendedById'],
                    'recommended_by_designation'   => $recommendedEmployee->designation,
                    'approved_by_id'               => $folderData['approvedById'],
                    'approved_by_designation'      => $approvedEmployee->designation,
                    'office_id'                    => auth()->user()->office_id,
                    'created_by_id'                => auth()->id(),
                    'procurement_category'         => $folderData['procurementCategory'],
                    'event_date'                   => $folderData['isTiedToEvent'] ? $folderData['eventDate'] : null,
                ]);
            }

            if (!empty($stagedFiles)) {
                $folderName = preg_replace('/[^A-Za-z0-9\-]/', '_', $folder->tracking_number);
                $employeeId = auth()->user()->employee_id ?? 1;

                Storage::disk('secure_procurement')->makeDirectory("{$folderName}/uploaded");

                foreach ($stagedFiles as $index => $extraFile) {
                    $fileName = "SUPPORTING_" . ($index + 1) . "_" . time() . "." . $extraFile->getClientOriginalExtension();
                    
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
                        'uploaded_by_employee_id' => $employeeId
                    ]);
                }
            }

            foreach ($basket as $basketKey => $itemData) {
                $prItem = PrItem::create([
                    'folder_id'                 => $folder->id,
                    'cob_item_id'               => null,
                    'app_line_item_id'          => $itemData['app_line_item_id'],
                    'item_description_override' => $itemData['description'],
                    'total_qty'                 => $itemData['qty'],
                    'unit'                      => $itemData['unit'] ?? 'pcs',
                    'unit_cost'                 => $itemData['unit_cost'],
                    'estimated_unit_cost'       => $itemData['unit_cost'],
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
}
