<?php

use App\Models\ProcurementFolder;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public $search = '';
    public bool $isCreatingPr = false;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    // Action and dialog state
    public ?string $editingFolderId = null;
    public ?string $confirmingDeleteId = null;
    public ?string $rejectingFolderId = null;
    public string $rejectionRemarks = '';
    public ?string $viewingHistoryFolderId = null;

    // Approver/Recommender: inline revision overlay inside the drawer
    public ?string $inlineRevisionFolderId = null;
    public string $inlineRevisionRemarks = '';

    // Approver/Recommender: which drawer row is expanded
    public ?string $expandedDrawerId = null;

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

    public function generateAndViewPdf($prNumber)
    {
        $this->errorMessage = null;
        $folder = ProcurementFolder::where('pr_number', $prNumber)->firstOrFail();
        
        $storagePath = "pr/{$folder->pr_number}.pdf";
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if (!$disk->exists($storagePath)) {
            try {
                if (!$disk->exists('pr')) {
                    $disk->makeDirectory('pr');
                }
                \Spatie\LaravelPdf\Facades\Pdf::view('pdf.pr-form', ['folder' => $folder])
                    ->save($disk->path($storagePath));
            } catch (\Exception $e) {
                $this->errorMessage = 'Failed to generate PR PDF: ' . $e->getMessage();
                return;
            }
        }
        
        // Dispatch browser event to open new tab
        $this->dispatch('open-pdf', url: route('procurement.pr.pdf', $folder->pr_number));
    }

    #[On('open-new-pr')]
    public function openNewPr()
    {
        if (auth()->user()->hasAnyRole(['MSD Head', 'Admin Head'])) {
            abort(403, 'Unauthorized action.');
        }
        $this->editingFolderId = null;
        $this->isCreatingPr = true;
        $this->dispatch('open-pr-creation');
    }

    public function editPr($id)
    {
        if (auth()->user()->hasAnyRole(['MSD Head', 'Admin Head'])) {
            abort(403, 'Unauthorized action.');
        }
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->editingFolderId = $id;
        $this->isCreatingPr = true;
        
        $this->dispatch('open-pr-creation', folderId: $id);
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

            \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => $hasRejection ? 'RESUBMITTED' : 'SUBMITTED',
                'actor_id' => $actor->id,
                'remarks' => $hasRejection ? 'PR resubmitted for approval.' : 'PR submitted & routed for approval.',
            ]);
        });

        $this->successMessage = "PR submitted & routed for signature successfully!";
    }

    public function approvePr($id)
    {
        $folder = ProcurementFolder::findOrFail($id);

        $actor = auth()->user()->employee;
        if (!$actor) {
            $this->errorMessage = "System error: Your account is not linked to an Employee record. Please contact the administrator.";
            return;
        }

        DB::transaction(function () use ($folder, $actor) {
            $isRecommender = $folder->recommended_by_id === $actor->id;
            $isApprover = $folder->approved_by_id === $actor->id;

            $updates = [];
            $action = '';
            $remarks = '';

            if ($isRecommender && !$folder->recommended_signed_at) {
                $updates['recommended_signed_at'] = now();
                $action = 'RECOMMENDED';
                $remarks = 'PR recommended and routed to Approving Officer.';
                $this->successMessage = "PR recommended successfully!";
            }

            if ($isApprover) {
                $updates['status'] = 'APPROVED';
                $updates['approved_signed_at'] = now();
                $action = 'APPROVED';
                $remarks = 'PR approved and locked for COA auditing.';
                $this->successMessage = "PR approved successfully and permanently locked!";
            }

            if (!empty($updates)) {
                $folder->update($updates);
                \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");
                \App\Models\ProcurementLog::create([
                    'procurement_folder_id' => $folder->id,
                    'action' => $action,
                    'actor_id' => $actor->id,
                    'remarks' => $remarks,
                ]);
            }
        });
    }

    public function startRejection($id)
    {
        $this->rejectingFolderId = $id;
        $this->rejectionRemarks = '';
    }

    public function rejectPr()
    {
        $this->validate([
            'rejectionRemarks' => 'required|string|max:1000',
        ]);

        $folder = ProcurementFolder::findOrFail($this->rejectingFolderId);

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

            \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => 'REJECTED',
                'actor_id' => $actor->id,
                'remarks' => $this->rejectionRemarks,
            ]);
        });

        $this->successMessage = "PR has been returned to DRAFT with feedback.";
        $this->rejectingFolderId = null;
        $this->rejectionRemarks = '';
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
    }

    public function deletePr()
    {
        $folder = ProcurementFolder::findOrFail($this->confirmingDeleteId);

        DB::transaction(function () use ($folder) {
            \App\Models\CobItemDistribution::whereHas('prItem', function($q) use ($folder) {
                $q->where('folder_id', $folder->id);
            })->update([
                'pr_item_id' => null,
                'procured_quantity' => 0,
            ]);

            \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");

            $folder->delete();
        });

        $this->successMessage = "PR has been deleted and distributions released.";
        $this->confirmingDeleteId = null;
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

            \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");

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

    public function with(): array
    {
        $user = auth()->user();
        $employee = $user->employee;
        $employeeId = $employee?->id;

        $isAdmin = $user->hasRole('Admin');
        $isApprover = $user->hasAnyRole(['MSD Head', 'Admin Head']);

        $query = ProcurementFolder::with(['purchaseOrder', 'prItems']);

        // Enforce RBAC Database Scoping
        if ($isAdmin) {
            // Admin sees everything
        } elseif ($isApprover) {
            // Approver Inbox: only ROUTING status, where they are designated as the approver or recommender
            $query->where('status', 'ROUTING')
                  ->where(function($q) use ($employeeId) {
                      $q->where('approved_by_id', $employeeId)
                        ->orWhere('recommended_by_id', $employeeId);
                  });
        } else {
            // Recommender/Compiler: folders matching their unit or created by them
            $query->where(function ($q) use ($employeeId, $employee) {
                $q->where('requested_by_id', $employeeId);
                if ($employee?->office_division) {
                    $q->orWhere('requesting_unit', $employee->office_division);
                }
            });
        }

        // Apply Search Filter inside a nested closure to respect the RBAC scoping
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
            });
        }

        $folders = $query->orderBy('created_at', 'desc')->paginate(10);

        // Enforce RBAC KPI Scoping
        if ($isAdmin) {
            $totalActive = ProcurementFolder::whereNotIn('status', ['PO_RELEASED'])->count();
            $totalPending = ProcurementFolder::where('status', 'DRAFT')->count();
        } elseif ($isApprover) {
            $totalActive = ProcurementFolder::where('status', 'ROUTING')
                ->where(function($q) use ($employeeId) {
                    $q->where('approved_by_id', $employeeId)->orWhere('recommended_by_id', $employeeId);
                })->count();
            $totalPending = $totalActive; // pending approval
        } else {
            $totalActive = ProcurementFolder::whereNotIn('status', ['PO_RELEASED'])
                ->where(function ($q) use ($employeeId, $employee) {
                    $q->where('requested_by_id', $employeeId);
                    if ($employee?->office_division) {
                        $q->orWhere('requesting_unit', $employee->office_division);
                    }
                })->count();
            $totalPending = ProcurementFolder::where('status', 'DRAFT')
                ->where(function ($q) use ($employeeId, $employee) {
                    $q->where('requested_by_id', $employeeId);
                    if ($employee?->office_division) {
                        $q->orWhere('requesting_unit', $employee->office_division);
                    }
                })->count();
        }

        // Approver-specific KPIs: total pending value of ROUTING PRs assigned to them
        $approverPendingValue = 0;
        if ($isApprover && $employeeId) {
            $approverPendingValue = ProcurementFolder::where('status', 'ROUTING')
                ->where(function($q) use ($employeeId) {
                    $q->where('approved_by_id', $employeeId)
                      ->orWhere('recommended_by_id', $employeeId);
                })
                ->with('prItems')
                ->get()
                ->sum(fn($f) => $f->prItems->sum(fn($i) => $i->estimated_total_cost));
        }

        return [
            'folders'              => $folders,
            'totalActive'          => $totalActive,
            'totalPending'         => $totalPending,
            'totalValue'           => \App\Models\PurchaseOrder::sum('total_amount') ?? 0,
            'avgTurnaround'        => 0,
            'isApprover'           => $isApprover,
            'isAdmin'              => $isAdmin,
            'approverPendingValue' => $approverPendingValue,
        ];
    }
}; ?>

<div>
    @section('header_title', $isCreatingPr ? 'Compile Purchase Request' : 'Procurement')

    @push('header_actions')
        @if($isCreatingPr)
            <x-primary-button variant="secondary" icon="arrow_back" x-on:click="$dispatch('close-pr-creation')">Back to Registry</x-primary-button>
        @elseif(!auth()->user()->hasAnyRole(['MSD Head', 'Admin Head']))
            <x-primary-button icon="add" x-on:click="$dispatch('open-new-pr')">New PR</x-primary-button>
        @endif
    @endpush

        <div class="p-container-padding bg-background space-y-6" 
             x-data="{ isCreatingPr: $wire.entangle('isCreatingPr') }"
             x-on:open-pdf.window="window.open($event.detail.url, '_blank')">

            {{-- PR Registry Workspace --}}
            <div x-show="!isCreatingPr"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-4"
                 class="space-y-6">

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

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- APPROVER / RECOMMENDER INBOX VIEW                               --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @if($isApprover)

        {{-- Approver KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            {{-- PRs Awaiting Signature --}}
            <div class="bg-[#001e40] text-white p-gutter rounded-xl shadow-sm flex flex-col justify-between">
                <span class="material-symbols-outlined text-4xl opacity-40" style="font-variation-settings:'FILL' 1;">signature</span>
                <div class="mt-4">
                    <p class="text-3xl font-bold tracking-tight">{{ $totalPending }}</p>
                    <p class="text-[11px] font-bold uppercase tracking-wider opacity-70 mt-1">PRs Awaiting Signature</p>
                </div>
            </div>
            {{-- Total Pending Value --}}
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between">
                <span class="material-symbols-outlined text-[28px] text-[#1f477b] opacity-80" style="font-variation-settings:'FILL' 1;">payments</span>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-[#001e40] tracking-tight">
                        @if($approverPendingValue >= 1000000)
                            ₱{{ number_format($approverPendingValue / 1000000, 2) }}M
                        @elseif($approverPendingValue >= 1000)
                            ₱{{ number_format($approverPendingValue / 1000, 1) }}K
                        @else
                            ₱{{ number_format($approverPendingValue, 2) }}
                        @endif
                    </p>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#43474f] mt-1">Total Pending Value</p>
                </div>
            </div>
            {{-- Budget Compliance --}}
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between">
                <span class="material-symbols-outlined text-[28px] text-green-600 opacity-80" style="font-variation-settings:'FILL' 1;">verified_user</span>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-green-700 tracking-tight">100%</p>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#43474f] mt-1">Budget Compliance</p>
                </div>
            </div>
            {{-- Avg Approval Time --}}
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between">
                <span class="material-symbols-outlined text-[28px] text-[#43474f] opacity-80" style="font-variation-settings:'FILL' 1;">schedule</span>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-[#001e40] tracking-tight">{{ $avgTurnaround > 0 ? $avgTurnaround.'d' : '—' }}</p>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#43474f] mt-1">Avg. Approval Time</p>
                </div>
            </div>
        </div>

        {{-- Approver Inbox Table --}}
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative">
            <div wire:loading class="absolute inset-x-0 bottom-0 top-[53px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center">
                <div class="flex flex-col items-center gap-2">
                    <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                    <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
                </div>
            </div>

            {{-- Table Header Bar --}}
            <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="font-bold text-[#001e40] text-lg">Approval Inbox</h3>
                    <p class="text-[11px] text-[#43474f] mt-0.5">PRs routed to you for final signature — review line items before approving.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search PR or unit..." class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all w-56 placeholder-[#43474f]/40"/>
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">PR Number</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Submitted</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Requesting Unit</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Total Amount</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        @forelse($folders as $folder)
                        {{-- Main PR Row --}}
                        <tr class="hover:bg-[#f4f3f8] transition-colors border-b border-[#c3c6d1] {{ $expandedDrawerId === $folder->id ? 'bg-[#f4f3f8] border-b-0' : '' }}">
                            <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $folder->pr_number ?? '—' }}</td>
                            <td class="p-table-cell-padding text-[#43474f]">{{ $folder->created_at->format('M d, Y') }}</td>
                            <td class="p-table-cell-padding text-[#1a1c1f]">
                                {{ $folder->requesting_unit ?? $folder->overall_purpose ?? '—' }}
                            </td>
                            <td class="p-table-cell-padding font-bold text-[#001e40]">
                                @php $totalAmt = $folder->prItems->sum('estimated_total_cost'); @endphp
                                ₱{{ number_format($totalAmt, 2) }}
                            </td>
                            <td class="p-table-cell-padding text-right">
                                <div class="flex justify-end items-center gap-2">
                                    <button
                                        wire:click="toggleDrawer('{{ $folder->id }}')"
                                        class="px-3 py-1.5 bg-[#001e40] text-white text-[11px] font-bold rounded-lg hover:bg-[#001e40]/90 transition-all flex items-center gap-1.5"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">{{ $expandedDrawerId === $folder->id ? 'keyboard_arrow_up' : 'rate_review' }}</span>
                                        {{ $expandedDrawerId === $folder->id ? 'Collapse' : 'Review' }}
                                    </button>
                                    <button
                                        wire:click="approvePr('{{ $folder->id }}')"
                                        wire:confirm="Approve this PR and lock it for COA auditing?"
                                        class="p-1.5 bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition-all material-symbols-outlined text-[18px]"
                                        title="Quick Approve"
                                        style="font-variation-settings:'FILL' 1;"
                                    >done_all</button>
                                    <button
                                        wire:click="viewHistory('{{ $folder->id }}')"
                                        class="p-1.5 bg-[#f4f3f8] text-[#43474f] border border-[#c3c6d1] rounded-lg hover:bg-[#eeedf2] transition-all material-symbols-outlined text-[18px]"
                                        title="Audit Trail"
                                    >history</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Expandable Drawer Row --}}
                        @if($expandedDrawerId === $folder->id)
                        <tr class="bg-[#f9f9fe] border-b border-[#c3c6d1] border-l-4 border-l-[#001e40]">
                            <td colspan="5" class="px-8 py-6">
                                <div class="flex flex-col lg:flex-row gap-8">

                                    {{-- Line Items Section --}}
                                    <div class="flex-1 space-y-4">
                                        <div class="flex items-center justify-between border-b border-[#c3c6d1] pb-3">
                                            <h4 class="text-base font-bold text-[#001e40] flex items-center gap-2">
                                                <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                                                Line Items Review
                                            </h4>
                                            {{-- PDF Preview Link --}}
                                            <button
                                                wire:click="generateAndViewPdf('{{ $folder->pr_number }}')"
                                                class="inline-flex items-center gap-1.5 text-[11px] bg-[#001e40]/8 text-[#001e40] hover:bg-[#001e40]/15 px-3 py-1.5 rounded-lg font-bold transition-all border border-[#001e40]/10"
                                            >
                                                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings:'FILL' 1;">picture_as_pdf</span>
                                                View Official Form
                                            </button>
                                        </div>

                                        <table class="w-full text-sm">
                                            <thead class="text-[#43474f] border-b border-[#c3c6d1]">
                                                <tr>
                                                    <th class="py-2 text-left text-[11px] font-bold uppercase tracking-wider ">Item Description</th>
                                                    <th class="py-2 text-center text-[11px] font-bold uppercase tracking-wider">Qty</th>
                                                    <th class="py-2 text-right text-[11px] font-bold uppercase tracking-wider">Unit Cost</th>
                                                    <th class="py-2 text-right text-[11px] font-bold uppercase tracking-wider">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-[#eeedf2]">
                                                @forelse($folder->prItems as $item)
                                                <tr >
                                                    <td class="py-2.5 text-[#1a1c1f] max-w-[350px] whitespace-normal break-words">
                                                        {{ $item->item_description_override ?? $item->cobItem?->full_particulars ?? '—' }}
                                                    </td>
                                                    <td class="py-2.5 text-center text-[#1a1c1f]">{{ $item->total_qty }}</td>
                                                    <td class="py-2.5 text-right text-[#1a1c1f]">₱{{ number_format($item->estimated_unit_cost, 2) }}</td>
                                                    <td class="py-2.5 text-right font-bold text-[#001e40]">₱{{ number_format($item->estimated_total_cost, 2) }}</td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="4" class="py-6 text-center text-[11px] text-[#43474f]/60">No line items found.</td></tr>
                                                @endforelse
                                            </tbody>
                                            <tfoot class="border-t-2 border-[#001e40]/20">
                                                <tr>
                                                    <td colspan="3" class="py-3 text-right text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Total PR Amount:</td>
                                                    <td class="py-3 text-right text-base font-bold text-[#001e40]">₱{{ number_format($folder->prItems->sum('estimated_total_cost'), 2) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        {{-- Purpose / Justification --}}
                                        @if($folder->overall_purpose || $folder->project_title)
                                        <div class="p-4 bg-[#eeedf2]/60 rounded-xl border border-[#c3c6d1]">
                                            <p class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider">Purpose / Justification</p>
                                            <p class="text-sm text-[#1a1c1f] mt-1 italic leading-relaxed">{{ $folder->overall_purpose ?? $folder->project_title }}</p>
                                        </div>
                                        @endif

                                        {{-- Inline Revision Overlay --}}
                                        @if($inlineRevisionFolderId === $folder->id)
                                        <div class="mt-4 p-5 bg-red-50 border border-red-200 rounded-xl space-y-3 animate-in fade-in duration-200">
                                            <p class="text-[11px] font-bold text-[#ba1a1a] uppercase tracking-wider flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-[16px]">edit_note</span>
                                                Corrective Feedback / Revision Remarks
                                            </p>
                                            <textarea
                                                wire:model="inlineRevisionRemarks"
                                                placeholder="Describe what needs to be corrected. The PR will be returned to DRAFT and the compiler will be notified..."
                                                class="w-full px-4 py-3 bg-white border border-red-200 rounded-lg text-sm focus:ring-2 focus:ring-[#ba1a1a]/40 outline-none transition-all resize-none min-h-[100px] text-[#1a1c1f]"
                                                autofocus
                                            ></textarea>
                                            @error('inlineRevisionRemarks')
                                                <p class="text-[11px] text-[#ba1a1a] font-bold">{{ $message }}</p>
                                            @enderror
                                            <div class="flex gap-2 justify-end">
                                                <button wire:click="cancelInlineRevision" class="px-4 py-2 text-[11px] font-bold text-[#43474f] hover:bg-red-100 rounded-lg transition-all">Cancel</button>
                                                <button wire:click="submitInlineRevision" class="px-4 py-2 text-[11px] font-bold bg-[#ba1a1a] text-white rounded-lg hover:bg-[#ba1a1a]/90 transition-all flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-[14px]">assignment_return</span>
                                                    Return with Edits
                                                </button>
                                            </div>
                                        </div>
                                        @endif
                                    </div>

                                    {{-- Approval Action Panel --}}
                                    <div class="w-full lg:w-60 flex flex-col gap-2.5 justify-start pt-1">
                                        <p class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider mb-1">Your Decision</p>

                                        {{-- Approve --}}
                                        <button
                                            wire:click="approvePr('{{ $folder->id }}')"
                                            wire:confirm="Approve this PR and permanently lock it for COA auditing?"
                                            class="w-full py-3 bg-[#001e40] text-white font-bold text-sm rounded-xl flex items-center justify-center gap-2 hover:bg-[#001e40]/90 transition-all shadow-sm"
                                        >
                                            <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">check_circle</span> Approve
                                        </button>

                                        {{-- Recommend Revision --}}
                                        @if($inlineRevisionFolderId !== $folder->id)
                                        <button
                                            wire:click="startInlineRevision('{{ $folder->id }}')"
                                            class="w-full py-3 border-2 border-[#001e40] text-[#001e40] font-bold text-sm rounded-xl flex items-center justify-center gap-2 hover:bg-[#001e40]/5 transition-all"
                                        >
                                            <span class="material-symbols-outlined">edit_note</span> Recommend Revision
                                        </button>
                                        @else
                                        <button
                                            wire:click="cancelInlineRevision"
                                            class="w-full py-3 border-2 border-[#001e40]/30 text-[#43474f] font-bold text-sm rounded-xl flex items-center justify-center gap-2 hover:bg-[#eeedf2] transition-all"
                                        >
                                            <span class="material-symbols-outlined">close</span> Cancel Revision
                                        </button>
                                        @endif

                                        {{-- Disapprove (full rejection modal) --}}
                                        <button
                                            wire:click="startRejection('{{ $folder->id }}')"
                                            class="w-full py-3 border-2 border-[#ba1a1a] text-[#ba1a1a] font-bold text-sm rounded-xl flex items-center justify-center gap-2 hover:bg-red-50 transition-all"
                                        >
                                            <span class="material-symbols-outlined">cancel</span> Disapprove
                                        </button>

                                        <div class="h-px bg-[#eeedf2] my-1"></div>

                                        {{-- View Audit Trail --}}
                                        <button
                                            wire:click="viewHistory('{{ $folder->id }}')"
                                            class="w-full py-2 text-[#43474f] text-[11px] font-bold hover:text-[#001e40] hover:underline flex items-center justify-center gap-1.5 transition-all"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">history</span> View Audit Trail
                                        </button>

                                        {{-- Collapse --}}
                                        <button
                                            wire:click="toggleDrawer('{{ $folder->id }}')"
                                            class="w-full py-2 text-[#43474f] text-[11px] font-bold hover:underline flex items-center justify-center gap-1.5 transition-all"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">keyboard_arrow_up</span> Collapse View
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif

                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                    <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">inbox</span>
                                    @if($search)
                                        <p class="font-bold text-[#001e40] text-lg">No Results Found</p>
                                        <p class="text-[13px] text-[#43474f] max-w-xs">No PRs matching "{{ $search }}" in your approval queue.</p>
                                    @else
                                        <p class="font-bold text-[#001e40] text-lg">Inbox is Clear</p>
                                        <p class="text-[13px] text-[#43474f] max-w-xs">There are no Purchase Requests currently awaiting your approval signature.</p>
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
                    <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing {{ $folders->count() }} of {{ $folders->total() }} Pending Requests</p>
                </div>
            @endif
        </div>

        {{-- Approver Insight Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
            {{-- Compliance Card --}}
            <div class="bg-white p-8 border border-[#c3c6d1] rounded-2xl shadow-sm flex gap-6 items-center">
                <div class="w-20 h-20 rounded-2xl bg-[#d5e3ff]/40 border border-[#001e40]/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[40px] text-[#001e40]" style="font-variation-settings:'FILL' 1;">verified_user</span>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-bold text-[#001e40]">Regional Compliance Check</h4>
                    <p class="text-sm text-[#43474f] mt-2 leading-relaxed">
                        All pending PRs have passed the automated budget availability check. Final signatures are required for regional disbursement authority under <strong>Circular 2026-015</strong>.
                    </p>
                    <a href="#" class="inline-block mt-3 text-[#001e40] text-[11px] font-bold hover:underline">View Compliance Logs →</a>
                </div>
            </div>
            {{-- Approver Tip Card --}}
            <div class="bg-[#d5e3ff]/20 p-8 border border-[#001e40]/10 rounded-2xl flex flex-col justify-center relative overflow-hidden group">
                <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-[120px] text-[#001e40]/5 group-hover:scale-110 transition-transform duration-700" style="font-variation-settings:'FILL' 1;">tips_and_updates</span>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-[#001e40]" style="font-variation-settings:'FILL' 1;">info</span>
                        <h4 class="text-base font-bold text-[#001e40]">Approver Tip</h4>
                    </div>
                    <p class="text-sm text-[#1a1c1f] leading-relaxed">
                        Click <strong>Review</strong> to expand the line items drawer and verify the Spatie PDF output before signing. Use <strong>Recommend Revision</strong> to log corrective feedback inline — remarks are permanently recorded to the audit trail.
                    </p>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- RECOMMENDER / COMPILER / ADMIN VIEW (non-approver)             --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @else

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

        {{-- PR Table --}}
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative">
            <div wire:loading class="absolute inset-x-0 bottom-0 top-[45px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
                <div class="flex flex-col items-center gap-2">
                    <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                    <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
                </div>
            </div>

            <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                <h3 class="font-bold text-[#001e40] text-lg">Purchase Request Tracker</h3>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search PR or supplier..." class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all w-56 placeholder-[#43474f]/40"/>
                    </div>
                    <x-primary-button variant="secondary" icon="filter_list" class="!px-3" />
                    <x-primary-button variant="secondary" icon="download" class="!px-3" />
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">PR Number</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Requested</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Supplier Name</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider w-56">Delivery Progress</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                        @forelse($folders as $folder)
                        <tr class="hover:bg-[#f4f3f8] transition-colors">
                            <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $folder->pr_number ?? '—' }}</td>
                            <td class="p-table-cell-padding text-[#1a1c1f]">{{ $folder->created_at->format('M d, Y') }}</td>
                            <td class="p-table-cell-padding text-[#1a1c1f]">{{ $folder->purchaseOrder?->supplier?->name ?? '—' }}</td>
                            <td class="p-table-cell-padding">
                                @php
                                    $statusColors = [
                                        'DRAFT' => 'bg-[#eeedf2] text-[#43474f]',
                                        'ROUTING' => 'bg-[#fff9c4] text-[#f57f17] border border-[#fbc02d]/30',
                                        'APPROVED' => 'bg-green-50 text-green-800 border border-green-200',
                                        'PR_PRINTED' => 'bg-[#ffdbca] text-[#341100]',
                                        'RFQ_SENT' => 'bg-[#d8e1ea] text-[#5b646b]',
                                        'AWARDED' => 'bg-green-100 text-green-800',
                                        'PO_RELEASED' => 'bg-[#d5e3ff] text-[#001b3c]',
                                    ];
                                    $color = $statusColors[$folder->status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $color }}">{{ str_replace('_', ' ', $folder->status) }}</span>
                            </td>
                            <td class="p-table-cell-padding">
                                @php 
                                    // Placeholder delivery logic
                                    $totalItems = $folder->prItems->sum('total_qty');
                                    $delivered = $folder->status === 'PO_RELEASED' ? $totalItems : 0; 
                                    $pct = $totalItems > 0 ? round(($delivered/$totalItems)*100) : 0; 
                                @endphp
                                <div class="space-y-1">
                                    <div class="flex justify-between text-[10px] font-bold text-[#43474f]">
                                        <span>{{ $delivered }} / {{ $totalItems }} Units</span>
                                        <span>{{ $pct }}%</span>
                                    </div>
                                    <div class="w-full h-2 bg-[#eeedf2] rounded-full overflow-hidden">
                                        <div class="h-full bg-[#001e40] rounded-full transition-all duration-700" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
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
                                                    {{-- View PDF (Always Available) --}}
                                                    <button wire:click="generateAndViewPdf('{{ $folder->pr_number }}')" wire:loading.attr="disabled" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all relative whitespace-nowrap">
                                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                                        <span>View PDF Form</span>
                                                    </button>

                                                    {{-- Audit Log History (Always Available) --}}
                                                    <button wire:click="viewHistory('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                        <span class="material-symbols-outlined text-[18px]">history</span>
                                                        <span>View Audit Trail</span>
                                                    </button>

                                                    @if($folder->status === 'DRAFT' && !auth()->user()->hasAnyRole(['MSD Head', 'Admin Head']))
                                                        <div class="h-px bg-[#eeedf2] my-1"></div>
                                                        {{-- Route for Signature --}}
                                                        <button wire:click="submitForApproval('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#1f477b] hover:bg-blue-50 rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">send</span>
                                                            <span>Submit & Route</span>
                                                        </button>

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

                                                    @if($folder->status === 'ROUTING' && auth()->user()->hasAnyRole(['Admin', 'MSD Head', 'Admin Head']))
                                                        <div class="h-px bg-[#eeedf2] my-1"></div>
                                                        {{-- Approve --}}
                                                        <button wire:click="approvePr('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-green-700 hover:bg-green-50 rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                                            <span>{{ $folder->recommended_by_id === auth()->user()->employee_id && $folder->approved_by_id !== auth()->user()->employee_id ? 'Recommend PR' : 'Approve & Lock' }}</span>
                                                        </button>

                                                        {{-- Return with Edits --}}
                                                        <button wire:click="startRejection('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#ba1a1a] hover:bg-red-50 rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">assignment_return</span>
                                                            <span>Return with Edits</span>
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
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                    <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">receipt_long</span>
                                    @if($search)
                                        <p class="font-bold text-[#001e40] text-lg">No Results Found</p>
                                        <p class="text-[13px] text-[#43474f] max-w-xs">We couldn't find any procurement folders matching "{{ $search }}".</p>
                                    @else
                                        <p class="font-bold text-[#001e40] text-lg">No Purchase Requests Found</p>
                                        <p class="text-[13px] text-[#43474f] max-w-xs">There are no procurement requests yet. Create the first one to start tracking your purchasing pipeline.</p>
                                        <x-primary-button icon="add" class="mt-2" x-on:click="isCreatingPr = true">Create First PR</x-primary-button>
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
        </div>

        @endif {{-- end @if($isApprover) ... @else --}}

        {{-- PR Compiler Workspace --}}
        <div x-show="isCreatingPr"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-4"
             class="space-y-5">
             <livewire:procurement.pr-compiler :folder-id="$editingFolderId" :key="$editingFolderId ?? 'new-pr'" />
        </div>

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

        {{-- Rejection Modal --}}
        @if($rejectingFolderId)
            <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
                <div class="bg-white border border-[#eeedf2] rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
                    <div class="flex items-center gap-3 text-[#ba1a1a]">
                        <span class="material-symbols-outlined text-[28px]">assignment_return</span>
                        <h4 class="text-lg font-bold">Return Purchase Request with Edits</h4>
                    </div>
                    <p class="text-xs text-[#43474f] leading-relaxed">
                        Provide specific correction remarks or instructions for the requesting user. The PR folder status will degrade back to <strong>DRAFT</strong> and fields will be unlocked.
                    </p>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f]">Corrective Feedback / Remarks <span class="text-[#ba1a1a]">*</span></label>
                        <textarea wire:model="rejectionRemarks" placeholder="Enter corrective feedback here..." class="w-full px-4 py-3 bg-[#f9f9fe] border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all resize-none min-h-[120px]"></textarea>
                        @error('rejectionRemarks') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button wire:click="$set('rejectingFolderId', null)" class="px-4 py-2 bg-white border border-[#c3c6d1] hover:bg-[#f4f3f8] text-[#43474f] font-bold text-xs rounded-lg transition-all">
                            Cancel
                        </button>
                        <button wire:click="rejectPr" class="px-4 py-2 bg-[#ba1a1a] hover:bg-[#ba1a1a]/90 text-white font-bold text-xs rounded-lg shadow-sm transition-all text-center">
                            Return with Edits
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
                            Audit Trail: {{ $historyFolder?->pr_number ?? 'PR History' }}
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

    </div>
</div>
