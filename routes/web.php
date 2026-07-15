<?php

use App\Http\Controllers\ProcurementController;
use App\Models\Employee;
use App\Models\ProcurementAttachment;
use App\Models\ProcurementFolder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

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

    // Office Heads no longer use the separate office dashboard;
    // they land on the unified Procurement Portal in read-only mode.
    // All other roles (Office Head, Document Custodian, etc.) → unified portal.
    return redirect()->route('procurement.portal');
})->middleware(['auth'])->name('procurement');

Volt::route('procurement/admin', 'procurement.gsu-master-desk')
    ->middleware(['auth'])
    ->name('procurement.admin');

Volt::route('procurement/portal', 'procurement.custodian-portal')
    ->middleware(['auth'])
    ->name('procurement.portal');

Volt::route('procurement/gsu-review/{folderId}', 'procurement.gsu-inbox-review')
    ->middleware(['auth'])
    ->name('procurement.gsu.review');

Volt::route('procurement/review/{folderId}', 'procurement.custodian-review')
    ->middleware(['auth'])
    ->name('procurement.review');

Route::get('procurement/pr/{folder}/pdf', [ProcurementController::class, 'viewPrPdf'])
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

Volt::route('admin/employees', 'admin.employee-management')
    ->middleware(['auth'])
    ->name('admin.employees');

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

Route::get('/api/search', function () {
    $query = strtolower(trim(request('q', '')));
    if (strlen($query) < 2) {
        return response()->json(['folders' => [], 'employees' => []]);
    }

    $user = auth()->user();
    $redirectBase = match (true) {
        $user->hasAnyRole(['Admin', 'Procurement Officer']) => route('procurement.admin'),
        default => route('procurement.portal'),
    };

    // Security code lookup
    $cleanSearch = strtoupper(str_replace('TRK-', '', trim($query)));
    $matchingId = null;
    if (ctype_xdigit($cleanSearch) && strlen($cleanSearch) === 12) {
        $matchingId = ProcurementFolder::select('id')
            ->get()
            ->first(fn ($f) => strtoupper(substr(md5($f->id), 0, 12)) === $cleanSearch)
            ?->id;
    }

    $foldersQuery = ProcurementFolder::query()
        ->where(function ($q) use ($query, $matchingId) {
            $q->where(DB::raw('LOWER(pr_number)'), 'like', "%{$query}%")
                ->orWhere(DB::raw('LOWER(tracking_number)'), 'like', "%{$query}%")
                ->orWhere(DB::raw('LOWER(overall_purpose)'), 'like', "%{$query}%")
                ->orWhere(DB::raw('LOWER(requesting_unit)'), 'like', "%{$query}%");
            if ($matchingId) {
                $q->orWhere('id', $matchingId);
            }
        })
        ->limit(5)
        ->get(['id', 'pr_number', 'tracking_number', 'overall_purpose', 'requesting_unit', 'status']);

    $folders = $foldersQuery->map(fn ($f) => [
        'id' => $f->id,
        'label' => $f->pr_number ?: $f->tracking_number,
        'purpose' => $f->overall_purpose,
        'unit' => $f->requesting_unit,
        'status' => $f->status,
        'status_label' => $f->status_label ?? $f->status,
        'security_code' => 'TRK-' . strtoupper(substr(md5($f->id), 0, 12)),
        'url' => $redirectBase . '?search=' . urlencode($f->pr_number ?: $f->tracking_number),
    ]);

    $employees = Employee::where(
        DB::raw('LOWER(fullname)'), 'like', "%{$query}%"
    )
        ->orWhere(DB::raw('LOWER(designation)'), 'like', "%{$query}%")
        ->orWhere(DB::raw('LOWER(office_division)'), 'like', "%{$query}%")
        ->limit(5)
        ->get(['fullname', 'designation', 'office_division', 'employment_status'])
        ->map(fn ($e) => [
            'fullname' => $e->fullname,
            'designation' => $e->designation,
            'office_division' => $e->office_division,
            'status' => $e->employment_status,
        ]);

    return response()->json(['folders' => $folders, 'employees' => $employees]);
})->middleware('auth')->name('api.search');

Route::post('/notifications/read', function () {
    auth()->user()->unreadNotifications->markAsRead();

    return response()->json(['success' => true]);
})->middleware('auth')->name('notifications.read');

Route::get('/admin/procurement/file-stream/{attachmentId}', function ($attachmentId) {
    $attachment = ProcurementAttachment::findOrFail($attachmentId);

    // Explicit security check: Validate permission levels before displaying sensitive data
    $user = auth()->user();

    // Procurement Officers and Admins always have access to procurement documents
    $hasBypassRole = $user->hasAnyRole(['Admin', 'Procurement Officer']);

    if (!$hasBypassRole && (!$user->employee || !$user->employee->isAllowedToSignOrViewDocs())) {
        abort(403, 'Unauthorized access to secure financial records.');
    }

    if (!Storage::disk('secure_procurement')->exists($attachment->file_path)) {
        abort(404, 'The physical asset was not found on the secure storage server.');
    }

    return Storage::disk('secure_procurement')->response($attachment->file_path, null, [
        'Content-Type' => $attachment->mime_type,
        'Content-Disposition' => 'inline; filename="' . $attachment->original_name . '"',
    ]);
})->name('admin.file-stream')->middleware(['auth']);

require __DIR__ . '/auth.php';
