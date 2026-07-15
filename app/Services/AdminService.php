<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Office;
use App\Models\SignatoryRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminService
{
    /**
     * Save or update an employee record.
     */
    public function saveEmployee(array $data, ?int $editingEmployeeId = null): Employee
    {
        return Employee::updateOrCreate(
            ['id' => $editingEmployeeId],
            [
                'id_number' => $data['id_number'],
                'fullname' => $data['fullname'],
                'designation' => $data['designation'],
                'salary_grade' => $data['salary_grade'],
                'office_division' => $data['office_division'],
                'sub_office' => $data['sub_office'] ?: null,
                'employment_status' => $data['employment_status'],
            ]
        );
    }

    /**
     * Delete an employee record with dependency validation.
     */
    public function deleteEmployee(int $employeeId): void
    {
        $emp = Employee::findOrFail($employeeId);

        // Safety checks to prevent orphan references in Signatory Matrix
        $isSignatory = SignatoryRegistry::where('primary_employee_id', $emp->id)
            ->orWhere('oic_primary_employee_id', $emp->id)
            ->orWhere('oic_secondary_employee_id', $emp->id)
            ->exists();

        if ($isSignatory) {
            throw new \RuntimeException("Cannot delete employee '{$emp->fullname}' because they are assigned to a slot in the Signatory Matrix.");
        }

        $emp->delete();
    }

    /**
     * Import employees in bulk from raw copy-pasted text.
     */
    public function importBulkEmployees(string $bulkText): array
    {
        $lines = explode("\n", $bulkText);
        $inserted = 0;
        $skipped = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Split by Tab (excel copy-paste) or Comma
            $cols = str_contains($line, "\t") ? explode("\t", $line) : explode(',', $line);
            $cols = array_map('trim', $cols);

            if (count($cols) < 5) {
                $skipped[] = 'Row ' . ($index + 1) . ': Invalid column count. Must have at least 5 columns (ID Number, Fullname, Designation, Salary Grade, Office Acronym).';

                continue;
            }

            $idNumber = $cols[0];
            $fullname = $cols[1];
            $designation = $cols[2];
            $salaryGrade = is_numeric($cols[3]) ? intval($cols[3]) : 1;
            $officeAcronym = $cols[4];
            $subOffice = $cols[5] ?? '';
            $rawStatus = strtoupper($cols[6] ?? 'PERMANENT');

            // Map status
            $status = 'PERMANENT';
            if (str_contains($rawStatus, 'CASUAL')) {
                $status = 'CASUAL';
            } elseif (str_contains($rawStatus, 'JO') || str_contains($rawStatus, 'JOB') || str_contains($rawStatus, 'CONTRACT')) {
                $status = 'JO';
            }

            // Verify office
            $office = Office::where('acronym', $officeAcronym)->first();
            if (!$office) {
                $skipped[] = 'Row ' . ($index + 1) . ": Office acronym '{$officeAcronym}' does not exist.";

                continue;
            }

            if (empty($idNumber) || empty($fullname) || empty($designation)) {
                $skipped[] = 'Row ' . ($index + 1) . ': ID, Name, or Designation cannot be empty.';

                continue;
            }

            try {
                Employee::updateOrCreate(
                    ['id_number' => $idNumber],
                    [
                        'fullname' => $fullname,
                        'designation' => $designation,
                        'salary_grade' => $salaryGrade,
                        'office_division' => $officeAcronym,
                        'sub_office' => $subOffice ?: null,
                        'employment_status' => $status,
                    ]
                );
                $inserted++;
            } catch (\Exception $e) {
                $skipped[] = 'Row ' . ($index + 1) . ': Error: ' . $e->getMessage();
            }
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Create or update a system user account.
     */
    public function saveUser(array $data, ?int $editingUserId = null): User
    {
        if ($data['employee_id']) {
            // Guard: prevent same employee being linked to two users
            $conflict = User::where('employee_id', $data['employee_id'])
                ->when($editingUserId, fn ($q) => $q->where('id', '!=', $editingUserId))
                ->first();

            if ($conflict) {
                throw new \RuntimeException("That employee is already linked to user '{$conflict->name}'.");
            }
        }

        return DB::transaction(function () use ($data, $editingUserId) {
            if ($editingUserId) {
                $user = User::findOrFail($editingUserId);
                $user->name = $data['name'];
                $user->username = $data['username'];
                $user->email = $data['email'];

                if (!empty($data['password'])) {
                    $user->password = Hash::make($data['password']);
                }

                $user->employee_id = $data['employee_id'];
                $user->office_id = $data['office_id'];
                $user->save();

                $user->syncRoles($data['role']);
            } else {
                $user = User::create([
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'employee_id' => $data['employee_id'],
                    'office_id' => $data['office_id'],
                ]);

                $user->assignRole($data['role']);
            }

            return $user;
        });
    }

    /**
     * Toggles 2-factor authentication flag for a user.
     */
    public function toggle2FA(int $userId): bool
    {
        $user = User::findOrFail($userId);
        $user->two_factor_enabled = !$user->two_factor_enabled;
        $user->save();

        return $user->two_factor_enabled;
    }
}
