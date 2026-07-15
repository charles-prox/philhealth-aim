<?php

namespace Tests\Feature\Auth;

use Database\Seeders\OfficeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register');
});

test('new users can register', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OfficeSeeder::class);

    $component = Volt::test('pages.auth.register')
        ->set('name', 'Test User')
        ->set('username', '12345678')
        ->set('unit', 'MSD')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $component->assertHasNoErrors();

    $component->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
