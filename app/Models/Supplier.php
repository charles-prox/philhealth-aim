<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'philgeps_number',
        'address',
        'contact_person',
        'contact_number',
    ];

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'supplier_id');
    }
}
