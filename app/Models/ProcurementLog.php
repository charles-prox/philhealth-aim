<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementLog extends Model
{
    public $timestamps = false; // only use created_at

    protected $fillable = [
        'procurement_folder_id',
        'action',
        'actor_id',
        'remarks',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function folder()
    {
        return $this->belongsTo(ProcurementFolder::class, 'procurement_folder_id');
    }

    public function actor()
    {
        return $this->belongsTo(Employee::class, 'actor_id');
    }
}
