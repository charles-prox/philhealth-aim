<?php

use App\Models\Employee;
use App\Models\ProcurementFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $emp = new Employee();
    $emp->id = 1;
    $emp->fullname = 'SYSTEM AGENT';
    $emp->designation = 'System';
    $emp->office_division = 'GSU';
    $emp->employment_status = 'PERMANENT';
    $emp->save();
});

it('generates next PR number in the official YYMMPR-XXX format', function () {
    $year2 = substr(now()->format('y'), -2);
    $month2 = now()->format('m');
    
    $expectedFirst = "{$year2}{$month2}PR-001";
    
    expect(ProcurementFolder::generateNextPrNumber())->toBe($expectedFirst);
});

it('increments the sequence correctly across months of the same year', function () {
    $year2 = substr(now()->format('y'), -2);
    $month2 = now()->format('m');
    
    ProcurementFolder::create([
        'pr_number' => "{$year2}01PR-001",
        'tracking_number' => 'TRK-2026-00001',
        'office_division' => 'GSU',
        'status' => 'DRAFT',
    ]);
    
    ProcurementFolder::create([
        'pr_number' => "{$year2}02PR-002",
        'tracking_number' => 'TRK-2026-00002',
        'office_division' => 'GSU',
        'status' => 'DRAFT',
    ]);
    
    $expectedNext = "{$year2}{$month2}PR-003";
    
    expect(ProcurementFolder::generateNextPrNumber())->toBe($expectedNext);
});

it('resets the sequence to 001 for a new year', function () {
    $year2 = substr(now()->format('y'), -2);
    $month2 = now()->format('m');
    
    // Create a folder from a previous year (e.g. if current is 26, last year was 25)
    $lastYearVal = (int) $year2 - 1;
    $lastYear2 = str_pad($lastYearVal >= 0 ? $lastYearVal : 99, 2, '0', STR_PAD_LEFT);
    
    ProcurementFolder::create([
        'pr_number' => "{$lastYear2}12PR-045",
        'tracking_number' => 'TRK-2025-00045',
        'office_division' => 'GSU',
        'status' => 'DRAFT',
    ]);
    
    $expected = "{$year2}{$month2}PR-001";
    
    expect(ProcurementFolder::generateNextPrNumber())->toBe($expected);
});
