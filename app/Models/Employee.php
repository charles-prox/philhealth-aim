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

    public function user()
    {
        return $this->hasOne(\App\Models\User::class, 'employee_id');
    }

    public function isAllowedToSignOrViewDocs(): bool
    {
        // 1. Is this employee a signatory in the SignatoryRegistry?
        if (\App\Models\SignatoryRegistry::isEmployeeSignatory($this->id)) {
            return true;
        }

        // 2. Is this employee part of the GSU / procurement unit or Admin?
        $user = $this->user;
        if ($user) {
            if ($user->hasRole('admin') || $user->hasRole('gsu') || $user->hasRole('gsu-member') || $this->designation === 'GSU Triage Officer') {
                return true;
            }
        }

        // 3. Or does the employee own any pending tasks?
        if (\App\Models\ApprovalTask::where('target_employee_id', $this->id)->exists()) {
            return true;
        }

        // 4. Or is the employee a creator of a procurement folder?
        if (\App\Models\ProcurementFolder::where('created_by_id', $user?->id)->orWhere('requested_by_id', $this->id)->exists()) {
            return true;
        }

        return false;
    }
}
