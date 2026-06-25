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

Route::get('procurement', function () {
    $user = auth()->user();
    if ($user->hasAnyRole(['Admin', 'Procurement Officer'])) {
        return redirect()->route('procurement.admin');
    }
    if ($user->hasRole('Office Head')) {
        return redirect()->route('procurement.office');
    }
    return redirect()->route('procurement.portal');
})->middleware(['auth'])->name('procurement');

Volt::route('procurement/admin', 'procurement.gsu-master-desk')
    ->middleware(['auth'])
    ->name('procurement.admin');

Volt::route('procurement/office', 'procurement.office-dashboard')
    ->middleware(['auth'])
    ->name('procurement.office');

Volt::route('procurement/portal', 'procurement.custodian-portal')
    ->middleware(['auth'])
    ->name('procurement.portal');

Route::get('procurement/pr/{folder}/pdf', [\App\Http\Controllers\ProcurementController::class, 'viewPrPdf'])
    ->middleware(['auth'])
    ->name('procurement.pr.pdf');

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

Volt::route('procurement/app/{header}/items', 'procurement.app-items')
    ->middleware(['auth'])
    ->name('procurement.app.items');

Volt::route('cob/realignment', 'cob.realignment-wizard')
    ->middleware(['auth'])
    ->name('cob.realignment');

Volt::route('cob/distribution', 'cob.distribution-matrix')
    ->middleware(['auth'])
    ->name('cob.distribution');



require __DIR__.'/auth.php';
