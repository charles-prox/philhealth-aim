<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetYear extends Model
{
    use HasUuids;

    protected $fillable = [
        'fiscal_year',
        'status',
        'total_allocation',
    ];

    protected $casts = [
        'total_allocation' => 'decimal:2',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(CobVersion::class);
    }

    public function activeVersion(): ?CobVersion
    {
        return $this->versions()->where('is_active', true)->first();
    }
}
