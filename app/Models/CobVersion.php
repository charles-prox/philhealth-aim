<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'budget_year_id',
        'version_name',
        'is_active',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function budgetYear(): BelongsTo
    {
        return $this->belongsTo(BudgetYear::class);
    }

    public function cobItems(): HasMany
    {
        return $this->hasMany(CobItem::class, 'version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getItemCountAttribute(): int
    {
        return $this->cobItems()->count();
    }

    public function getTotalAllocationAttribute(): float
    {
        return (float) $this->cobItems()->sum('recom_amount');
    }

    public function statusBadge(): array
    {
        if ($this->status === 'APPROVED' || $this->is_active) {
            return [
                'label' => 'Approved',
                'classes' => 'bg-[#d5e3ff] text-[#001b3c]'
            ];
        }
        
        if ($this->status === 'SUPERSEDED') {
            return [
                'label' => 'Superseded',
                'classes' => 'bg-[#ffdbca] text-[#723610]'
            ];
        }

        return [
            'label' => 'Draft',
            'classes' => 'bg-[#eeedf2] text-[#43474f]'
        ];
    }
}
