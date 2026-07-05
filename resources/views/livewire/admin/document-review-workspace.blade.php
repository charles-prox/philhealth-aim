<?php

use Livewire\Volt\Component;
use App\Models\ApprovalTask;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public ApprovalTask $task;
    public $rejectionType = ''; // 'EDIT', 'COMPLIANCE', 'PERMANENT'
    public $rejectionRemarks = '';
    public int $activeTab = 0;
    
    public bool $isBudgetOfficer = false;
    public string $budgetPpaCode = '';
    public string $budgetCode = '';

    public bool $hasOlderPending = false;
    public ?ApprovalTask $oldestPendingTask = null;

    public function mount($taskId)
    {
        $employeeId = auth()->user()->employee_id;
        if (!$employeeId || !\App\Models\SignatoryRegistry::isEmployeeSignatory($employeeId)) {
            abort(403, "Access Denied: You are not registered in the Signatory Matrix.");
        }

        $this->task = ApprovalTask::findOrFail($taskId);

        $this->oldestPendingTask = ApprovalTask::where('target_employee_id', $employeeId)
            ->where('status', 'PENDING')
            ->oldest()
            ->first();

        if ($this->oldestPendingTask && $this->oldestPendingTask->id !== $this->task->id) {
            $this->hasOlderPending = true;
        }

        $this->isBudgetOfficer = ($employeeId === \App\Models\SignatoryRegistry::getActiveSignatoryFor('BUDGET_OFFICER'));
        if ($this->isBudgetOfficer) {
            $folder = $this->task->document;
            $this->budgetPpaCode = $folder->budget_ppa_code ?: ($folder->prItems->first()?->cobItem?->sub_ppa_code ?? $folder->prItems->first()?->cobItem?->ppa_code ?? '');
            $this->budgetCode = $folder->budget_code ?: ($folder->prItems->first()?->cobItem?->account ?? $folder->prItems->first()?->cobItem?->exp_desc ?? '');
        }

        // Security Guard: Check if the logged in user is the target signatory
        if ($this->task->target_employee_id !== $employeeId) {
            abort(403, "Unauthorized: You are not the assigned signatory for this document task.");
        }

        // Anti-Bypass Guard: Enforce time entry logging immediately upon mounting the viewport
        if (is_null($this->task->viewed_at)) {
            $this->task->update([
                'viewed_at' => now(),
                'viewed_by_employee_id' => auth()->user()->employee_id
            ]);
        }

        // Auto-select SYSTEM_PR tab by default for verification/approval of PR
        $sortedAttachments = $this->task->document->attachments->sortBy(function($attach) {
            return match($attach->attachment_type) {
                'SYSTEM_PR' => 1,
                'SYSTEM_COVER_LETTER' => 2,
                'SYSTEM_ABC' => 3,
                default => 4
            };
        })->values();

        foreach ($sortedAttachments as $index => $attach) {
            if ($attach->attachment_type === 'SYSTEM_PR') {
                $this->activeTab = $index;
                break;
            }
        }
    }

    public function executeApprovalSignature()
    {
        // Ultimate Safety Check: Ensure structural loading was not skipped
        if (is_null($this->task->viewed_at)) {
            throw new \Exception("Security Exception: Document validation context must be verified before sign-off.");
        }

        if ($this->hasOlderPending) {
            session()->flash('error', "FIFO Policy: You must sign the oldest pending task in your queue first.");
            return;
        }

        if ($this->task->status !== 'PENDING') {
            session()->flash('error', "This task has already been processed.");
            return $this->redirectRoute('admin.unified-desk');
        }

        if ($this->isBudgetOfficer) {
            $this->validate([
                'budgetPpaCode' => 'required|string|max:100',
                'budgetCode' => 'required|string|max:100',
            ], [
                'budgetPpaCode.required' => 'Operational Rule: You must verify and enter the PPA Code.',
                'budgetCode.required' => 'Operational Rule: You must verify and enter the Budget Code.',
            ]);

            $this->task->document->update([
                'budget_ppa_code' => $this->budgetPpaCode,
                'budget_code' => $this->budgetCode,
            ]);
        }

        \DB::transaction(function () {
            $document = $this->task->document;
            
            // Execute the model's interface method to stamp the signature footprint
            $document->applySignature(auth()->user()->employee_id);
            
            $this->task->update(['status' => 'SIGNED']);
        });

        session()->flash('success', "Document {$this->task->tracking_number} signed successfully.");
        return $this->redirectRoute('admin.unified-desk');
    }

    public function submitDocumentRejection()
    {
        $this->validate([
            'rejectionType'    => 'required|in:EDIT,COMPLIANCE,PERMANENT',
            'rejectionRemarks' => 'required|string|min:10|max:1000',
        ], [
            'rejectionRemarks.required' => 'Operational Rule: You must provide clear explanatory remarks for a document return.',
            'rejectionRemarks.min' => 'Please provide comprehensive notes (min 10 characters) so the user knows how to achieve compliance.'
        ]);

        if ($this->hasOlderPending) {
            session()->flash('error', "FIFO Policy: You must sign the oldest pending task in your queue first.");
            return;
        }

        if ($this->task->status !== 'PENDING') {
            session()->flash('error', "This task has already been processed.");
            return $this->redirectRoute('admin.unified-desk');
        }

        \DB::transaction(function () {
            $document = $this->task->document;
            $creatorId = $document->created_by_id;

            switch ($this->rejectionType) {
                case 'EDIT':
                    // Soft Return: Fields unlock entirely for structural modifications
                    $document->update([
                        'status' => 'RETURNED_FOR_EDIT',
                        'current_signatory_id' => $creatorId
                    ]);
                    break;

                case 'COMPLIANCE':
                    // Compliance Return: Core text inputs lock down, file upload streams activate
                    $document->update([
                        'status' => 'RETURNED_FOR_COMPLIANCE',
                        'current_signatory_id' => $creatorId
                    ]);
                    break;

                case 'PERMANENT':
                    // Hard Rejection: Document lifecycle terminates permanently
                    $document->update([
                        'status' => 'REJECTED',
                        'current_signatory_id' => null
                    ]);

                    // AUTOMATED BUDGET REVERSAL ROUTINE
                    if (method_exists($document, 'prItems')) {
                        foreach ($document->prItems as $item) {
                            if ($item->appLineItem) {
                                $item->appLineItem->decrement(
                                    'utilized_budget', 
                                    ($item->quantity * $item->estimated_unit_cost)
                                );
                            }
                        }
                    }
                    break;
            }

            // Close out the approval task row entry
            $this->task->update(['status' => 'REJECTED']);

            // Write a permanent tracking log entry
            $document->logs()->create([
                'action' => 'DOCUMENT_REJECTION_' . $this->rejectionType,
                'actor_id' => auth()->user()->employee_id,
                'remarks' => $this->rejectionRemarks,
                'created_at' => now(),
            ]);

            // Dispatch immediate notification banner to the end-user
            if ($document->creator) {
                $document->creator->notify(new \App\Notifications\DocumentReturnedNotification([
                    'tracking_number' => $document->pr_number ?: $document->tracking_number,
                    'type'            => $this->rejectionType,
                    'remarks'         => $this->rejectionRemarks,
                    'officer_name'    => auth()->user()->employee->fullname
                ]));
            }
        });

        session()->flash('success', 'Document processing completed. Record pushed back to the originator.');
        return $this->redirectRoute('admin.unified-desk');
    }
}; ?>

<div class="p-gutter space-y-6">
    @section('header_title', 'Document Workspace')

    {{-- Breadcrumb / Header --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.unified-desk') }}" class="inline-flex items-center gap-1.5 text-xs text-[#001e40] font-bold hover:underline">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            Back to Approval Desk
        </a>
        <span class="text-xs text-[#43474f] font-mono">TASK-ID: #{{ $task->id }}</span>
    </div>

    {{-- Split-Screen Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter items-start">
        
        {{-- LEFT PANEL: Document Review Viewport (col-span-2) --}}
        <div class="lg:col-span-2 sticky top-[20px] h-[calc(100vh-100px)] flex flex-col">
            
            {{-- Dynamic Tabbed Viewport Box --}}
            <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden flex flex-col flex-1 mb-6">
                <!-- Navigation Tabs Strip -->
                @php
                    $allAttachments = $task->document->attachments->sortBy(function($attach) {
                        return match($attach->attachment_type) {
                            'SYSTEM_PR' => 1,
                            'SYSTEM_COVER_LETTER' => 2,
                            'SYSTEM_ABC' => 3,
                            default => 4
                        };
                    })->values();
                @endphp
                <div class="flex border-b border-[#eeedf2] bg-[#f9f9fe] p-2 gap-2 overflow-x-auto custom-scrollbar">
                    @foreach($allAttachments as $index => $attach)
                        @php
                            $displayName = match($attach->attachment_type) {
                                'SYSTEM_PR' => 'Purchase Request',
                                'SYSTEM_COVER_LETTER' => 'Cover Letter',
                                'SYSTEM_ABC' => 'ABC',
                                default => pathinfo($attach->original_name, PATHINFO_FILENAME)
                            };
                        @endphp
                        <button wire:click="$set('activeTab', {{ $index }})" 
                                class="px-3.5 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 shrink-0 {{ $activeTab == $index ? 'bg-[#001e40] text-white shadow-sm' : 'hover:bg-[#eeedf2] text-[#43474f] hover:text-[#001e40]' }}">
                            <span class="material-symbols-outlined text-[16px]">
                                {{ str_starts_with($attach->attachment_type, 'SYSTEM_') ? 'auto_stories' : 'description' }}
                            </span>
                            <span>{{ $displayName }}</span>
                        </button>
                    @endforeach
                </div>

                <!-- Live Document Active Viewport Panel -->
                <div class="flex-1 w-full bg-white relative">
                    @if(isset($allAttachments[$activeTab]))
                        @php
                            $activeAttach = $allAttachments[$activeTab];
                            $isImage = str_starts_with($activeAttach->mime_type, 'image/');
                        @endphp
                        @if($isImage)
                            <div class="w-full h-full flex items-center justify-center p-4 bg-[#f4f3f8] overflow-auto absolute inset-0">
                                <img src="{{ route('admin.file-stream', $activeAttach->id) }}"
                                     class="max-w-full max-h-full object-contain shadow-md rounded-xl border border-[#c3c6d1]"
                                     alt="{{ $activeAttach->original_name }}"/>
                            </div>
                        @else
                            <iframe src="{{ route('admin.file-stream', $activeAttach->id) }}" 
                                    class="w-full h-full border-0 absolute inset-0" 
                                    loading="lazy"></iframe>
                        @endif
                    @else
                        <div class="flex flex-col items-center justify-center h-full text-xs text-[#43474f]/50 italic gap-2">
                            <span class="material-symbols-outlined text-[36px] text-[#c3c6d1]">find_in_page</span>
                            <span>No compliance documents compiled yet for this request.</span>
                        </div>
                    @endif
                </div>
            </div>

        </div>
        
        {{-- RIGHT PANEL: Action Execution Sidebar (col-span-1) --}}
        <div class="lg:col-span-1 space-y-6">
            
            {{-- Sign Off Action Card --}}
            <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden">
                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe]">
                    <h3 class="font-bold text-sm text-[#001e40]">Action Execution Panel</h3>
                </div>
                
                <div class="p-6 space-y-6">
                    @if($task->status === 'PENDING')
                        @if($hasOlderPending)
                            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-3 text-xs leading-relaxed text-amber-800">
                                <div class="font-bold flex items-center gap-1.5 text-amber-900">
                                    <span class="material-symbols-outlined text-[18px]">warning</span>
                                    FIFO Policy Active
                                </div>
                                <p>
                                    Under the First-In-First-Out (FIFO) approval policy, you must review and sign the oldest document in your queue before you can process this request.
                                </p>
                                <div class="pt-2">
                                    <a href="{{ route('admin.document-workspace', $oldestPendingTask->id) }}" 
                                       class="w-full inline-flex items-center justify-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 px-3 rounded-xl shadow-sm transition-all text-xs active:scale-95">
                                        <span class="material-symbols-outlined text-[14px]">arrow_right_alt</span>
                                        Open Oldest Task ({{ $oldestPendingTask->tracking_number }})
                                    </a>
                                </div>
                            </div>
                        @else
                            {{-- Approve Segment --}}
                            <div class="space-y-3">
                            @if($isBudgetOfficer)
                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl space-y-3 mb-2 text-xs">
                                    <div class="font-bold text-[#001e40] flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px]">account_balance_wallet</span>
                                        Budget Code Certification Stamp
                                    </div>
                                    <p class="text-[10px] text-[#43474f] leading-relaxed">
                                        Confirm and verify the PPA Code and Budget Code below. These will be stamped directly onto the PR document.
                                    </p>
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">PPA Code <span class="text-red-600">*</span></label>
                                            <input type="text" wire:model="budgetPpaCode" class="w-full text-xs px-3 py-2 bg-white border border-[#c3c6d1] rounded-lg outline-none focus:ring-1 focus:ring-[#001e40] font-mono" placeholder="e.g. A.XII.f.X.a.01">
                                            @error('budgetPpaCode') <span class="text-[10px] text-[#ba1a1a] font-bold mt-0.5 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Budget Code / Account <span class="text-red-600">*</span></label>
                                            <input type="text" wire:model="budgetCode" class="w-full text-xs px-3 py-2 bg-white border border-[#c3c6d1] rounded-lg outline-none focus:ring-1 focus:ring-[#001e40] font-mono" placeholder="e.g. Traveling Expenses">
                                            @error('budgetCode') <span class="text-[10px] text-[#ba1a1a] font-bold mt-0.5 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="p-3 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200 text-xs leading-relaxed">
                                <span class="font-bold flex items-center gap-1 mb-1">
                                    <span class="material-symbols-outlined text-[16px]">verified</span> Physical & Digital Verification
                                </span>
                                I hereby certify that I have received, reviewed, and signed the physical hard copy of this Purchase Request, and verified that all attached physical and digital documents are correct and consistent.
                            </div>
                            
                            <button wire:click="executeApprovalSignature" 
                                    wire:loading.attr="disabled"
                                    class="w-full flex items-center justify-center gap-2 bg-[#001e40] hover:bg-[#003272] disabled:bg-[#001e40]/70 text-white font-bold py-3 px-4 rounded-xl shadow-md transition-all active:scale-95 text-sm">
                                <span class="material-symbols-outlined animate-spin" wire:loading wire:target="executeApprovalSignature" style="display:none">progress_activity</span>
                                <span class="material-symbols-outlined" wire:loading.remove wire:target="executeApprovalSignature">draw</span>
                                <span>Approve & Sign Document</span>
                            </button>
                        </div>

                        {{-- Rejection Segment --}}
                        <div class="border-t border-[#eeedf2] pt-6 space-y-4">
                            <h4 class="font-bold text-xs text-[#ba1a1a] uppercase tracking-wider">3-Tier Rejection Engine</h4>
                            
                            <div class="space-y-3 text-xs">
                                {{-- Radio Type Option 1 --}}
                                <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-[#c3c6d1] hover:bg-[#f4f3f8] cursor-pointer transition-colors">
                                    <input type="radio" wire:model.live="rejectionType" value="EDIT" class="mt-0.5 text-[#ba1a1a] focus:ring-[#ba1a1a]">
                                    <div>
                                        <p class="font-bold text-[#1a1c1f]">Soft Return (Returned for Edit)</p>
                                        <p class="text-[10px] text-[#43474f] mt-0.5">Unlocks all fields for text/item editing. Stays with original creator.</p>
                                    </div>
                                </label>

                                {{-- Radio Type Option 2 --}}
                                <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-[#c3c6d1] hover:bg-[#f4f3f8] cursor-pointer transition-colors">
                                    <input type="radio" wire:model.live="rejectionType" value="COMPLIANCE" class="mt-0.5 text-[#ba1a1a] focus:ring-[#ba1a1a]">
                                    <div>
                                        <p class="font-bold text-[#1a1c1f]">Compliance Return</p>
                                        <p class="text-[10px] text-[#43474f] mt-0.5">Locks form inputs, opens only the attachment upload stream for corrections.</p>
                                    </div>
                                </label>

                                {{-- Radio Type Option 3 --}}
                                <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-[#c3c6d1] hover:bg-[#f4f3f8] cursor-pointer transition-colors">
                                    <input type="radio" wire:model.live="rejectionType" value="PERMANENT" class="mt-0.5 text-[#ba1a1a] focus:ring-[#ba1a1a]">
                                    <div>
                                        <p class="font-bold text-[#ba1a1a]">Permanent Rejection (Purge)</p>
                                        <p class="text-[10px] text-[#43474f] mt-0.5">Terminates lifecycle. Reverts utilized budget back to the APP matrix line item.</p>
                                    </div>
                                </label>
                            </div>

                            {{-- Rejection Comments --}}
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-[#43474f]">Explanatory Remarks <span class="text-red-600">*</span></label>
                                <textarea wire:model="rejectionRemarks" rows="4" 
                                          class="w-full text-xs p-3 border border-[#c3c6d1] rounded-xl focus:border-[#ba1a1a] focus:ring-[#ba1a1a] placeholder-gray-400"
                                          placeholder="Provide clear compliance explanations (min 10 characters)..."></textarea>
                                @error('rejectionRemarks') <span class="text-[10px] font-bold text-[#ba1a1a] mt-0.5 block">{{ $message }}</span> @enderror
                                @error('rejectionType') <span class="text-[10px] font-bold text-[#ba1a1a] mt-0.5 block">{{ $message }}</span> @enderror
                            </div>

                            <button wire:click="submitDocumentRejection"
                                    wire:loading.attr="disabled"
                                    class="w-full flex items-center justify-center gap-1.5 bg-[#ba1a1a] hover:bg-[#93000a] disabled:bg-[#ba1a1a]/70 text-white font-bold py-2.5 px-4 rounded-xl shadow-md transition-all active:scale-95 text-xs">
                                <span class="material-symbols-outlined animate-spin text-[16px]" wire:loading wire:target="submitDocumentRejection" style="display:none">progress_activity</span>
                                <span class="material-symbols-outlined text-[16px]" wire:loading.remove wire:target="submitDocumentRejection">assignment_return</span>
                                <span>Submit Rejection / Return</span>
                            </button>
                        </div>
                        @endif
                    @else
                        {{-- Task Already Processed --}}
                        <div class="text-center py-6 space-y-4">
                            @if($task->status === 'SIGNED')
                                <div class="w-16 h-16 rounded-full bg-green-50 text-green-700 flex items-center justify-center mx-auto shadow-inner">
                                    <span class="material-symbols-outlined text-[36px]">verified</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm text-[#001e40]">Document Signed</h4>
                                    <p class="text-xs text-[#43474f] mt-1">This task was approved and signed off.</p>
                                    <p class="text-[10px] font-mono text-[#43474f]/70 mt-2">Processed: {{ $task->updated_at->format('M d, Y h:i A') }}</p>
                                </div>
                            @else
                                <div class="w-16 h-16 rounded-full bg-red-50 text-red-700 flex items-center justify-center mx-auto shadow-inner">
                                    <span class="material-symbols-outlined text-[36px]">assignment_return</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm text-[#ba1a1a]">Document Rejected</h4>
                                    <p class="text-xs text-[#43474f] mt-1">This task was rejected/returned.</p>
                                    <p class="text-[10px] font-mono text-[#43474f]/70 mt-2">Processed: {{ $task->updated_at->format('M d, Y h:i A') }}</p>
                                </div>
                            @endif
                            <a href="{{ route('admin.unified-desk') }}" class="inline-flex items-center gap-1 bg-[#eeedf2] hover:bg-[#c3c6d1] text-[#001e40] text-xs font-bold px-4 py-2 rounded-xl transition-all">
                                Return to Desk
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Audit Timeline Log (Moved to Right Panel) --}}
            <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm space-y-4">
                <h3 class="font-bold text-sm text-[#001e40] border-b border-[#eeedf2] pb-2">Document Audit Timeline</h3>
                <div class="space-y-4">
                    @forelse($task->document->logs as $log)
                        <div class="flex items-start gap-3 text-xs">
                            <div class="mt-0.5">
                                <span class="material-symbols-outlined text-[16px] text-[#43474f]">history</span>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-[#1a1c1f]">
                                    {{ str_replace('_', ' ', $log->action) }} 
                                    <span class="font-medium text-[#43474f]">by {{ $log->actor?->fullname ?? 'System' }}</span>
                                </p>
                                @if($log->remarks)
                                    <p class="text-[#43474f] italic mt-0.5">"{{ $log->remarks }}"</p>
                                @endif
                                <span class="text-[9px] text-[#43474f]/60">{{ $log->created_at->format('M d, Y h:i A') }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-[#43474f]/60 italic">No historical timeline records found.</p>
                    @endforelse
                </div>
            </div>

            {{-- Audit Compliance Info --}}
            <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm text-xs space-y-3">
                <h4 class="font-bold text-[#001e40] flex items-center gap-1 border-b border-[#eeedf2] pb-1.5">
                    <span class="material-symbols-outlined text-sm">gavel</span>
                    COA Internal Controls
                </h4>
                <div class="space-y-2 text-[#43474f] leading-relaxed">
                    <p>1. <strong>Strict Viewport Audit:</strong> Reviewers must open this viewport to verify document context before signatures can be digitally verified. Bypasses will be auto-flagged.</p>
                    <p>2. <strong>Rollback Transparency:</strong> Permanent rejections release all matching line items' reserved values back into the divisional APP budget automatically.</p>
                </div>
            </div>

        </div>

    </div>
</div>

