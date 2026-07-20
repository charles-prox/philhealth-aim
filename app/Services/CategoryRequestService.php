<?php

namespace App\Services;

use App\Models\ProcurementCategoryRequest;
use App\Models\ProcurementCategory;
use Illuminate\Support\Facades\DB;

class CategoryRequestService
{
    public function approve(ProcurementCategoryRequest $request, array $validatedData): void
    {
        DB::transaction(function () use ($request, $validatedData) {
            // 1. Create the official UACS-compliant procurement category
            ProcurementCategory::create([
                'name' => $validatedData['name'],
                'uacs_code' => $validatedData['uacs_code'],
                'budget_class' => $validatedData['budget_class'],
                'tracking_type' => $validatedData['tracking_type'],
                'audit_requirement' => $validatedData['audit_requirement'],
            ]);

            // 2. Mark the request as APPROVED
            $request->update(['status' => 'APPROVED']);
        });
    }

    public function reject(ProcurementCategoryRequest $request, string $reason): void
    {
        $request->update([
            'status' => 'REJECTED',
            'rejection_reason' => $reason
        ]);
    }
}
