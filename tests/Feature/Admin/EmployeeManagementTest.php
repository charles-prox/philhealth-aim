<?php

use App\Models\User;
use App\Models\Employee;
use App\Models\Office;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    // Seed standard test office
    Office::create(['name' => 'General Services Unit', 'acronym' => 'GSU', 'type' => 'UNIT']);
});

test('non-admin users cannot access employee management', function () {
    $user = User::factory()->create(); // standard user, no roles

    $this->actingAs($user);

    $response = $this->get('/admin/employees');
    $response->assertStatus(403);
});

test('admin users can access employee management', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $response = $this->get('/admin/employees');
    $response->assertStatus(200);
});

test('admin can create a new employee record', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    Volt::test('admin.employee-management')
        ->set('empIdNumber', 'TEST1234')
        ->set('empFullname', 'TEST EMPLOYEE')
        ->set('empDesignation', 'Test Staff')
        ->set('empSalaryGrade', 10)
        ->set('empOfficeDivision', 'GSU')
        ->set('empStatus', 'PERMANENT')
        ->call('saveEmployee')
        ->assertHasNoErrors();

    $this->assertTrue(Employee::where('id_number', 'TEST1234')->exists());
    $emp = Employee::where('id_number', 'TEST1234')->first();
    $this->assertEquals('TEST EMPLOYEE', $emp->fullname);
});

test('admin can edit an existing employee record', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $emp = Employee::create([
        'id_number' => 'EDIT1234',
        'fullname' => 'ORIGINAL NAME',
        'designation' => 'Original Staff',
        'salary_grade' => 5,
        'office_division' => 'GSU',
        'employment_status' => 'PERMANENT'
    ]);

    $this->actingAs($admin);

    Volt::test('admin.employee-management')
        ->call('openEditModal', $emp->id)
        ->set('empFullname', 'UPDATED NAME')
        ->call('saveEmployee')
        ->assertHasNoErrors();

    $this->assertEquals('UPDATED NAME', $emp->fresh()->fullname);
});

test('admin can bulk import employees from pasted text', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $bulkData = "BULK001\tBulk Employee A\tOfficer III\t11\tGSU\t\tRegular\n" .
                "BULK002\tBulk Employee B\tOfficer II\t9\tGSU\tProcurement\tCasual";

    Volt::test('admin.employee-management')
        ->set('bulkText', $bulkData)
        ->call('importBulk')
        ->assertHasNoErrors()
        ->assertSet('bulkResults.inserted', 2);

    $this->assertTrue(Employee::where('id_number', 'BULK001')->exists());
    $this->assertTrue(Employee::where('id_number', 'BULK002')->exists());
    
    $empB = Employee::where('id_number', 'BULK002')->first();
    $this->assertEquals('CASUAL', $empB->employment_status);
});
