<?php

namespace App\Services;

use App\Models\Office;
use App\Models\SignatoryRegistry;

class SignatoryService
{
    /**
     * Resolves the section head signatory for a specific office.
     */
    public static function getSectionHeadForOffice(?Office $office): ?int
    {
        if (!$office) {
            return null;
        }

        // Exempt Planning (PRU) and Public affairs (PAU) units, mapping them to their own UNIT_HEAD
        if (in_array($office->acronym, ['PAU', 'PRU'])) {
            return SignatoryRegistry::getActiveSignatoryFor('UNIT_HEAD', $office->id);
        }

        $section = $office->section;
        if ($section) {
            return SignatoryRegistry::getActiveSignatoryFor('SECTION_HEAD', $section->id);
        }

        return null;
    }

    /**
     * Resolves the recommended signatory based on total cost and requesting office.
     */
    public static function getRecommendedSignatory(float $totalCost, ?Office $office): ?int
    {
        if ($totalCost >= 200000.00) {
            return self::getSectionHeadForOffice($office);
        }

        $gsuOffice = Office::where('acronym', 'GSU')->first();
        if ($gsuOffice) {
            return SignatoryRegistry::getActiveSignatoryFor('UNIT_HEAD', $gsuOffice->id);
        }

        return null;
    }

    /**
     * Resolves the approved signatory based on total cost threshold.
     */
    public static function getApprovedSignatory(float $totalCost): ?int
    {
        if ($totalCost >= 200000.00) {
            return SignatoryRegistry::getActiveSignatoryFor('RVP');
        }

        return SignatoryRegistry::getActiveSignatoryFor('MSD_HEAD');
    }

    /**
     * Returns an array of valid recommender employee IDs for the given cost and office.
     */
    public static function getValidRecommenderIds(float $totalCost, ?Office $office): array
    {
        $ids = [];
        if ($totalCost >= 200000.00) {
            $sectionHead = self::getSectionHeadForOffice($office);
            if ($sectionHead) {
                $ids[] = $sectionHead;
            }
        } else {
            $gsuOffice = Office::where('acronym', 'GSU')->first();
            if ($gsuOffice) {
                $gsuSigner = SignatoryRegistry::getActiveSignatoryFor('UNIT_HEAD', $gsuOffice->id);
                if ($gsuSigner) {
                    $ids[] = $gsuSigner;
                }
            }
        }

        return array_filter(array_unique($ids));
    }

    /**
     * Returns an array of valid approver employee IDs for the given cost.
     */
    public static function getValidApproverIds(float $totalCost): array
    {
        $ids = [];
        if ($totalCost >= 200000.00) {
            $rvpSigner = SignatoryRegistry::getActiveSignatoryFor('RVP');
            if ($rvpSigner) {
                $ids[] = $rvpSigner;
            }
        } else {
            $msdSigner = SignatoryRegistry::getActiveSignatoryFor('MSD_HEAD');
            if ($msdSigner) {
                $ids[] = $msdSigner;
            }
        }

        return array_filter(array_unique($ids));
    }

    /**
     * Set active holder for a signatory slot.
     */
    public function updateActiveHolder(int $registryId, string $holder): void
    {
        $allowed = ['PRIMARY', 'OIC_1', 'OIC_2'];
        if (!in_array($holder, $allowed)) {
            throw new \InvalidArgumentException('Invalid holder value.');
        }

        $row = SignatoryRegistry::findOrFail($registryId);

        // Guard: don't allow switching to OIC_1/OIC_2 if that slot has no employee
        if ($holder === 'OIC_1' && !$row->oic_primary_employee_id) {
            throw new \RuntimeException("Cannot activate OIC 1: No employee is assigned to that slot for \"{$row->position_title}\". Edit the row to assign an OIC first.");
        }
        if ($holder === 'OIC_2' && !$row->oic_secondary_employee_id) {
            throw new \RuntimeException("Cannot activate OIC 2: No employee is assigned to that slot for \"{$row->position_title}\". Edit the row to assign an OIC first.");
        }

        $row->update(['active_holder' => $holder]);
    }

    /**
     * Update slot configuration details.
     */
    public function saveSlot(int $registryId, array $data): void
    {
        $row = SignatoryRegistry::findOrFail($registryId);

        // Guard: prevent activating OIC slots if their employee is not assigned
        if ($data['active_holder'] === 'OIC_1' && !$data['oic_primary_employee_id']) {
            throw new \RuntimeException('Cannot set active holder to OIC 1 without assigning an OIC 1 employee.');
        }
        if ($data['active_holder'] === 'OIC_2' && !$data['oic_secondary_employee_id']) {
            throw new \RuntimeException('Cannot set active holder to OIC 2 without assigning an OIC 2 employee.');
        }

        $row->update([
            'position_title' => $data['position_title'],
            'primary_employee_id' => $data['primary_employee_id'],
            'oic_primary_employee_id' => $data['oic_primary_employee_id'] ?: null,
            'oic_secondary_employee_id' => $data['oic_secondary_employee_id'] ?: null,
            'active_holder' => $data['active_holder'],
        ]);
    }
}
