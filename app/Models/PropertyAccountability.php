<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;

class PropertyAccountability extends Model
{
    protected $fillable = [
        'doc_number',
        'doc_type', // ICS, PAR
        'end_user_id', // Permanent Staff
        'sub_user_id', // JO/Casual
        'location',
    ];

    public function endUser()
    {
        return $this->belongsTo(Employee::class, 'end_user_id');
    }

    public function subUser()
    {
        return $this->belongsTo(Employee::class, 'sub_user_id');
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->sub_user_id && !$model->end_user_id) {
                throw new Exception('A sub_user (JO/Casual) requires a primary end_user (Permanent) for legal liability.');
            }
        });
    }
}
