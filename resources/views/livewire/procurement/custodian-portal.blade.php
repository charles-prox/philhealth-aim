<?php

use App\Models\ProcurementFolder;
use App\Models\AppHeader;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;

use Livewire\Attributes\Url;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        // Office Heads: read-only view to track their office's requests
        // Document Custodians: full create/edit/submit access
        // Admin & Procurement Officer: full access to both portals
        if (!auth()->user()->hasAnyRole(['Document custodian', 'Office Head', 'Admin', 'Procurement Officer'])) {
            abort(403, 'Unauthorized access.');
        }
    }

    #[Url]
    public $search = '';
    public bool $isCreatingPr = false;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    // Action and dialog state
    public ?string $editingFolderId = null;
    public ?string $confirmingDeleteId = null;
    public ?string $viewingHistoryFolderId = null;
    public ?string $viewingFolderId = null;

    /** Office Heads are read-only: they can view PRs but not create/edit/submit/delete. */
    public function isReadOnly(): bool
    {
        // Only Office Head (without Admin override) is read-only
        return auth()->user()->hasRole('Office Head')
            && !auth()->user()->hasAnyRole(['Admin', 'Procurement Officer']);
    }

    #[On('pr-created')]
    public function onPrCreated()
    {
        $this->isCreatingPr = false;
        $this->editingFolderId = null;
        $this->successMessage = "PR compiled successfully! Folder has been created/updated in the Procurement Tracker.";
        $this->errorMessage = null;
        $this->resetPage();
    }

    #[On('pr-cancelled')]
    public function onPrCancelled()
    {
        $this->isCreatingPr = false;
        $this->editingFolderId = null;
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    #[On('close-pr-creation')]
    public function onClosePrCreation()
    {
        $this->isCreatingPr = false;
        $this->editingFolderId = null;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function generateAndViewPdf($folderId)
    {
        $this->errorMessage = null;
        $folder = ProcurementFolder::findOrFail($folderId);
        
        if (in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER'])) {
            $this->errorMessage = 'Generating or viewing PDFs for Draft or Cancelled Purchase Requests is not allowed.';
            return;
        }
        
        $this->dispatch('open-pdf', url: route('procurement.pr.pdf', $folder->id));
    }

    #[On('open-new-pr')]
    public function openNewPr()
    {
        if ($this->isReadOnly()) {
            $this->errorMessage = 'You have read-only access. Contact the Document Custodian to create a PR.';
            return;
        }

        if (!auth()->user()->hasAnyRole(['Admin', 'Document custodian', 'Procurement Officer'])) {
            abort(403, 'Unauthorized action.');
        }

        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $appGateCleared = AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->exists();
        if (!$appGateCleared) {
            $this->errorMessage = "PR Creation Suspended: The Annual Procurement Plan (APP) for fiscal year {$currentYear} has not been uploaded or approved by the Admin Head.";
            return;
        }

        $this->editingFolderId = null;
        $this->isCreatingPr = true;
        $this->dispatch('open-pr-creation');
    }

    public function editPr($id)
    {
        if ($this->isReadOnly()) {
            $this->errorMessage = 'You have read-only access and cannot edit PRs.';
            return;
        }

        if (!auth()->user()->hasAnyRole(['Admin', 'Document custodian', 'Procurement Officer'])) {
            abort(403, 'Unauthorized action.');
        }

        $folder = ProcurementFolder::findOrFail($id);
        if ($folder->status === 'CANCELLED' || $folder->status === 'CANCELLED_BY_USER') {
            abort(403, 'Access Denied: This Purchase Request has been permanently archived and cannot be modified.');
        }

        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $appGateCleared = AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->exists();
        if (!$appGateCleared) {
            $this->errorMessage = "PR Editing Suspended: The Annual Procurement Plan (APP) for fiscal year {$currentYear} has not been approved.";
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;
        $this->editingFolderId = $id;
        $this->isCreatingPr = true;
        
        $this->dispatch('open-pr-creation', folderId: $id);
    }

    public function submitForApproval($id)
    {
        if ($this->isReadOnly()) {
            abort(403, 'Unauthorized action.');
        }

        $folder = ProcurementFolder::findOrFail($id);
        $user = auth()->user();
        if (!$user->hasRole('Admin') && $folder->requested_by_id !== $user->employee_id) {
            abort(403, 'Unauthorized action.');
        }

        $actor = $user->employee;
        if (!$actor) {
            $this->errorMessage = "System error: Your account is not linked to an Employee record. Please contact the administrator.";
            return;
        }

        DB::transaction(function () use ($folder, $actor) {
            $hasRejection = $folder->logs()->where('action', 'REJECTED')->exists();

            $folder->update([
                'status' => 'SUBMITTED_TO_GSU',
                'requested_signed_at' => now(),
            ]);

            if ($folder->pr_number) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");
            }

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => $hasRejection ? 'RESUBMITTED' : 'SUBMITTED',
                'actor_id' => $actor->id,
                'remarks' => $hasRejection 
                    ? 'PR resubmitted to GSU Inbox. Digitally signed: Purchase Request (PR) and Cover Letter.' 
                    : 'PR submitted to GSU Inbox. Digitally signed: Purchase Request (PR) and Cover Letter.',
            ]);
        });

        // Regenerate PDFs with end-user signature stamp
        \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($folder);

        $this->successMessage = "PR submitted to GSU Inbox successfully!";
    }

    public function cancelSubmission($id)
    {
        if ($this->isReadOnly()) {
            abort(403, 'Unauthorized action.');
        }

        $folder = ProcurementFolder::findOrFail($id);
        $user = auth()->user();
        if (!$user->hasRole('Admin') && $folder->requested_by_id !== $user->employee_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($folder->status !== 'SUBMITTED_TO_GSU') {
            $this->errorMessage = "Only submitted PRs that have not been accepted can be cancelled.";
            return;
        }

        $folder->cancelAndPurge('CANCELLED_BY_USER');

        \App\Models\ProcurementLog::create([
            'procurement_folder_id' => $folder->id,
            'action' => 'REJECTED',
            'actor_id' => auth()->user()->employee_id,
            'remarks' => 'Submission cancelled by requester.',
        ]);

        $this->successMessage = "Submission cancelled. PR cancelled and deleted from disk.";
    }

    public function confirmDelete($id)
    {
        if ($this->isReadOnly()) {
            abort(403, 'Unauthorized action.');
        }

        $folder = ProcurementFolder::findOrFail($id);
        $user = auth()->user();
        if (!$user->hasRole('Admin') && $folder->requested_by_id !== $user->employee_id) {
            abort(403, 'Unauthorized action.');
        }
        $this->confirmingDeleteId = $id;
    }

    public function deletePr()
    {
        if ($this->isReadOnly()) {
            abort(403, 'Unauthorized action.');
        }

        $folder = ProcurementFolder::findOrFail($this->confirmingDeleteId);
        $user = auth()->user();
        if (!$user->hasRole('Admin') && $folder->requested_by_id !== $user->employee_id) {
            abort(403, 'Unauthorized action.');
        }

        DB::transaction(function () use ($folder) {
            \App\Models\CobItemDistribution::whereHas('prItem', function($q) {
                $q->where('pr_items.folder_id', $this->confirmingDeleteId);
            })->update([
                'pr_item_id' => null,
                'procured_quantity' => 0,
            ]);

            $folder->cancelAndPurge('CANCELLED_BY_USER');
        });

        $this->successMessage = "PR has been cancelled and distributions released.";
        $this->confirmingDeleteId = null;
    }

    public function viewHistory($id)
    {
        $this->viewingHistoryFolderId = $id;
    }

    public function closeHistory()
    {
        $this->viewingHistoryFolderId = null;
    }

    public function viewDetails($id)
    {
        $this->viewingFolderId = $id;
    }

    public function closeDetails()
    {
        $this->viewingFolderId = null;
    }

    #[\Livewire\Attributes\Computed]
    public function viewingFolder()
    {
        return $this->viewingFolderId ? \App\Models\ProcurementFolder::with(['prItems.appLineItem', 'attachments'])->find($this->viewingFolderId) : null;
    }

    #[On('app-status-updated')]
    public function onAppStatusUpdated()
    {
        // Force refresh state on APP updates
    }

    public function with(): array
    {
        $user = auth()->user();
        $employee = $user->employee;
        $employeeId = $employee?->id;
        $isAdmin = $user->hasRole('Admin');
        $isOfficeHead = $user->hasRole('Office Head') && !$isAdmin;

        $isProcurementOfficer = $user->hasRole('Procurement Officer');

        $query = ProcurementFolder::with(['purchaseOrder', 'prItems', 'currentSignatory']);

        // Enforce RBAC Database Scoping
        if ($isAdmin || $isProcurementOfficer) {
            // Admins and Procurement Officers see all PRs globally
        } elseif ($isOfficeHead) {
            // Office Heads: see PRs from their division or where they are a signatory
            $query->where(function($q) use ($employeeId, $employee) {
                $q->where('requested_by_id', $employeeId)
                  ->orWhere('current_signatory_id', $employeeId);
                if ($employee?->office_division) {
                    $q->orWhere('requesting_unit', $employee->office_division);
                }
            });
        } else {
            // Document Custodian: only PRs they personally created
            $query->where('requested_by_id', $employeeId);
        }

        // Apply Search Filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where(DB::raw('LOWER(pr_number)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(overall_purpose)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(project_title)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(requesting_unit)'), 'like', '%' . strtolower($this->search) . '%');

                // System Security Code lookup
                $cleanSearch = strtoupper(str_replace('TRK-', '', trim($this->search)));
                if (ctype_xdigit($cleanSearch) && strlen($cleanSearch) === 12) {
                    $matchingId = \App\Models\ProcurementFolder::select('id')
                        ->get()
                        ->first(fn($f) => strtoupper(substr(md5($f->id), 0, 12)) === $cleanSearch)
                        ?->id;
                    if ($matchingId) {
                        $q->orWhere('id', $matchingId);
                    }
                }
            });
        }

        $folders = $query->orderBy('created_at', 'desc')->paginate(10);

        // KPI counts scoped by role
        if ($isAdmin || $isProcurementOfficer) {
            $totalActive = ProcurementFolder::whereNotIn('status', ['PO_RELEASED'])->count();
            $totalPending = ProcurementFolder::where('status', 'DRAFT')->count();
        } elseif ($isOfficeHead) {
            $baseScope = ProcurementFolder::where(function($q) use ($employeeId, $employee) {
                $q->where('requested_by_id', $employeeId)
                  ->orWhere('current_signatory_id', $employeeId);
                if ($employee?->office_division) {
                    $q->orWhere('requesting_unit', $employee->office_division);
                }
            });
            $totalActive = (clone $baseScope)->whereNotIn('status', ['PO_RELEASED'])->count();
            $totalPending = (clone $baseScope)->where('status', 'DRAFT')->count();
        } else {
            $totalActive = ProcurementFolder::whereNotIn('status', ['PO_RELEASED'])
                ->where('requested_by_id', $employeeId)->count();
            $totalPending = ProcurementFolder::where('status', 'DRAFT')
                ->where('requested_by_id', $employeeId)->count();
        }

        // APP gate check
        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $appGateCleared = AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->exists();

        return [
            'folders'        => $folders,
            'totalActive'    => $totalActive,
            'totalPending'   => $totalPending,
            'appGateCleared' => $appGateCleared,
            'currentYear'    => $currentYear,
            'isReadOnly'     => $this->isReadOnly(),
        ];
    }
}; ?>

<div x-data="{ isCreatingPr: $wire.entangle('isCreatingPr') }">
    @section('header_title', $isCreatingPr ? 'Compile Purchase Request' : 'Procurement Portal')

    @push('header_actions')
        @if($isCreatingPr)
            <x-primary-button variant="secondary" icon="arrow_back" x-on:click="$dispatch('close-pr-creation')">Back to Registry</x-primary-button>
        @elseif($appGateCleared && !$isReadOnly)
            <x-primary-button icon="add" x-on:click="$dispatch('open-new-pr')">New PR</x-primary-button>
        @elseif($isReadOnly)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#fff9c4]/80 border border-[#fbc02d]/30 text-[#f57f17] rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm">
                <span class="material-symbols-outlined text-[14px]">visibility</span>
                Read-Only View
            </span>
        @endif
    @endpush

    <div class="p-container-padding bg-background space-y-6" 
         x-on:open-pdf.window="window.open($event.detail.url, '_blank')"
         x-on:open-new-pr.window="$wire.openNewPr()">

        {{-- PR Registry Workspace --}}
        <div x-show="!isCreatingPr"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="space-y-6">

            @include('livewire.procurement.partials.pr-portal-header')

            @include('livewire.procurement.partials.pr-registry-table')

        @include('livewire.procurement.partials.pr-compiler-modal')
        @include('livewire.procurement.partials.delete-confirm-modal')
        @include('livewire.procurement.partials.audit-trail-modal')
        @include('livewire.procurement.partials.folder-details-modal')

</div>
