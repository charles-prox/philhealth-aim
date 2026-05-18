<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'version_id',
        'source_item_id',
        'target_item_id',
        'amount',
        'reference_memo',
        'memo_attachment',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(CobItem::class, 'source_item_id');
    }

    public function targetItem(): BelongsTo
    {
        return $this->belongsTo(CobItem::class, 'target_item_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CobVersion::class, 'version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
