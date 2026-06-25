<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'folder_id',
        'cob_item_id',
        'app_line_item_id',
        'item_description_override',
        'total_qty',
        'unit',
        'unit_cost',
        'estimated_unit_cost',
        'estimated_total_cost',
        'accountability_type',
    ];

    protected $casts = [
        'unit_cost'            => 'decimal:2',
        'estimated_unit_cost'  => 'decimal:2',
        'estimated_total_cost' => 'decimal:2',
    ];

    // -------------------------------------------------------------------------
    // Boot — Auto-compute costs & COA accountability threshold on creation
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $cobItem = $model->cobItem;
            $cobUnitCost = 0.0;
            if ($cobItem) {
                $recomQty = (float) $cobItem->recom_qty;
                $cobUnitCost = $recomQty > 0 ? ((float) $cobItem->recom_amount / $recomQty) : 0.0;
            }

            $cost = $model->estimated_unit_cost
                ?? $model->unit_cost
                ?? $cobUnitCost;

            $qty = $model->total_qty ?? 0;

            $model->estimated_unit_cost  = $cost;
            $model->estimated_total_cost = $qty * $cost;

            // COA / PhilHealth ₱50,000 Threshold Rule
            $model->accountability_type = $cost >= 50000.00 ? 'PAR' : 'ICS';
        });
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * @deprecated Use accountability_type column directly.
     * Kept for backward compatibility with existing blade views.
     */
    public function getAccountabilityTypeAttribute(): string
    {
        return $this->attributes['accountability_type']
            ?? ($this->unit_cost >= 50000 ? 'PAR' : 'ICS');
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProcurementFolder::class, 'folder_id');
    }

    public function cobItem(): BelongsTo
    {
        return $this->belongsTo(CobItem::class, 'cob_item_id');
    }

    public function appLineItem(): BelongsTo
    {
        return $this->belongsTo(AppLineItem::class, 'app_line_item_id');
    }

    /**
     * The distribution allocation rows that were compiled into this PR item.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(CobItemDistribution::class);
    }

    /** @deprecated Use distributions() */
    public function distributionPlans(): HasMany
    {
        return $this->hasMany(DistributionPlan::class, 'pr_item_id');
    }
}
