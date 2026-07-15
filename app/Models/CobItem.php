<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CobItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'version_id',
        'recom_amount',
        'encumbered_amount',
        'actual_spent',
        'current_balance',
        'ppa_code',
        'ppa_desc',
        'sub_ppa_code',
        'exp_desc',
        'is_ict',
        'account',
        'tier',
        'class',
        'gass',
        'transaction_id',
        'work_and_financial_plan_id',
        'office_id',
        'sector',
        'full_particulars',
        'unit',
        'recom_qty',
        'version_number',
        'is_active',
        'status',
        'superseded_by_id',
        'particulars1',
        'particulars2',
        'revision_remarks',
    ];

    protected $casts = [
        'is_ict' => 'boolean',
        'recom_amount' => 'decimal:2',
        'encumbered_amount' => 'decimal:2',
        'actual_spent' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'recom_qty' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CobVersion::class, 'version_id');
    }

    public function sourceTransactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class, 'source_item_id');
    }

    public function targetTransactions(): HasMany
    {
        return $this->hasMany(BudgetTransaction::class, 'target_item_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(CobItem::class, 'superseded_by_id');
    }

    public function supersedes(): HasOne
    {
        return $this->hasOne(CobItem::class, 'superseded_by_id');
    }

    /**
     * All upstream allocation rows for this COB line item.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(CobItemDistribution::class);
    }
}
