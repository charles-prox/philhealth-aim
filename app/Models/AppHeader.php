<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppHeader extends Model
{
    protected $fillable = [
        'fiscal_year',
        'is_approved',
        'csv_file_path',
        'scanned_pdf_path',
        'uploaded_by_id',
        'approved_at',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function lineItems(): HasMany
    {
        return $this->hasMany(AppLineItem::class, 'app_header_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_id');
    }
}
