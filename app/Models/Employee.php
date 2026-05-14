<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'fullname',
        'designation',
        'salary_grade',
        'office_division',
        'sub_office',
        'employment_status',
    ];
}
