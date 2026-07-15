<?php

namespace App\Models;

use App\Notifications\DocumentReRoutedNotification;
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
     * @param  string  $positionCode  e.g., 'GSU_HEAD', 'RVP'
     * @param  int|null  $officeId  Required for office-scoped slots like 'DIVISION_CHIEF'
     * @return int|null The resolved employee_id, or null if unassigned
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
            'OIC_1' => $registry->oic_primary_employee_id ?? $registry->primary_employee_id,
            'OIC_2' => $registry->oic_secondary_employee_id ?? $registry->primary_employee_id,
            default => $registry->primary_employee_id, // 'PRIMARY' is the safe default
        };
    }

    /**
     * Collects all employee IDs assigned to a set of position slots.
     * Used by the PR Wizard computed properties to build the authorized
     * signatory pool across primary + OIC columns, stripping nulls safely.
     *
     * @param  array  $positionCodes  e.g., ['GSU_HEAD', 'DIVISION_CHIEF']
     * @param  int|null  $officeId  Scope filter for office-bound slots
     * @return array<int> Unique, non-null employee IDs
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

    /**
     * Resolves the active signatory employee ID for this slot.
     */
    public function getActiveSignatoryId(): ?int
    {
        if (!$this->primary_employee_id) {
            return null;
        }

        return match ($this->active_holder) {
            'OIC_1' => $this->oic_primary_employee_id ?? $this->primary_employee_id,
            'OIC_2' => $this->oic_secondary_employee_id ?? $this->primary_employee_id,
            default => $this->primary_employee_id,
        };
    }

    public static function isEmployeeSignatory(?int $employeeId): bool
    {
        if (!$employeeId) {
            return false;
        }

        return self::where('primary_employee_id', $employeeId)
            ->orWhere('oic_primary_employee_id', $employeeId)
            ->orWhere('oic_secondary_employee_id', $employeeId)
            ->exists();
    }

    protected static function booted()
    {
        static::updating(function ($registry) {
            if ($registry->isDirty('active_holder') || $registry->isDirty('primary_employee_id') || $registry->isDirty('oic_primary_employee_id') || $registry->isDirty('oic_secondary_employee_id')) {
                // Retrieve the old state from DB before dirty attributes are applied
                $oldSignatoryId = null;
                $original = $registry->getOriginal();
                if (!empty($original['primary_employee_id'])) {
                    $oldActiveHolder = $original['active_holder'] ?? 'PRIMARY';
                    $oldSignatoryId = match ($oldActiveHolder) {
                        'OIC_1' => $original['oic_primary_employee_id'] ?? $original['primary_employee_id'],
                        'OIC_2' => $original['oic_secondary_employee_id'] ?? $original['primary_employee_id'],
                        default => $original['primary_employee_id'],
                    };
                }

                // Resolve the new signatory ID
                $newActiveHolder = $registry->active_holder;
                $newSignatoryId = match ($newActiveHolder) {
                    'OIC_1' => $registry->oic_primary_employee_id ?? $registry->primary_employee_id,
                    'OIC_2' => $registry->oic_secondary_employee_id ?? $registry->primary_employee_id,
                    default => $registry->primary_employee_id,
                };

                if ($oldSignatoryId && $newSignatoryId && $oldSignatoryId !== $newSignatoryId) {
                    $registry->pendingSignatoryMigration = [
                        'old' => $oldSignatoryId,
                        'new' => $newSignatoryId,
                    ];
                }
            }
        });

        static::saved(function ($registry) {
            if (isset($registry->pendingSignatoryMigration)) {
                $oldSignatoryId = $registry->pendingSignatoryMigration['old'];
                $newSignatoryId = $registry->pendingSignatoryMigration['new'];
                unset($registry->pendingSignatoryMigration);

                \DB::transaction(function () use ($oldSignatoryId, $newSignatoryId) {
                    $inFlightFolders = ProcurementFolder::where('current_signatory_id', $oldSignatoryId)
                        ->where('status', 'ROUTING')
                        ->get();

                    foreach ($inFlightFolders as $folder) {
                        $folder->update(['current_signatory_id' => $newSignatoryId]);

                        ProcurementLog::create([
                            'procurement_folder_id' => $folder->id,
                            'action' => 'SYSTEM_REROUTE',
                            'actor_id' => auth()->user()?->employee_id,
                            'remarks' => 'Document automatically transferred from previous officer to newly active signatory due to Admin switchboard matrix adjustment.',
                            'created_at' => now(),
                        ]);

                        // Send dynamic in-app notification to the PR creator user
                        if ($folder->created_by_id) {
                            $creator = User::find($folder->created_by_id);
                            if ($creator) {
                                $newSignatory = Employee::find($newSignatoryId);
                                $newSignatoryName = $newSignatory ? $newSignatory->fullname : 'OIC/New Signatory';
                                // We suffix (OIC) if it is OIC_1 or OIC_2
                                if ($registry->active_holder !== 'PRIMARY') {
                                    $newSignatoryName .= ' (OIC)';
                                }
                                $creator->notify(new DocumentReRoutedNotification($folder, $newSignatoryName));
                            }
                        }
                    }
                });
            }
        });
    }
}
