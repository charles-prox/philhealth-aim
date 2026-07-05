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

    // GSU Triage Box State
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

        // GSU Triage Box data
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

        {{-- KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            @php $kpis = [
                ['label' => 'Active Requests',    'value' => $totalActive > 0 ? $totalActive : null, 'sub' => 'Ongoing tracking',    'icon' => 'description',     'icon_bg' => 'bg-[#001e40]/8',   'icon_color' => 'text-[#001e40]', 'trend' => 'up',   'trend_color' => 'text-green-700'],
                ['label' => 'Total PO Value',     'value' => $totalValue > 0 ? '₱'.number_format($totalValue/1000000, 2).'M' : null, 'sub' => 'Awarded & Released', 'icon' => 'account_balance_wallet', 'icon_bg' => 'bg-[#d5e3ff]/60', 'icon_color' => 'text-[#1f477b]', 'trend' => null,   'trend_color' => ''],
                ['label' => 'Pending Action',   'value' => $totalPending > 0 ? $totalPending : null, 'sub' => 'Draft / Unawarded',   'icon' => 'pending_actions',  'icon_bg' => 'bg-[#ffdad6]/60', 'icon_color' => 'text-[#ba1a1a]', 'trend' => 'alert','trend_color' => 'text-[#ba1a1a]'],
                ['label' => 'Avg Turnaround',     'value' => $avgTurnaround > 0 ? $avgTurnaround.'d': null, 'sub' => 'Delivery time',    'icon' => 'timer',            'icon_bg' => 'bg-green-50',      'icon_color' => 'text-green-700', 'trend' => 'check', 'trend_color' => 'text-green-700'],
            ] @endphp

            @foreach($kpis as $kpi)
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm">
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">{{ $kpi['label'] }}</span>
                    <div class="w-9 h-9 {{ $kpi['icon_bg'] }} rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined {{ $kpi['icon_color'] }} text-[20px]">{{ $kpi['icon'] }}</span>
                    </div>
                </div>
                @if($kpi['value'] !== null)
                    <p class="text-3xl font-bold text-[#001e40]">{{ $kpi['value'] }}</p>
                    <p class="text-[11px] {{ $kpi['trend_color'] ?: 'text-[#43474f]' }} mt-2 font-bold uppercase tracking-wider flex items-center gap-1">
                        @if($kpi['trend'] === 'up')    <span class="material-symbols-outlined text-[14px]">trending_up</span>
                        @elseif($kpi['trend'] === 'alert') <span class="material-symbols-outlined text-[14px]">warning</span>
                        @elseif($kpi['trend'] === 'check') <span class="material-symbols-outlined text-[14px]">check_circle</span>
                        @endif
                        {{ $kpi['sub'] }}
                    </p>
                @else
                    <p class="text-3xl font-bold text-[#c3c6d1]">—</p>
                    <p class="text-[11px] text-[#c3c6d1] mt-2 font-bold uppercase tracking-wider">No data yet</p>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Tab Switching --}}
        <div class="flex border-b border-[#c3c6d1] gap-6 mb-6">
            <button wire:click="$set('activeTab', 'registry')" class="pb-3 text-sm font-bold border-b-2 transition-all flex items-center gap-2 {{ $activeTab === 'registry' ? 'border-[#001e40] text-[#001e40]' : 'border-transparent text-[#43474f] hover:text-[#001e40]' }}">
                <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                Procurement Registry
            </button>
            <button wire:click="$set('activeTab', 'triage')" class="pb-3 text-sm font-bold border-b-2 transition-all flex items-center gap-2 relative {{ $activeTab === 'triage' ? 'border-[#001e40] text-[#001e40]' : 'border-transparent text-[#43474f] hover:text-[#001e40]' }}">
                <span class="material-symbols-outlined text-[18px]">move_to_inbox</span>
                GSU Inbox
                @if($triageCount > 0)
                    <span class="px-2 py-0.5 text-[10px] font-black bg-[#ba1a1a] text-white rounded-full animate-pulse">{{ $triageCount }}</span>
                @endif
            </button>
        </div>

        @if($activeTab === 'registry')
            {{-- PR Table --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative">
                <div wire:loading class="absolute inset-x-0 bottom-0 top-[45px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                        <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
                    </div>
                </div>

                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                    <h3 class="font-bold text-[#001e40] text-lg">Office PR Registry</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search PR or purpose..." class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all w-56 placeholder-[#43474f]/40"/>
                        </div>
                        <x-primary-button variant="secondary" icon="filter_list" class="!px-3" />
                        <x-primary-button variant="secondary" icon="download" class="!px-3" />
                    </div>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Tracking Number</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">PR Number</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Requested</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Purpose</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                           @forelse($folders as $folder)
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#43474f] font-mono text-xs">{{ $folder->tracking_number }}</td>
                                <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $folder->pr_number ?? '—' }}</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">{{ $folder->created_at->format('M d, Y') }}</td>
                                <td class="p-table-cell-padding text-[#1a1c1f] relative group">
                                    @php
                                        $purposeLines = explode("\n", trim($folder->overall_purpose ?: 'No purpose specified'));
                                        $firstLine = trim($purposeLines[0] ?? '');
                                        $hasMultipleLines = count($purposeLines) > 1 || strlen($firstLine) > 40;
                                        $displayPurpose = Str::limit($firstLine, 40) . ($hasMultipleLines ? '...' : '');
                                    @endphp
                                    <div class="cursor-help font-medium hover:text-[#001e40] transition-colors">
                                        {{ $displayPurpose }}
                                    </div>
                                    <!-- Custom Hover Tooltip (Scrollable) -->
                                    <div class="absolute left-4 top-full mt-1 hidden group-hover:block w-80 max-h-36 overflow-y-auto bg-[#001e40] text-white text-[11px] p-3 rounded-xl shadow-xl border border-white/10 z-50 whitespace-pre-line custom-scrollbar">
                                        <div class="font-bold text-[9px] uppercase tracking-wider text-white/50 mb-1 border-b border-white/10 pb-1">Full Purpose</div>
                                        {{ $folder->overall_purpose ?: 'No purpose specified' }}
                                    </div>
                                </td>
                                <td class="p-table-cell-padding">
                                    @php
                                        $statusColors = [
                                            'DRAFT' => 'bg-[#eeedf2] text-[#43474f]',
                                            'SUBMITTED_TO_GSU' => 'bg-[#e0f7fa] text-[#006064] border border-[#00acc1]/20',
                                            'ROUTING' => 'bg-[#fff9c4] text-[#f57f17] border border-[#fbc02d]/30',
                                            'APPROVED' => 'bg-green-50 text-green-800 border border-green-200',
                                            'PR_PRINTED' => 'bg-[#ffdbca] text-[#341100]',
                                            'RFQ_SENT' => 'bg-[#d8e1ea] text-[#5b646b]',
                                            'AWARDED' => 'bg-green-100 text-green-800',
                                            'PO_RELEASED' => 'bg-[#d5e3ff] text-[#001b3c]',
                                            'CANCELLED' => 'bg-red-50 text-red-700 border border-red-200',
                                            'CANCELLED_BY_USER' => 'bg-red-50 text-red-700 border border-red-200',
                                            'RETURNED_FOR_EDIT' => 'bg-amber-50 text-amber-800 border border-amber-200',
                                            'RETURNED_FOR_COMPLIANCE' => 'bg-purple-50 text-purple-800 border border-purple-200',
                                        ];
                                        $color = $statusColors[$folder->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $color }}">{{ $folder->status_label }}</span>
                                </td>

                                <td class="p-table-cell-padding text-right">
                                        <div class="relative inline-block text-left" x-data="{ open: false, coords: { top: 0, left: 0 } }">
                                            <button @click="open = !open; if(open) { let rect = $el.getBoundingClientRect(); coords.top = rect.bottom + window.scrollY; coords.left = rect.right - 240 + window.scrollX; }" class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all flex items-center justify-center" title="Actions">
                                                <span class="material-symbols-outlined text-[20px]">more_vert</span>
                                            </button>
                                            
                                            <template x-teleport="body">
                                                <div x-show="open"
                                                     @click.outside="open = false"
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 scale-95"
                                                     x-transition:enter-end="opacity-100 scale-100"
                                                     x-transition:leave="transition ease-in duration-75"
                                                     x-transition:leave-start="opacity-100 scale-100"
                                                     x-transition:leave-end="opacity-0 scale-95"
                                                     class="absolute z-[99999] w-60 rounded-xl shadow-lg bg-white border border-[#c3c6d1]"
                                                     :style="`top: ${coords.top}px; left: ${coords.left}px; display: none;`"
                                                     @click="open = false">
                                                    <div class="p-1.5 space-y-1 text-left">
                                                        {{-- View PR Details (Always Available) --}}
                                                        <button wire:click="viewDetails('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">info</span>
                                                            <span>View PR Details</span>
                                                        </button>

                                                        {{-- View PDF Form (Only if not Draft or Cancelled) --}}
                                                        @if(!in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER']))
                                                            <button wire:click="generateAndViewPdf('{{ $folder->id }}')" wire:loading.attr="disabled" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all relative whitespace-nowrap">
                                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                                                <span>View PDF Form</span>
                                                            </button>
                                                        @endif

                                                        {{-- Audit Log History (Always Available) --}}
                                                        <button wire:click="viewHistory('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">history</span>
                                                            <span>View Audit Trail</span>
                                                        </button>

                                                        @if($folder->status === 'DRAFT')
                                                            <div class="h-px bg-[#eeedf2] my-1"></div>
                                                            {{-- Route for Signature --}}
                                                            <a href="{{ route('procurement.review', $folder->id) }}" wire:navigate class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#1f477b] hover:bg-blue-50 rounded-lg transition-all whitespace-nowrap">
                                                                 <span class="material-symbols-outlined text-[18px]">rate_review</span>
                                                                 <span>Sign & Submit to GSU</span>
                                                            </a>

                                                            {{-- Edit Draft --}}
                                                            <button wire:click="editPr('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#001e40] hover:bg-[#f4f3f8] hover:text-black rounded-lg transition-all whitespace-nowrap">
                                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                                <span>Edit Draft</span>
                                                            </button>

                                                            {{-- Delete Draft --}}
                                                            <button wire:click="confirmDelete('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#ba1a1a] hover:bg-red-50 rounded-lg transition-all whitespace-nowrap">
                                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                                                <span>Delete Draft</span>
                                                            </button>
                                                        @endif


                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                        <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">receipt_long</span>
                                        @if($search)
                                            <p class="font-bold text-[#001e40] text-lg">No Results Found</p>
                                            <p class="text-[13px] text-[#43474f] max-w-xs">We couldn't find any procurement folders matching "{{ $search }}".</p>
                                        @else
                                            <p class="font-bold text-[#001e40] text-lg">No Purchase Requests Found</p>
                                            <p class="text-[13px] text-[#43474f] max-w-xs">There are no procurement requests yet. Create the first one to start tracking your purchasing pipeline.</p>
                                            @if($appGateCleared)
                                                <x-primary-button icon="add" class="mt-2" x-on:click="$dispatch('open-new-pr')">Create First PR</x-primary-button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($folders->hasPages())
                    <div class="p-gutter border-t border-[#c3c6d1] bg-[#f9f9fe]">
                        {{ $folders->links() }}
                    </div>
                @elseif($folders->count() > 0)
                    <div class="p-gutter border-t border-[#c3c6d1] flex items-center justify-between bg-[#f9f9fe]">
                        <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing 1 to {{ $folders->count() }} of {{ $folders->total() }} PRs</p>
                    </div>
                @endif
            </div>
        @else
            {{-- GSU Inbox Table --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative">
                <div wire:loading class="absolute inset-x-0 bottom-0 top-[45px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                        <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating Inbox...</span>
                    </div>
                </div>

                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-[#001e40] text-lg">GSU Inbox</h3>
                        <p class="text-[11px] text-[#43474f] mt-0.5">Incoming Purchase Requests submitted by end-users. Review and assign official PR numbers before routing.</p>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Tracking Number</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Submitted</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Requesting Unit</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Compiler</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Total Cost</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                            @forelse($triageFolders as $folder)
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#001e40]">
                                    <div>{{ $folder->tracking_number ?? '—' }}</div>
                                    @if($this->checkIsPossibleDuplicate($folder))
                                        <span class="inline-flex items-center gap-1 bg-[#fff3cd] text-[#856404] border border-[#ffeeba] px-1.5 py-0.5 rounded text-[10px] font-bold mt-1">
                                            <span class="material-symbols-outlined text-[12px] font-bold">warning</span> Potential Duplicate
                                        </span>
                                    @endif
                                </td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">{{ $folder->created_at->format('M d, Y') }}</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">
                                    {{ $folder->requesting_unit ?? $folder->overall_purpose ?? '—' }}
                                </td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">
                                    {{ $folder->requestedBy?->fullname ?? '—' }}
                                </td>
                                <td class="p-table-cell-padding font-bold text-[#001e40]">
                                    ₱{{ number_format($folder->prItems->sum('estimated_total_cost'), 2) }}
                                </td>
                                <td class="p-table-cell-padding text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        <a href="{{ route('procurement.gsu.review', $folder->id) }}"
                                            class="px-3 py-1.5 bg-[#f4f3f8] text-[#001e40] border border-[#c3c6d1] text-[11px] font-bold rounded-lg hover:bg-[#eeedf2] transition-all flex items-center gap-1.5"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">rate_review</span>
                                            Open & Review
                                        </a>

                                        <button
                                            wire:click="viewHistory('{{ $folder->id }}')"
                                            class="p-1.5 bg-[#f4f3f8] text-[#43474f] border border-[#c3c6d1] rounded-lg hover:bg-[#eeedf2] transition-all material-symbols-outlined text-[18px]"
                                            title="Audit Trail"
                                        >history</button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                        <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">inbox</span>
                                        <p class="font-bold text-[#001e40] text-lg">Inbox is Clear</p>
                                        <p class="text-[13px] text-[#43474f] max-w-xs">There are no pending incoming Purchase Requests currently awaiting GSU review.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Insight Cards (Hidden on Empty State) --}}
        @if($totalActive > 0 || $totalPending > 0)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
            <div class="bg-[#d5e3ff]/30 p-8 border border-[#001e40]/10 rounded-2xl relative overflow-hidden group shadow-sm">
                <div class="relative z-10">
                    <h4 class="text-xl font-bold text-[#001e40] mb-2">Delivery Bottlenecks Detected</h4>
                    <p class="text-sm text-[#43474f] max-w-md leading-relaxed">3 awarded purchase requests from "Global Tech Solutions Inc." are exceeding the expected delivery timeline. Consideration for alternative RFQs recommended.</p>
                    <x-primary-button class="mt-5">Resolve Delays</x-primary-button>
                </div>
                <span class="material-symbols-outlined absolute -right-6 -bottom-6 text-[140px] text-[#001e40]/5 group-hover:scale-110 transition-transform duration-700" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
            </div>
            <div class="bg-white p-8 border border-[#c3c6d1] rounded-2xl shadow-sm flex gap-6 items-start">
                <div class="w-24 h-24 rounded-2xl bg-[#eeedf2] flex items-center justify-center flex-shrink-0 shadow-inner">
                    <span class="material-symbols-outlined text-[48px] text-[#43474f]">inventory</span>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="text-xl font-bold text-[#1a1c1f]">Regional Stock Utilization</h4>
                        <span class="px-3 py-1 bg-[#ffdad6] text-[#93000a] text-[10px] font-bold rounded-full uppercase">LOW STOCK</span>
                    </div>
                    <p class="text-sm text-[#43474f] mb-5 leading-relaxed">Critical medical supplies in Warehouse Sector B are below the 15% threshold. Initiate procurement cycle for FY26 Q2.</p>
                    <div class="w-full h-2 bg-[#eeedf2] rounded-full overflow-hidden">
                        <div class="h-full bg-[#ba1a1a] rounded-full" style="width: 15%"></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        </div> {{-- Close x-show="!isCreatingPr" --}}
    </div> {{-- Close p-container-padding --}}

        {{-- PR Compiler Workspace Modal --}}
        @if($isCreatingPr)
            <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto">
                <div class="bg-[#f1f3f6] border border-[#eeedf2] rounded-xl max-w-7xl w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200 relative flex flex-col my-8 h-[90vh]">
                    {{-- Modal Header --}}
                    <div class="bg-white px-6 py-4 flex justify-between items-center border-b border-[#eeedf2] rounded-t-xl flex-shrink-0">
                        @php
                            $editingFolder = $editingFolderId ? \App\Models\ProcurementFolder::find($editingFolderId) : null;
                        @endphp
                        @if($editingFolder)
                            <div class="flex items-center gap-3">
                                <h3 class="text-sm font-bold text-[#001e40] uppercase tracking-wider">Edit Draft Purchase Request</h3>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200">Draft</span>
                                <span class="font-mono text-xs text-[#43474f]/70 bg-[#eeedf2]/50 px-2 py-0.5 rounded border border-[#c3c6d1]">{{ $editingFolder->tracking_number }}</span>
                            </div>
                        @else
                            <h3 class="text-sm font-bold text-[#001e40] uppercase tracking-wider">Purchase Request Compilation Wizard</h3>
                        @endif
                        <button x-on:click="$dispatch('close-pr-creation')" class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all">
                            <span class="material-symbols-outlined text-[20px] font-bold">close</span>
                        </button>
                    </div>

                    {{-- Modal Body (Scrollable) --}}
                    <div class="overflow-y-auto px-6 flex-1">
                        <livewire:procurement.pr-compiler :folder-id="$editingFolderId" :key="$editingFolderId ?? 'new-pr'" />
                    </div>
                </div>
            </div>
        @endif


        {{-- Delete Confirmation Modal --}}
        @if($confirmingDeleteId)
            <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
                <div class="bg-white border border-[#eeedf2] rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
                    <div class="flex items-center gap-3 text-[#ba1a1a]">
                        <span class="material-symbols-outlined text-[28px]">warning</span>
                        <h4 class="text-lg font-bold">Confirm Deletion</h4>
                    </div>
                    <p class="text-xs text-[#43474f] leading-relaxed">
                        Are you sure you want to delete this Purchase Request draft? This action is irreversible, and all locked allocations will be released back to the COB Matrix.
                    </p>
                    <div class="flex justify-end gap-3 pt-2">
                        <button wire:click="$set('confirmingDeleteId', null)" class="px-4 py-2 bg-white border border-[#c3c6d1] hover:bg-[#f4f3f8] text-[#43474f] font-bold text-xs rounded-lg transition-all">
                            Cancel
                        </button>
                        <button wire:click="deletePr" class="px-4 py-2 bg-[#ba1a1a] hover:bg-[#ba1a1a]/90 text-white font-bold text-xs rounded-lg shadow-sm transition-all text-center">
                            Yes, Delete & Release
                        </button>
                    </div>
                </div>
            </div>
        @endif





        {{-- Audit Trail History Modal --}}
        @if($viewingHistoryFolderId)
            @php
                $historyFolder = \App\Models\ProcurementFolder::with('logs.actor')->find($viewingHistoryFolderId);
            @endphp
            <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
                <div class="bg-white border border-[#eeedf2] rounded-2xl max-w-xl w-full p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
                    <div class="flex justify-between items-center border-b border-[#eeedf2] pb-3">
                        <h4 class="text-base font-bold text-[#001e40] flex items-center gap-2">
                            <span class="material-symbols-outlined text-[22px]">history</span>
                            Audit Trail: {{ $historyFolder?->pr_number ?? $historyFolder?->tracking_number }}
                        </h4>
                        <button wire:click="closeHistory" class="p-1 hover:bg-[#eeedf2] rounded-lg">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>

                    <div class="max-h-[350px] overflow-y-auto pr-2 custom-scrollbar">
                        @if($historyFolder && $historyFolder->logs->isNotEmpty())
                            <div class="relative border-l-2 border-[#eeedf2] ml-4 pl-6 space-y-6">
                                @foreach($historyFolder->logs as $log)
                                    @php
                                        $actionClasses = [
                                            'REJECTED' => 'bg-red-100 text-[#ba1a1a] border-red-200',
                                            'RESUBMITTED' => 'bg-blue-50 text-[#1f477b] border-blue-100',
                                            'APPROVED' => 'bg-green-100 text-green-800 border-green-200',
                                        ];
                                        $class = $actionClasses[$log->action] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <div class="relative">
                                        {{-- Timeline bullet --}}
                                        <div class="absolute -left-[31px] top-0.5 w-4.5 h-4.5 rounded-full border-4 border-white bg-[#001e40] flex items-center justify-center"></div>
                                        
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-md uppercase border {{ $class }}">{{ $log->action }}</span>
                                                <span class="text-[10px] text-[#43474f]/60 font-semibold">{{ $log->created_at->format('M d, Y · h:i A') }}</span>
                                            </div>
                                            <p class="text-xs text-[#001e40] font-bold">
                                                By: {{ $log->actor?->fullname ?? 'Unknown Actor' }} <span class="text-[10px] text-[#43474f] font-normal italic">({{ $log->actor?->designation }})</span>
                                            </p>
                                            @if($log->remarks)
                                                <div class="bg-[#f9f9fe] border border-[#eeedf2] rounded-lg p-2.5 mt-1 text-xs text-[#43474f] italic">
                                                    "{{ $log->remarks }}"
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-10 space-y-2 text-[#43474f]">
                                <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">history_toggle_off</span>
                                <p class="text-sm font-bold text-[#001e40]">No Activity Logged</p>
                                <p class="text-[11px] max-w-xs mx-auto">This Purchase Request has not transitioned states yet. Activities will be logged as signatures are routed.</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end pt-2 border-t border-[#eeedf2]">
                        <button wire:click="closeHistory" class="px-4 py-2 bg-[#eeedf2] hover:bg-[#f4f3f8] text-[#43474f] font-bold text-xs rounded-lg transition-all">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Folder Details Modal --}}
        @if($this->viewingFolder)
            @php
                $vf = $this->viewingFolder;
            @endphp
            <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto">
                <div class="bg-[#f1f3f6] border border-[#eeedf2] rounded-2xl max-w-4xl w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200 relative flex flex-col my-8 h-[80vh]">
                    <!-- Modal Header -->
                    <div class="bg-white px-6 py-4 flex justify-between items-center border-b border-[#eeedf2] rounded-t-2xl flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <h3 class="text-sm font-bold text-[#001e40] uppercase tracking-wider">Purchase Request Details</h3>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                {{ $vf->status === 'APPROVED' ? 'bg-green-50 text-green-700 border border-green-200' : 
                                   (in_array($vf->status, ['CANCELLED', 'CANCELLED_BY_USER']) ? 'bg-red-50 text-red-700 border border-red-200' : 
                                   ($vf->status === 'DRAFT' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200')) }}">
                                {{ $vf->status_label }}
                            </span>
                            <span class="font-mono text-xs text-[#43474f]/70 bg-[#eeedf2]/50 px-2 py-0.5 rounded border border-[#c3c6d1]">{{ $vf->tracking_number }}</span>
                        </div>
                        <button wire:click="closeDetails" class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all">
                            <span class="material-symbols-outlined text-[20px] font-bold">close</span>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="overflow-y-auto px-6 py-5 flex-1 space-y-6">
                        <!-- Metadata Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white p-4 border border-[#eeedf2] rounded-xl space-y-2">
                                <h4 class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/60">Operational Details</h4>
                                <div class="space-y-1.5 text-xs text-[#001e40]">
                                    <div>
                                        <strong class="text-[#43474f]">PR Number:</strong> 
                                        @if($vf->pr_number)
                                            <span class="px-2 py-0.5 bg-blue-100 text-[#001e40] rounded font-bold font-mono text-[11px] border border-blue-200 ml-1">{{ $vf->pr_number }}</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-[#eeedf2] text-[#43474f]/60 rounded italic text-[11px] ml-1">Not assigned</span>
                                        @endif
                                    </div>
                                    <div><strong class="text-[#43474f]">Procurement Method:</strong> {{ $vf->procurement_method ?: 'Shopping' }}</div>
                                    <div><strong class="text-[#43474f]">Created At:</strong> {{ $vf->created_at ? $vf->created_at->format('Y-m-d H:i') : '' }}</div>
                                </div>

                                @php
                                    $uniqueAppLines = $vf->prItems->map(fn($item) => $item->appLineItem)->filter()->unique('id');
                                @endphp
                                @if($uniqueAppLines->isNotEmpty())
                                    <div class="mt-4 pt-3 border-t border-[#eeedf2] space-y-2">
                                        <h5 class="text-[9px] uppercase font-bold tracking-wider text-[#43474f]/60 mb-2">Sourced APP Line Items</h5>
                                        @foreach($uniqueAppLines as $appLine)
                                            <div class="p-2.5 bg-[#f9f9fe] border border-[#eeedf2] rounded-lg space-y-1">
                                                <div class="font-bold text-[11px] text-[#001e40] leading-snug">{{ $appLine->project_title }}</div>
                                                @if($appLine->description)
                                                    <div class="text-[10px] text-[#43474f]/80 leading-relaxed">{{ $appLine->description }}</div>
                                                @endif
                                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-[10px] pt-1.5 font-mono border-t border-[#eeedf2] mt-1.5">
                                                    <div>
                                                        <span class="text-[#43474f]">Approved:</span>
                                                        <span class="font-bold text-[#001e40]">₱{{ number_format($appLine->approved_budget, 2) }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-[#43474f]">Utilized:</span>
                                                        <span class="font-bold text-[#ba1a1a]">₱{{ number_format($appLine->utilized_budget, 2) }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="text-[#43474f]">Available:</span>
                                                        <span class="font-bold text-emerald-700">₱{{ number_format($appLine->approved_budget - $appLine->utilized_budget, 2) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="bg-white p-4 border border-[#eeedf2] rounded-xl space-y-2">
                                <h4 class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/60">Purpose</h4>
                                <p class="text-xs text-[#001e40] italic leading-relaxed">{{ $vf->overall_purpose ?: 'No purpose specified' }}</p>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="bg-white border border-[#c3c6d1] rounded-xl overflow-hidden shadow-2xs">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-[#f9f9fe] border-b border-[#eeedf2]">
                                        <th class="p-3 font-bold text-[#001e40] uppercase tracking-wider">Item Description</th>
                                        <th class="p-3 font-bold text-[#001e40] uppercase tracking-wider text-center">Qty</th>
                                        <th class="p-3 font-bold text-[#001e40] uppercase tracking-wider text-center">Unit</th>
                                        <th class="p-3 font-bold text-[#001e40] uppercase tracking-wider text-right">Unit Price</th>
                                        <th class="p-3 font-bold text-[#001e40] uppercase tracking-wider text-right">Total Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totalCost = 0; @endphp
                                    @forelse($vf->prItems as $item)
                                        @php
                                            $desc = $item->item_description_override ?? $item->appLineItem?->description ?? 'Unknown Particulars';
                                            $cost = $item->estimated_unit_cost ?? $item->unit_cost ?? 0;
                                            $total = $item->total_qty * $cost;
                                            $totalCost += $total;
                                        @endphp
                                        <tr class="border-b border-[#eeedf2]/60 hover:bg-[#f9f9fe]/40">
                                            <td class="p-3 font-medium text-[#001e40]">{{ $desc }}</td>
                                            <td class="p-3 text-center text-[#43474f]">{{ $item->total_qty }}</td>
                                            <td class="p-3 text-center text-[#43474f]">{{ $item->unit ?: 'pcs' }}</td>
                                            <td class="p-3 text-right text-[#43474f]">₱{{ number_format($cost, 2) }}</td>
                                            <td class="p-3 text-right font-bold text-[#001e40]">₱{{ number_format($total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-8 text-center text-[#43474f]/50 italic">No items compiled in this PR.</td>
                                        </tr>
                                    @endforelse
                                    @if($vf->prItems->isNotEmpty())
                                        <tr class="bg-[#f9f9fe]/50 font-bold border-t border-[#eeedf2]">
                                            <td colspan="4" class="p-3 text-right text-[#001e40] uppercase tracking-wider">Total Value</td>
                                            <td class="p-3 text-right text-[#001e40] text-sm">₱{{ number_format($totalCost, 2) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Attachments / Document Package Section -->
                        @if($vf->attachments->isNotEmpty())
                            <div class="space-y-3">
                                <h4 class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/60">Document Package & Attachments</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach($vf->attachments as $attach)
                                        <div class="p-3 bg-white border border-[#eeedf2] rounded-xl flex items-center justify-between text-xs shadow-2xs">
                                            <span class="flex items-center gap-2 truncate pr-2">
                                                <span class="material-symbols-outlined text-[18px] text-[#001e40]/60">
                                                    {{ str_starts_with($attach->attachment_type, 'SYSTEM_') ? 'auto_stories' : 'description' }}
                                                </span>
                                                <span class="truncate font-semibold text-[#001e40]">{{ $attach->original_name }}</span>
                                                <span class="text-[9px] px-1.5 py-0.5 bg-[#eeedf2] text-[#43474f] rounded font-bold uppercase">{{ str_replace('SYSTEM_', '', $attach->attachment_type) }}</span>
                                            </span>
                                            <a href="{{ route('admin.file-stream', $attach->id) }}" target="_blank" class="font-bold text-[#1f477b] underline hover:text-[#001e40] shrink-0">View</a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="bg-white px-6 py-4 border-t border-[#eeedf2] rounded-b-2xl flex justify-between items-center flex-shrink-0">
                        <div class="flex items-center gap-3">
                            @php
                                $user = auth()->user();
                                $empId = $user->employee?->id;
                                $isGsuUser = $user->hasAnyRole(['Admin', 'Procurement Officer']);
                                $isRecommender = ($empId && $empId === $vf->recommended_by_id && !$vf->recommended_signed_at);
                                $isApprover = ($empId && $empId === $vf->approved_by_id && !$vf->approved_signed_at && $vf->recommended_signed_at);
                            @endphp

                            {{-- GSU Acceptance Action --}}
                            @if($vf->status === 'SUBMITTED_TO_GSU' && $isGsuUser)
                                <a href="{{ route('procurement.gsu.review', $vf->id) }}" class="px-4 py-2 bg-[#001e40] hover:bg-[#003272] text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5 active:scale-95">
                                    <span class="material-symbols-outlined text-[16px]">rate_review</span>
                                    Open & Review Workspace
                                </a>
                            @endif


                        </div>
                        <button wire:click="closeDetails" class="px-5 py-2 bg-[#eeedf2] hover:bg-[#c3c6d1] text-[#43474f] font-bold text-xs rounded-xl transition-all active:scale-95">
                            Close Details
                        </button>
                    </div>
                </div>
            </div>
        @endif

</div>
