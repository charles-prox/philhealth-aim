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
        'source_item_id',
        'target_item_id',
        'amount',
        'reference_memo',
        'memo_attachment',
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
}
