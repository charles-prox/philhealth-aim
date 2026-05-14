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

Route::view('procurement', 'procurement')
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
Volt::route('cob/kickoff', 'cob.annual-kickoff')
    ->middleware(['auth'])
    ->name('cob.kickoff');

Volt::route('cob/version/{version}/items', 'cob.version-items')
    ->middleware(['auth'])
    ->name('cob.items');

require __DIR__.'/auth.php';
