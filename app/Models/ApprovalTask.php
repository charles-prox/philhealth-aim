<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalTask extends Model
{
    protected $fillable = [
        'target_employee_id',
        'document_type',
        'document_id',
        'tracking_number',
        'document_label',
        'originating_office',
        'viewed_at',
        'viewed_by_employee_id',
        'status',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function targetEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }

    public function viewedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'viewed_by_employee_id');
    }

    /**
     * Get the owning document model (polymorphic).
     */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }
}
