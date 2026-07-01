<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementAttachment extends Model
{
    protected $fillable = [
        'procurement_folder_id',
        'attachment_type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by_employee_id',
    ];

    public function folder()
    {
        return $this->belongsTo(ProcurementFolder::class, 'procurement_folder_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_employee_id');
    }
}
