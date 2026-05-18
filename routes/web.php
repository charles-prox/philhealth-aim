<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

use App\Models\User;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    if (User::count() === 0) {
        return redirect()->route('register');
    }

    return redirect()->route('login');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Volt::route('procurement', 'procurement')
    ->middleware(['auth'])
    ->name('procurement');

Route::view('inventory', 'inventory')
    ->middleware(['auth'])
    ->name('inventory');

Route::view('accountability', 'accountability')
    ->middleware(['auth'])
    ->name('accountability');

Route::view('repairs', 'repairs')
    ->middleware(['auth'])
    ->name('repairs');

Route::view('reports', 'reports')
    ->middleware(['auth'])
    ->name('reports');

Volt::route('admin/users', 'admin.user-management')
    ->middleware(['auth'])
    ->name('admin.users');

// COB Management Module
Volt::route('cob/registry', 'cob.cob-registry')
    ->middleware(['auth'])
    ->name('cob.registry');

Volt::route('cob/version/{version}/items', 'cob.version-items')
    ->middleware(['auth'])
    ->name('cob.items');

Volt::route('cob/realignment', 'cob.realignment-wizard')
    ->middleware(['auth'])
    ->name('cob.realignment');

require __DIR__.'/auth.php';
