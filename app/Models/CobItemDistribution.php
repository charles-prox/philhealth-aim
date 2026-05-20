<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CobItemDistribution extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'cob_item_id',
        'office_id',
        'employee_id',
        'sub_employee_id',
        'allocated_quantity',
        'procured_quantity',
        'pr_item_id',
    ];

    protected $casts = [
        'allocated_quantity' => 'integer',
        'procured_quantity'  => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * True when this allocation has been compiled into a PR.
     * A locked row cannot be edited or soft-deleted.
     */
    public function getIsLockedAttribute(): bool
    {
        return !is_null($this->pr_item_id);
    }

    /**
     * Quantity still available to be procured on this allocation.
     */
    public function getRemainingQtyAttribute(): int
    {
        return $this->allocated_quantity - $this->procured_quantity;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function cobItem(): BelongsTo
    {
        return $this->belongsTo(CobItem::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * The designated end-user employee. Nullable for office-pooled stock.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * The actual casual/JO user of the item. Nullable.
     */
    public function subEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sub_employee_id');
    }

    /**
     * The PR item this distribution is locked into. Null when still free.
     */
    public function prItem(): BelongsTo
    {
        return $this->belongsTo(PrItem::class);
    }
}
