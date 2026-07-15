<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    protected $fillable = [
        'name',
        'type',
        'acronym',
        'parent_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'office_division', 'acronym');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(CobItemDistribution::class);
    }

    // ─── Hierarchy Lineage Resolvers ──────────────────────────────────────────

    /**
     * Resolves the governing DIVISION office (e.g., ORVP, MSD, HCDMD, FOD) by walking up.
     */
    public function getDivisionAttribute(): ?Office
    {
        $current = $this;
        while ($current) {
            if ($current->type === 'DIVISION') {
                return $current;
            }
            $current = $current->parent;
        }

        return null;
    }

    /**
     * Resolves the associated SECTION office (e.g., ASS, FMS, BAS, AQAS, LHIOs) by walking up.
     */
    public function getSectionAttribute(): ?Office
    {
        $current = $this;
        while ($current) {
            if ($current->type === 'SECTION') {
                return $current;
            }
            $current = $current->parent;
        }

        return null;
    }

    /**
     * Returns the office itself if it represents a UNIT (e.g., GSU, HRU, PBCs).
     */
    public function getUnitAttribute(): ?Office
    {
        return $this->type === 'UNIT' ? $this : null;
    }
}
