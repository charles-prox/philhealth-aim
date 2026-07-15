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
        if (!auth()->user()->hasAnyRole(['Admin', 'Procurement Officer'])) {
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

    // Approver/Recommender: inline revision overlay inside the drawer
    public ?string $inlineRevisionFolderId = null;
    public string $inlineRevisionRemarks = '';

    // Approver/Recommender: which drawer row is expanded
    public ?string $expandedDrawerId = null;

    // GSU Inbox State
    public string $activeTab = 'registry';

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
        
        // Dispatch browser event to open new tab directly
        $this->dispatch('open-pdf', url: route('procurement.pr.pdf', $folder->id));
    }

    #[On('open-new-pr')]
    public function openNewPr()
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Procurement Officer', 'Document custodian', 'Office Head'])) {
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
        // Note: no dispatch('open-pr-creation') — the :key prop on the sub-component
        // forces a fresh mount automatically when isCreatingPr flips to true.
    }

    public function editPr($id)
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Procurement Officer', 'Document custodian', 'Office Head'])) {
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
        // Note: no dispatch needed — the :key prop on the sub-component handles re-mount.
    }

    public function submitForApproval($id)
    {
        if (auth()->user()->hasAnyRole(['MSD Head', 'Admin Head'])) {
            abort(403, 'Unauthorized action.');
        }
        $folder = ProcurementFolder::findOrFail($id);

        $actor = auth()->user()->employee;
        if (!$actor) {
            $this->errorMessage = "System error: Your account is not linked to an Employee record. Please contact the administrator.";
            return;
        }

        DB::transaction(function () use ($folder, $actor) {
            $hasRejection = $folder->logs()->where('action', 'REJECTED')->exists();

            $folder->update([
                'status' => 'ROUTING',
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
                    ? 'Purchase Request resubmitted and routed for signatures. The physical document package is enroute to the division Recommending Officer.' 
                    : 'Purchase Request submitted and routed for signatures. The physical document package is enroute to the division Recommending Officer.',
            ]);
        });

        $this->successMessage = "PR submitted & routed for signature successfully!";
    }


    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
    }

    public function deletePr()
    {
        $folder = ProcurementFolder::findOrFail($this->confirmingDeleteId);

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

    public function checkIsPossibleDuplicate($folder)
    {
        foreach ($folder->prItems as $item) {
            $duplicateExists = DB::table('procurement_folders')
                ->join('pr_items', 'procurement_folders.id', '=', 'pr_items.folder_id')
                ->where('procurement_folders.id', '!=', $folder->id)
                ->where('procurement_folders.office_id', $folder->office_id)
                ->where('pr_items.app_line_item_id', $item->app_line_item_id)
                ->where('pr_items.total_qty', $item->total_qty)
                ->whereIn('procurement_folders.status', ['SUBMITTED_TO_GSU', 'ROUTING', 'APPROVED'])
                ->where('procurement_folders.created_at', '>=', now()->subDays(30))
                ->exists();

            if ($duplicateExists) return true;
        }
        return false;
    }

    public function toggleDrawer($id)
    {
        $this->expandedDrawerId = ($this->expandedDrawerId === $id) ? null : $id;
        $this->inlineRevisionFolderId = null;
        $this->inlineRevisionRemarks = '';
    }

    public function startInlineRevision($folderId)
    {
        $this->inlineRevisionFolderId = $folderId;
        $this->inlineRevisionRemarks = '';
    }

    public function cancelInlineRevision()
    {
        $this->inlineRevisionFolderId = null;
        $this->inlineRevisionRemarks = '';
    }

    public function submitInlineRevision()
    {
        $this->validate([
            'inlineRevisionRemarks' => 'required|string|max:1000',
        ], [], ['inlineRevisionRemarks' => 'Revision Remarks']);

        $folder = ProcurementFolder::findOrFail($this->inlineRevisionFolderId);

        $actor = auth()->user()->employee;
        if (!$actor) {
            $this->errorMessage = "System error: Your account is not linked to an Employee record. Please contact the administrator.";
            return;
        }

        DB::transaction(function () use ($folder, $actor) {
            $folder->update([
                'status' => 'DRAFT',
                'recommended_signed_at' => null,
                'approved_signed_at' => null,
            ]);

            if ($folder->pr_number) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");
            }

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => 'REJECTED',
                'actor_id' => $actor->id,
                'remarks' => $this->inlineRevisionRemarks,
            ]);
        });

        $this->successMessage = "PR has been returned to DRAFT with your revision remarks.";
        $this->inlineRevisionFolderId = null;
        $this->inlineRevisionRemarks = '';
        $this->expandedDrawerId = null;
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
        $isProcurementOfficer = $user->hasRole('Procurement Officer');

        $query = ProcurementFolder::with(['purchaseOrder', 'prItems', 'currentSignatory'])
            ->whereNotNull('pr_number')
            ->where('pr_number', '<>', '');

        // Scoping for Procurement Officers (who are not Admins)
        if (!$isAdmin && $isProcurementOfficer) {
            $query->where(function($q) use ($employeeId) {
                // 1. Can see non-draft, non-cancelled folders
                $q->whereNotIn('status', ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER'])
                  // 2. Or they can see drafts they personally created/requested
                  ->orWhere(function($sub) use ($employeeId) {
                      $sub->where('status', 'DRAFT')
                          ->where(function($orSub) use ($employeeId) {
                              $orSub->where('requested_by_id', $employeeId)
                                    ->orWhere('created_by_id', auth()->id());
                          });
                  })
                  // 3. Or they can see cancelled folders ONLY if they were once submitted to GSU
                  ->orWhere(function($sub) {
                      $sub->whereIn('status', ['CANCELLED', 'CANCELLED_BY_USER'])
                          ->whereHas('logs', function($l) {
                              $l->where('action', 'SUBMITTED');
                          });
                  });
            });
        }

        // Apply Search Filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where(DB::raw('LOWER(pr_number)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(overall_purpose)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(requesting_unit)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhereHas('purchaseOrder', function($sq) {
                      $sq->whereHas('supplier', function($ssq) {
                          $ssq->where(DB::raw('LOWER(name)'), 'like', '%' . strtolower($this->search) . '%');
                      });
                  });

                // Match System Security Code (TRK-...) for Paper-to-Digital validation
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

        // KPIs
        $totalActive = ProcurementFolder::whereNotIn('status', ['PO_RELEASED'])->count();
        $totalPending = ProcurementFolder::where('status', 'DRAFT')->count();

        // GSU Inbox data
        $triageQuery = ProcurementFolder::where('status', 'SUBMITTED_TO_GSU')->with(['prItems.appLineItem']);
        $triageCount = $triageQuery->count();
        $triageFolders = $triageQuery->orderBy('created_at', 'desc')->get();

        // Check if APP gate is cleared for the current year
        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $appGateCleared = AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->exists();

        return [
            'folders'              => $folders,
            'totalActive'          => $totalActive,
            'totalPending'         => $totalPending,
            'totalValue'           => \App\Models\PurchaseOrder::sum('total_amount') ?? 0,
            'avgTurnaround'        => 0,
            'isApprover'           => false,
            'isAdmin'              => $isAdmin,
            'isProcurementOfficer' => $isProcurementOfficer,
            'isDocumentCustodian'  => false,
            'isOfficeHead'         => false,
            'approverPendingValue' => 0,
            'triageFolders'        => $triageFolders,
            'triageCount'          => $triageCount,
            'appGateCleared'       => $appGateCleared,
            'currentYear'          => $currentYear,
        ];
    }
}; ?>

<div x-data="{ isCreatingPr: $wire.entangle('isCreatingPr') }">
    @section('header_title', $isCreatingPr ? 'Compile Purchase Request' : 'Procurement')

    @push('header_actions')
        @if($isCreatingPr)
            <x-primary-button variant="secondary" icon="arrow_back" x-on:click="$dispatch('close-pr-creation')">Back to Registry</x-primary-button>
        @elseif(auth()->user()->hasAnyRole(['Admin', 'Procurement Officer', 'Document custodian', 'Office Head']) && $appGateCleared)
            <x-primary-button icon="add" x-on:click="$dispatch('open-new-pr')">New PR</x-primary-button>
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
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="space-y-6">

            {{-- APP Warning Banner --}}
            @if(!$appGateCleared)
                <div class="flex items-start gap-4 bg-amber-50 border border-amber-200 text-amber-900 px-5 py-4 rounded-xl shadow-sm mb-4">
                    <span class="material-symbols-outlined text-amber-600 text-[28px] mt-0.5" style="font-variation-settings: 'FILL' 1;">warning</span>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-amber-950">Procurement Pipeline Suspended</h4>
                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                            No active Annual Procurement Plan (APP) has been approved for the current fiscal year ({{ $currentYear }}). 
                            Purchase Request compilation is locked until the APP is uploaded and activated by the Admin Head.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Success Banner --}}
            @if($successMessage)
                <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-5 py-3 rounded-xl shadow-sm" x-data="{ show: true }" x-show="show">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <p class="text-sm font-bold flex-1">{{ $successMessage }}</p>
                    <button @click="show = false" wire:click="$set('successMessage', null)" class="p-1 hover:bg-green-100 rounded-lg">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            @endif

            {{-- Error Banner --}}
            @if(session('error') || $errorMessage)
                <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-5 py-3 rounded-xl shadow-sm mb-4" x-data="{ show: true }" x-show="show">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <p class="text-sm font-bold flex-1">{{ session('error') ?? $errorMessage }}</p>
                    <button @click="show = false" @if($errorMessage) wire:click="$set('errorMessage', null)" @endif class="p-1 hover:bg-red-100 rounded-lg">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            @endif

            @include('livewire.procurement.partials.gsu-kpi-header')

            @include('livewire.procurement.partials.gsu-pr-table')

        </div> {{-- Close x-show="!isCreatingPr" --}}
    </div> {{-- Close p-container-padding --}}

        @include('livewire.procurement.partials.pr-compiler-modal')
        @include('livewire.procurement.partials.delete-confirm-modal')
        @include('livewire.procurement.partials.audit-trail-modal')
        @include('livewire.procurement.partials.folder-details-modal')

</div>
