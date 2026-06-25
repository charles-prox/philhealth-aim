<?php

use App\Models\ProcurementFolder;
use App\Models\AppHeader;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        if (!auth()->user()->hasAnyRole(['Document custodian', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }
    }

    public $search = '';
    public bool $isCreatingPr = false;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    // Action and dialog state
    public ?string $editingFolderId = null;
    public ?string $confirmingDeleteId = null;
    public ?string $viewingHistoryFolderId = null;

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
        
        $identifier = $folder->pr_number ?: $folder->tracking_number;
        $storagePath = "pr/{$identifier}.pdf";
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
        $this->dispatch('open-pdf', url: route('procurement.pr.pdf', $folder->id));
    }

    #[On('open-new-pr')]
    public function openNewPr()
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Document custodian'])) {
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
        if (!auth()->user()->hasAnyRole(['Admin', 'Document custodian'])) {
            abort(403, 'Unauthorized action.');
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
        $folder = ProcurementFolder::findOrFail($id);

        // Verify ownership/guard
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
                'status' => 'SUBMITTED_TO_GSU', // Routes directly to GSU Triage
                'requested_signed_at' => now(),
            ]);

            if ($folder->pr_number) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");
            }

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => $hasRejection ? 'RESUBMITTED' : 'SUBMITTED',
                'actor_id' => $actor->id,
                'remarks' => $hasRejection ? 'PR resubmitted to GSU Triage.' : 'PR submitted to GSU Triage.',
            ]);
        });

        $this->successMessage = "PR submitted to GSU Triage successfully!";
    }

    public function cancelSubmission($id)
    {
        $folder = ProcurementFolder::findOrFail($id);
        $user = auth()->user();
        if (!$user->hasRole('Admin') && $folder->requested_by_id !== $user->employee_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($folder->status !== 'SUBMITTED_TO_GSU') {
            $this->errorMessage = "Only submitted PRs that have not been triaged can be cancelled.";
            return;
        }

        DB::transaction(function () use ($folder) {
            $folder->update([
                'status' => 'DRAFT',
            ]);

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => 'REJECTED',
                'actor_id' => auth()->user()->employee_id,
                'remarks' => 'Submission cancelled by requester. Returned to DRAFT.',
            ]);
        });

        $this->successMessage = "Submission cancelled. PR returned to drafts.";
    }

    public function confirmDelete($id)
    {
        $folder = ProcurementFolder::findOrFail($id);
        $user = auth()->user();
        if (!$user->hasRole('Admin') && $folder->requested_by_id !== $user->employee_id) {
            abort(403, 'Unauthorized action.');
        }
        $this->confirmingDeleteId = $id;
    }

    public function deletePr()
    {
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

            // Release APP Line Item utilized budget
            foreach ($folder->prItems as $item) {
                if ($item->app_line_item_id) {
                    $appLineItem = \App\Models\AppLineItem::find($item->app_line_item_id);
                    if ($appLineItem) {
                        $appLineItem->decrement('utilized_budget', $item->estimated_total_cost);
                    }
                }
            }

            if ($folder->pr_number) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete("pr/{$folder->pr_number}.pdf");
            }

            $folder->delete();
        });

        $this->successMessage = "PR has been deleted and distributions released.";
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

        $query = ProcurementFolder::with(['purchaseOrder', 'prItems']);

        // Enforce RBAC Database Scoping
        if (!$isAdmin) {
            // Document Custodian: only see PRs they personally created via true foreign key column
            $query->where('requested_by_id', $employeeId);
        }

        // Apply Search Filter inside a nested closure to respect the scoping
        if ($this->search) {
            $query->where(function($q) {
                $q->where(DB::raw('LOWER(pr_number)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(overall_purpose)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(project_title)'), 'like', '%' . strtolower($this->search) . '%');
            });
        }

        $folders = $query->orderBy('created_at', 'desc')->paginate(10);

        // Enforce RBAC KPI Scoping
        if (!$isAdmin) {
            $totalActive = ProcurementFolder::whereNotIn('status', ['PO_RELEASED'])
                ->where('requested_by_id', $employeeId)
                ->count();
            $totalPending = ProcurementFolder::where('status', 'DRAFT')
                ->where('requested_by_id', $employeeId)
                ->count();
        } else {
            $totalActive = ProcurementFolder::whereNotIn('status', ['PO_RELEASED'])->count();
            $totalPending = ProcurementFolder::where('status', 'DRAFT')->count();
        }

        // Check if APP gate is cleared for the current year
        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $appGateCleared = AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->exists();

        return [
            'folders'              => $folders,
            'totalActive'          => $totalActive,
            'totalPending'         => $totalPending,
            'appGateCleared'       => $appGateCleared,
            'currentYear'          => $currentYear,
        ];
    }
}; ?>

<div>
    @section('header_title', $isCreatingPr ? 'Compile Purchase Request' : 'Procurement Portal')

    @push('header_actions')
        @if($isCreatingPr)
            <x-primary-button variant="secondary" icon="arrow_back" x-on:click="$dispatch('close-pr-creation')">Back to Registry</x-primary-button>
        @elseif($appGateCleared)
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

            {{-- PR Table --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative">
                <div wire:loading class="absolute inset-x-0 bottom-0 top-[45px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                        <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
                    </div>
                </div>

                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                    <h3 class="font-bold text-[#001e40] text-lg">Personal PR Tracker</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search PR or purpose..." class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all w-56 placeholder-[#43474f]/40"/>
                        </div>
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
                                <td class="p-table-cell-padding text-[#1a1c1f] max-w-[250px] truncate" title="{{ $folder->overall_purpose ?? $folder->project_title }}">{{ $folder->overall_purpose ?? $folder->project_title ?? '—' }}</td>
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
                                        ];
                                        $color = $statusColors[$folder->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $color }}">{{ str_replace('_', ' ', $folder->status) }}</span>
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
                                                    <button wire:click="generateAndViewPdf('{{ $folder->id }}')" wire:loading.attr="disabled" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all relative whitespace-nowrap">
                                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                                        <span>View PDF Form</span>
                                                    </button>

                                                    {{-- Audit Log History (Always Available) --}}
                                                    <button wire:click="viewHistory('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                        <span class="material-symbols-outlined text-[18px]">history</span>
                                                        <span>View Audit Trail</span>
                                                    </button>

                                                    @if($folder->status === 'SUBMITTED_TO_GSU')
                                                        <div class="h-px bg-[#eeedf2] my-1"></div>
                                                        {{-- Cancel Submission --}}
                                                        <button wire:click="cancelSubmission('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#ba1a1a] hover:bg-red-50 rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">cancel</span>
                                                            <span>Cancel Submission</span>
                                                        </button>
                                                    @endif

                                                    @if($folder->status === 'DRAFT')
                                                        <div class="h-px bg-[#eeedf2] my-1"></div>
                                                        {{-- Route for Signature --}}
                                                        <button wire:click="submitForApproval('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#1f477b] hover:bg-blue-50 rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">send</span>
                                                            <span>Submit to GSU</span>
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
                                            <x-primary-button icon="add" class="mt-2" x-on:click="$dispatch('open-new-pr')">Create First PR</x-primary-button>
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
        </div>

        @if($isCreatingPr)
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
                 <livewire:procurement.end-user-portal :folder-id="$editingFolderId" :key="$editingFolderId ?? 'new-pr'" />
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

    </div>
</div>
