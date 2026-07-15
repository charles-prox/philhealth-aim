<?php

namespace App\Services;

use App\Models\ProcurementCategory;
use Illuminate\Support\Facades\DB;
use Exception;

class CategoryManagementService
{
    public function create(array $data): ProcurementCategory
    {
        return ProcurementCategory::create($data);
    }

    public function update(ProcurementCategory $category, array $data): void
    {
        $category->update($data);
    }

    /**
     * Safely delete a category with strict relational protection.
     * Throws an exception if the category is currently tied to transactional records.
     */
    public function delete(ProcurementCategory $category): void
    {
        // 🛡️ COA Safety Check: Check if this category name is linked to active folders or items
        $isLinkedToFolders = DB::table('procurement_folders')
            ->where('procurement_category', $category->name)
            ->exists();

        if ($isLinkedToFolders) {
            throw new Exception("Deletion Blocked: This category is currently linked to historical or active Purchase Requests.");
        }

        $category->delete();
    }
}
