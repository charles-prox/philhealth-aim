<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementFolder extends Model
{
    protected $fillable = [
        'pr_number',
        'rfq_control_no',
        'status',
        'google_sheet_id',
        'google_form_id',
    ];
}
