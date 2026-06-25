<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppLineItem extends Model
{
    protected $fillable = [
        'app_header_id',
        'project_title',
        'implementing_unit',
        'description',
        'procurement_mode',
        'is_epa',
        'evaluation_criteria',
        'activity_start',
        'activity_end',
        'source_of_fund',
        'approved_budget',
        'strategy_tools',
        'remarks',
        'utilized_budget',
    ];

    protected $casts = [
        'is_epa' => 'boolean',
        'approved_budget' => 'decimal:2',
        'utilized_budget' => 'decimal:2',
    ];

    public function appHeader(): BelongsTo
    {
        return $this->belongsTo(AppHeader::class, 'app_header_id');
    }

    public function prItems(): HasMany
    {
        return $this->hasMany(PrItem::class, 'app_line_item_id');
    }
}
