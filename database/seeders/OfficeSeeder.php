<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Seeds the offices table from distinct office_division values
     * already present in the employees table. Safe to run after EmployeeSeeder.
     */
    public function run(): void
    {
        $divisions = Employee::query()
            ->select('office_division')
            ->distinct()
            ->orderBy('office_division')
            ->pluck('office_division');

        foreach ($divisions as $division) {
            Office::firstOrCreate(
                ['name' => $division],
                ['code' => null]
            );
        }
    }
}
