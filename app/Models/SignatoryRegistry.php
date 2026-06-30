<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatoryRegistry extends Model
{
    protected $table = 'signatory_registry';

    protected $fillable = [
        'position_code',
        'position_title',
        'office_id',
        'primary_employee_id',
        'oic_primary_employee_id',
        'oic_secondary_employee_id',
        'active_holder',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────────

    public function primaryEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'primary_employee_id');
    }

    public function oicPrimary(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'oic_primary_employee_id');
    }

    public function oicSecondary(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'oic_secondary_employee_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    // ─── Active Holder Resolution ───────────────────────────────────────────────

    /**
     * Resolves which employee currently holds the active signing pen for a
     * given position slot. Returns null if the primary slot has not been assigned yet.
     *
     * @param  string   $positionCode  e.g., 'GSU_HEAD', 'RVP'
     * @param  int|null $officeId      Required for office-scoped slots like 'DIVISION_CHIEF'
     * @return int|null                The resolved employee_id, or null if unassigned
     */
    public static function getActiveSignatoryFor(string $positionCode, ?int $officeId = null): ?int
    {
        $query = self::where('position_code', $positionCode);

        if ($officeId !== null) {
            $query->where('office_id', $officeId);
        }

        $registry = $query->first();

        if (!$registry || !$registry->primary_employee_id) {
            return null; // Slot exists but has not been configured yet
        }

        return match ($registry->active_holder) {
            'OIC_1' => $registry->oic_primary_employee_id   ?? $registry->primary_employee_id,
            'OIC_2' => $registry->oic_secondary_employee_id ?? $registry->primary_employee_id,
            default => $registry->primary_employee_id, // 'PRIMARY' is the safe default
        };
    }

    /**
     * Collects all employee IDs assigned to a set of position slots.
     * Used by the PR Wizard computed properties to build the authorized
     * signatory pool across primary + OIC columns, stripping nulls safely.
     *
     * @param  array      $positionCodes  e.g., ['GSU_HEAD', 'DIVISION_CHIEF']
     * @param  int|null   $officeId       Scope filter for office-bound slots
     * @return array<int>                 Unique, non-null employee IDs
     */
    public static function getAuthorizedEmployeeIds(array $positionCodes, ?int $officeId = null): array
    {
        $query = self::whereIn('position_code', $positionCodes);

        if ($officeId !== null) {
            // For mixed queries (e.g., GSU_HEAD + DIVISION_CHIEF), we want:
            //   - all rows for global slots (office_id IS NULL)
            //   - only the user's office row for scoped slots (office_id = $officeId)
            $query->where(function ($q) use ($officeId) {
                $q->whereNull('office_id')
                  ->orWhere('office_id', $officeId);
            });
        }

        $rows = $query->get(['primary_employee_id', 'oic_primary_employee_id', 'oic_secondary_employee_id']);

        return collect($rows)
            ->flatMap(fn ($row) => [
                $row->primary_employee_id,
                $row->oic_primary_employee_id,
                $row->oic_secondary_employee_id,
            ])
            ->filter()   // strips null OIC slots — prevents WhereIn null-injection
            ->unique()
            ->values()
            ->toArray();
    }
}
