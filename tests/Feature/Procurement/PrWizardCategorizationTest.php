<?php

use App\Models\AppHeader;
use App\Models\AppLineItem;
use App\Models\BudgetYear;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 0. Seed an Office
    $office = new Office;
    $office->id = 1;
    $office->name = 'General Services Unit';
    $office->acronym = 'GSU';
    $office->type = 'DIVISION';
    $office->save();

    // 1. Seed an Employee for Auth/Signatory check
    $emp = new Employee;
    $emp->id = 1;
    $emp->fullname = 'SYSTEM USER';
    $emp->designation = 'Staff';
    $emp->office_division = 'GSU';
    $emp->employment_status = 'PERMANENT';
    $emp->save();

    // 2. Create budget year and approved APP Header so APP Gate is cleared
    $year = now()->year;
    BudgetYear::create([
        'fiscal_year' => $year,
        'status' => 'OPEN',
    ]);
    $header = AppHeader::create([
        'fiscal_year' => $year,
        'is_approved' => true,
        'uploaded_by_id' => $emp->id,
    ]);

    // 3. Create an APP Line Item to fund the PR
    $item = new AppLineItem;
    $item->id = 1;
    $item->app_header_id = $header->id;
    $item->approved_budget = 100000;
    $item->utilized_budget = 0;
    $item->description = 'Office Stationery';
    $item->project_title = 'Standard Supplies';
    $item->procurement_mode = 'Shopping';
    $item->implementing_unit = 'GSU';
    $item->activity_start = 'Q1';
    $item->activity_end = 'Q4';
    $item->source_of_fund = 'COB';
    $item->is_epa = false;
    $item->save();

    // Authenticate a user
    $user = User::factory()->create([
        'employee_id' => $emp->id,
        'office_id' => 1,
    ]);
    $this->actingAs($user);
});

it('blocks advancing when category is missing', function () {
    Volt::test('procurement.end-user-portal')
        ->set('currentStep', 2)
        ->set('form.procurementCategory', '')
        ->call('nextStep')
        ->assertHasErrors(['form.procurementCategory' => 'required'])
        ->assertSet('currentStep', 2);
});

it('blocks advancing when tied to event but date is missing', function () {
    Volt::test('procurement.end-user-portal')
        ->set('currentStep', 2)
        ->set('form.procurementCategory', 'OFFICE_SUPPLIES')
        ->set('form.isTiedToEvent', true)
        ->set('form.eventDate', null)
        ->call('nextStep')
        ->assertHasErrors(['form.eventDate' => 'required_if'])
        ->assertSet('currentStep', 2);
});

it('blocks advancing when event date is in the past or is today', function () {
    Volt::test('procurement.end-user-portal')
        ->set('currentStep', 2)
        ->set('form.procurementCategory', 'OFFICE_SUPPLIES')
        ->set('form.isTiedToEvent', true)
        ->set('form.eventDate', now()->subDay()->format('Y-m-d'))
        ->call('nextStep')
        ->assertHasErrors(['form.eventDate' => 'after'])
        ->assertSet('currentStep', 2);

    Volt::test('procurement.end-user-portal')
        ->set('currentStep', 2)
        ->set('form.procurementCategory', 'OFFICE_SUPPLIES')
        ->set('form.isTiedToEvent', true)
        ->set('form.eventDate', now()->format('Y-m-d'))
        ->call('nextStep')
        ->assertHasErrors(['form.eventDate' => 'after'])
        ->assertSet('currentStep', 2);
});

it('advances to step 2 when selection is valid from step 1', function () {
    Volt::test('procurement.end-user-portal')
        ->set('selectedAppLineId', 1)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('currentStep', 2);
});
