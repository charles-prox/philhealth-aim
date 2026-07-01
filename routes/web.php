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

Volt::route('admin/signatory-switchboard', 'admin.signatory-switchboard')
    ->middleware(['auth'])
    ->name('admin.signatory');

Volt::route('admin/unified-desk', 'admin.unified-approval-desk')
    ->middleware(['auth'])
    ->name('admin.unified-desk');

Volt::route('admin/document-workspace/{taskId}', 'admin.document-review-workspace')
    ->middleware(['auth'])
    ->name('admin.document-workspace');

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



Route::post('/notifications/read', function () {
    auth()->user()->unreadNotifications->markAsRead();
    return response()->json(['success' => true]);
})->middleware('auth')->name('notifications.read');

Route::get('/admin/procurement/file-stream/{attachmentId}', function ($attachmentId) {
    $attachment = \App\Models\ProcurementAttachment::findOrFail($attachmentId);
    
    // Explicit security check: Validate permission levels before displaying sensitive data
    if (!auth()->user()->employee || !auth()->user()->employee->isAllowedToSignOrViewDocs()) {
        abort(403, 'Unauthorized access to secure financial records.');
    }

    if (!\Illuminate\Support\Facades\Storage::disk('secure_procurement')->exists($attachment->file_path)) {
        abort(404, 'The physical asset was not found on the secure storage server.');
    }

    return \Illuminate\Support\Facades\Storage::disk('secure_procurement')->response($attachment->file_path, null, [
        'Content-Type' => $attachment->mime_type,
        'Content-Disposition' => 'inline; filename="' . $attachment->original_name . '"',
    ]);
})->name('admin.file-stream')->middleware(['auth']);

require __DIR__.'/auth.php';
