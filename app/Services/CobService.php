<?php

namespace App\Services;

use App\Models\BudgetYear;
use App\Models\CobItem;
use App\Models\CobVersion;

class CobService
{
    /**
     * Create a new budget year.
     */
    public function createBudgetYear(string $year): BudgetYear
    {
        return BudgetYear::create([
            'fiscal_year' => (int) $year,
            'status' => 'OPEN',
            'total_allocation' => 0,
        ]);
    }

    /**
     * Set a budget year as active/OPEN and locks others.
     */
    public function activateYear(string $yearId): void
    {
        BudgetYear::query()->update(['status' => 'LOCKED']);
        BudgetYear::findOrFail($yearId)->update(['status' => 'OPEN']);
    }

    /**
     * Create a new COB version container.
     */
    public function createVersion(string $yearId, string $versionName, int $userId): CobVersion
    {
        return CobVersion::create([
            'budget_year_id' => $yearId,
            'version_name' => $versionName,
            'is_active' => false,
            'created_by' => $userId,
        ]);
    }

    /**
     * Activate/Approve a specific COB version and matches/supersedes the lineage from old version items.
     */
    public function activateVersion(string $versionId): void
    {
        $version = CobVersion::findOrFail($versionId);

        // 1. Find the currently active version(s)
        $activeVersions = CobVersion::where('budget_year_id', $version->budget_year_id)
            ->where('is_active', true)
            ->get();

        foreach ($activeVersions as $activeVer) {
            // Mark old version as superseded
            $activeVer->update(['is_active' => false, 'status' => 'SUPERSEDED']);

            // Mark items as superseded
            CobItem::where('version_id', $activeVer->id)
                ->update(['is_active' => false, 'status' => 'SUPERSEDED']);

            $oldItems = CobItem::where('version_id', $activeVer->id)
                ->whereNull('superseded_by_id')
                ->get();

            $matchedNewItemIds = [];

            foreach ($oldItems as $oldItem) {
                // Compound Fingerprint Matching
                $match = CobItem::where('version_id', $version->id)
                    ->whereNotIn('id', $matchedNewItemIds)
                    ->where(function ($q) use ($oldItem) {
                        if (!empty($oldItem->transaction_id)) {
                            $q->where('transaction_id', $oldItem->transaction_id);
                        }
                        $q->where('ppa_code', $oldItem->ppa_code)
                            ->where('ppa_desc', $oldItem->ppa_desc)
                            ->where('full_particulars', $oldItem->full_particulars);
                    })
                    ->first();

                // Fallback: TransactionID + Particulars1/2
                if (!$match && !empty($oldItem->transaction_id)) {
                    $match = CobItem::where('version_id', $version->id)
                        ->whereNotIn('id', $matchedNewItemIds)
                        ->where('transaction_id', $oldItem->transaction_id)
                        ->where('particulars1', $oldItem->particulars1)
                        ->where('particulars2', $oldItem->particulars2)
                        ->first();
                }

                // Last Resort: TransactionID only
                if (!$match && !empty($oldItem->transaction_id)) {
                    $match = CobItem::where('version_id', $version->id)
                        ->whereNotIn('id', $matchedNewItemIds)
                        ->where('transaction_id', $oldItem->transaction_id)
                        ->first();
                }

                if ($match) {
                    $oldItem->update(['superseded_by_id' => $match->id]);
                    $matchedNewItemIds[] = $match->id;
                }
            }
        }

        // 2. Activate the new version
        $version->update([
            'is_active' => true,
            'status' => 'APPROVED',
        ]);

        CobItem::where('version_id', $version->id)
            ->update(['is_active' => true, 'status' => 'APPROVED']);
    }

    /**
     * Create a revision DRAFT from an APPROVED version by cloning items.
     */
    public function createRevision(string $versionId, string $remarks, int $userId): string
    {
        $oldVersion = CobVersion::with('cobItems')->findOrFail($versionId);

        if ($oldVersion->status !== 'APPROVED') {
            throw new \RuntimeException('Only APPROVED versions can be revised.');
        }

        $revisionCount = CobVersion::where('budget_year_id', $oldVersion->budget_year_id)
            ->where('version_name', 'like', $oldVersion->version_name . ' - Revision %')
            ->count() + 1;

        $newName = $oldVersion->version_name . (str_contains($oldVersion->version_name, ' - Revision') ? '' : " - Revision {$revisionCount}");

        $newVersion = CobVersion::create([
            'budget_year_id' => $oldVersion->budget_year_id,
            'version_name' => $newName,
            'is_active' => false,
            'status' => 'DRAFT',
            'remarks' => $remarks,
            'created_by' => $userId,
        ]);

        foreach ($oldVersion->cobItems as $oldItem) {
            $newItem = CobItem::create([
                'version_id' => $newVersion->id,
                'recom_amount' => $oldItem->recom_amount,
                'encumbered_amount' => $oldItem->encumbered_amount,
                'actual_spent' => $oldItem->actual_spent,
                'current_balance' => $oldItem->current_balance,
                'ppa_code' => $oldItem->ppa_code,
                'ppa_desc' => $oldItem->ppa_desc,
                'sub_ppa_code' => $oldItem->sub_ppa_code,
                'sub_ppa_desc' => $oldItem->sub_ppa_desc,
                'exp_desc' => $oldItem->exp_desc,
                'is_ict' => $oldItem->is_ict,
                'account' => $oldItem->account,
                'tier' => $oldItem->tier,
                'class' => $oldItem->class,
                'gass' => $oldItem->gass,
                'transaction_id' => $oldItem->transaction_id,
                'work_and_financial_plan_id' => $oldItem->work_and_financial_plan_id,
                'office_id' => $oldItem->office_id,
                'sector' => $oldItem->sector,
                'full_particulars' => $oldItem->full_particulars,
                'particulars1' => $oldItem->particulars1,
                'particulars2' => $oldItem->particulars2,
                'unit' => $oldItem->unit,
                'recom_qty' => $oldItem->recom_qty,
                'version_number' => $oldItem->version_number + 1,
                'is_active' => false,
                'status' => 'DRAFT',
            ]);

            $oldItem->update(['superseded_by_id' => $newItem->id]);
        }

        return $newName;
    }

    /**
     * Delete a COB version container.
     */
    public function deleteVersion(string $versionId): void
    {
        $version = CobVersion::findOrFail($versionId);

        if ($version->is_active) {
            throw new \RuntimeException('Cannot delete an active COB version. Deactivate it first.');
        }

        $itemIds = CobItem::where('version_id', $version->id)->pluck('id');
        CobItem::whereIn('superseded_by_id', $itemIds)->update(['superseded_by_id' => null]);

        $version->delete();
    }
}
