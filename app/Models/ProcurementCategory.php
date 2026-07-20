<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementCategory extends Model
{
    protected $fillable = [
        'name',
        'uacs_code',
        'budget_class',
        'tracking_type',
        'audit_requirement',
    ];

    public function scopeAlphabetical($query)
    {
        return $query->orderBy('name', 'asc');
    }
}
