<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'office_division', 'name');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(CobItemDistribution::class);
    }
}
