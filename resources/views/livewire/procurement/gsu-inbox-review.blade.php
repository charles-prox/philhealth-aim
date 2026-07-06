<?php

use Livewire\Volt\Component;
use App\Models\ProcurementFolder;
use App\Models\ProcurementLog;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component
{
    public ProcurementFolder $folder;
    public int $activeTab = 0;

    // Accept flow
    public string $prNumber = '';

    // Return flow
    public string $returnRemarks = '';
    public string $returnMode = ''; // 'ACCEPT' or 'RETURN'

    public function mount(string $folderId)
    {
        $user = auth()->user();

        // Only Procurement Officers and Admins can access this workspace
        $isGsu = $user->hasAnyRole(['Admin', 'Procurement Officer']);

        if (!$isGsu) {
            abort(403, 'Access Denied: Only GSU Procurement Officers may access this workspace.');
        }

        $this->folder = ProcurementFolder::with(['attachments', 'prItems.appLineItem', 'logs', 'requestedBy', 'office'])
            ->findOrFail($folderId);

        if ($this->folder->status !== 'SUBMITTED_TO_GSU') {
            abort(403, 'This request is no longer pending GSU review.');
        }

        // Sort attachments: SYSTEM_PR, SYSTEM_COVER_LETTER, SYSTEM_ABC, then others
        $sortedAttachments = $this->folder->attachments->sortBy(function($attach) {
            return match($attach->attachment_type) {
                'SYSTEM_PR' => 1,
                'SYSTEM_COVER_LETTER' => 2,
                'SYSTEM_ABC' => 3,
                default => 4
            };
        })->values();

        // Auto-select the SYSTEM_PR tab if it exists
        foreach ($sortedAttachments as $index => $attach) {
            if ($attach->attachment_type === 'SYSTEM_PR') {
                $this->activeTab = $index;
                break;
            }
        }

        // Pre-fill PR number suggestion
        $this->prNumber = ProcurementFolder::generateNextPrNumber();
    }

    public function acceptAndRoute()
    {
        $this->validate([
            'prNumber' => 'required|string|max:50|unique:procurement_folders,pr_number',
        ], [
            'prNumber.required' => 'An official PR Number is required to route this request.',
            'prNumber.unique'   => 'This PR Number is already assigned. Please use the next sequential number.',
        ]);

        $actor = auth()->user()->employee;
        if (!$actor) {
            session()->flash('error', 'System error: Your account is not linked to an Employee record.');
            return;
        }

        DB::transaction(function () use ($actor) {
            $this->folder->update([
                'pr_number'           => $this->prNumber,
                'status'              => 'ROUTING',
                'gsu_accepted_at'     => now(),
                'gsu_accepted_by_id'  => $actor->id,
            ]);

            $hasABC = $this->folder->prItems->sum(fn($item) => (float) ($item->estimated_unit_cost ?? $item->unit_cost ?? 0.0)) > 0.0;
            $signedDocs = $hasABC ? 'Approved Budget for the Contract (ABC)' : 'None (No ABC generated)';

            ProcurementLog::create([
                'procurement_folder_id' => $this->folder->id,
                'action'                => 'APPROVED',
                'actor_id'              => $actor->id,
                'remarks'               => "Purchase Request accepted by GSU and routed for signatures with official PR Number: {$this->prNumber}. Digitally signed: {$signedDocs}.",
            ]);
        });

        \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($this->folder);

        session()->flash('success', "PR accepted and officially numbered {$this->prNumber}. Document package routed to signatories.");
        return $this->redirectRoute('procurement.admin');
    }

    public function returnToSender()
    {
        $this->validate([
            'returnRemarks' => 'required|string|min:10|max:1000',
        ], [
            'returnRemarks.required' => 'You must provide clear remarks explaining what needs to be corrected.',
            'returnRemarks.min'      => 'Please provide comprehensive notes (min 10 characters) so the requestor knows what to correct.',
        ]);

        $actor = auth()->user()->employee;
        if (!$actor) {
            session()->flash('error', 'System error: Your account is not linked to an Employee record.');
            return;
        }

        DB::transaction(function () use ($actor) {
            $this->folder->update([
                'status' => 'RETURNED_FOR_COMPLIANCE',
            ]);

            ProcurementLog::create([
                'procurement_folder_id' => $this->folder->id,
                'action'                => 'RETURNED',
                'actor_id'              => $actor->id,
                'remarks'               => $this->returnRemarks,
            ]);
        });

        session()->flash('success', 'Purchase Request returned to the requesting office for compliance.');
        return $this->redirectRoute('procurement.admin');
    }
}; ?>

<div class="p-gutter space-y-6">
    @section('header_title', 'GSU Inbox — Document Review')

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-xs font-bold">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-xs font-bold">
            {{ session('error') }}
        </div>
    @endif

    {{-- Breadcrumb / Header --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('procurement.admin') }}" class="inline-flex items-center gap-1.5 text-xs text-[#001e40] font-bold hover:underline">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            Back to GSU Desk
        </a>
        <div class="flex items-center gap-3">
            <span class="text-xs text-[#43474f] font-mono bg-[#eeedf2] px-2.5 py-1 rounded-lg border border-[#c3c6d1]">{{ $folder->tracking_number }}</span>
            <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                Pending GSU Review
            </span>
        </div>
    </div>

    {{-- Split-Screen Workspace Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter items-start">

        {{-- LEFT PANEL: Document Review Viewport (col-span-2) --}}
        <div class="lg:col-span-2 sticky top-[20px] h-[calc(100vh-100px)] flex flex-col">

            {{-- Dynamic Tabbed Viewport Box --}}
            <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden flex flex-col flex-1 mb-6">

                @php
                    $allAttachments = $folder->attachments->sortBy(function($attach) {
                        return match($attach->attachment_type) {
                            'SYSTEM_PR' => 1,
                            'SYSTEM_COVER_LETTER' => 2,
                            'SYSTEM_ABC' => 3,
                            default => 4
                        };
                    })->values();
                @endphp
                <div class="flex border-b border-[#eeedf2] bg-[#f9f9fe] p-2 gap-2 overflow-x-auto custom-scrollbar">
                    @forelse($allAttachments as $index => $attach)
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
                    @empty
                        <span class="px-4 py-2 text-xs text-[#43474f]/50 italic">No documents compiled yet.</span>
                    @endforelse
                </div>

                {{-- Live Document Viewer --}}
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

            {{-- PR Summary Card --}}
            <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden">
                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe]">
                    <h3 class="font-bold text-sm text-[#001e40]">Request Summary</h3>
                </div>
                <div class="p-5 space-y-3 text-xs">
                    <div class="flex justify-between">
                        <span class="text-[#43474f] font-semibold">Requesting Unit</span>
                        <span class="font-bold text-[#001e40] text-right">{{ $folder->requesting_unit ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#43474f] font-semibold">Requested By</span>
                        <span class="font-bold text-[#001e40] text-right">{{ $folder->requestedBy?->fullname ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#43474f] font-semibold">Date Submitted</span>
                        <span class="font-bold text-[#001e40]">{{ $folder->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#43474f] font-semibold">Total Value</span>
                        <span class="font-bold text-[#001e40]">₱{{ number_format($folder->prItems->sum('estimated_total_cost'), 2) }}</span>
                    </div>
                    <div class="pt-1 border-t border-[#eeedf2]">
                        <span class="text-[#43474f] font-semibold block mb-1">Purpose / Description</span>
                        <p class="italic text-[#001e40] leading-relaxed">{{ $folder->overall_purpose ?: 'No purpose specified.' }}</p>
                    </div>
                </div>
            </div>

            {{-- Accept & Route Action Card --}}
            <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden">
                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe]">
                    <h3 class="font-bold text-sm text-[#001e40]">GSU Review & Action</h3>
                </div>
                <div class="p-6 space-y-6">

                    {{-- Accept Section --}}
                    <div class="space-y-3">
                        <div class="p-3 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200 text-xs leading-relaxed">
                            <span class="font-bold flex items-center gap-1 mb-1">
                                <span class="material-symbols-outlined text-[16px]">verified</span>
                                Physical & Digital Verification
                            </span>
                            I hereby certify that I have received, reviewed, and verified the physical hard copy of this Purchase Request, and confirmed that all attached physical and digital documents are correct and complete.
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f]">
                                Assign Official PR Number <span class="text-[#ba1a1a]">*</span>
                            </label>
                            <input type="text" wire:model="prNumber"
                                   placeholder="PR-YYYY-XXXXX"
                                   class="w-full px-4 py-2.5 bg-[#f9f9fe] border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all font-mono font-bold text-[#001e40]"/>
                            @error('prNumber') <p class="text-[10px] font-bold text-[#ba1a1a] mt-0.5">{{ $message }}</p> @enderror
                        </div>

                        <button wire:click="acceptAndRoute"
                                wire:loading.attr="disabled"
                                class="w-full flex items-center justify-center gap-2 bg-[#001e40] hover:bg-[#003272] text-white font-bold py-3 px-4 rounded-xl shadow-md transition-all active:scale-95 text-sm disabled:opacity-60">
                            <span class="material-symbols-outlined" wire:loading.remove wire:target="acceptAndRoute">done_all</span>
                            <span class="material-symbols-outlined animate-spin" wire:loading wire:target="acceptAndRoute" style="display:none">progress_activity</span>
                            Accept & Route to Signatories
                        </button>
                    </div>

                    {{-- Return Divider --}}
                    <div class="border-t border-[#eeedf2] pt-6 space-y-4">
                        <h4 class="font-bold text-xs text-[#ba1a1a] uppercase tracking-wider">Return to Requesting Office</h4>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-[#43474f]">
                                Return / Compliance Remarks <span class="text-red-600">*</span>
                            </label>
                            <textarea wire:model="returnRemarks" rows="4"
                                      class="w-full text-xs p-3 border border-[#c3c6d1] rounded-xl focus:border-[#ba1a1a] focus:ring-1 focus:ring-[#ba1a1a] outline-none placeholder-gray-400 transition-all"
                                      placeholder="Clearly state what is missing or needs to be corrected (min 10 characters)..."></textarea>
                            @error('returnRemarks') <span class="text-[10px] font-bold text-[#ba1a1a] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>

                        <button wire:click="returnToSender"
                                wire:loading.attr="disabled"
                                class="w-full flex items-center justify-center gap-1.5 bg-[#ba1a1a] hover:bg-[#93000a] text-white font-bold py-2.5 px-4 rounded-xl shadow-md transition-all active:scale-95 text-xs disabled:opacity-60">
                            <span class="material-symbols-outlined text-[16px]">assignment_return</span>
                            Return to Requesting Office
                        </button>
                    </div>

                </div>
            </div>

            {{-- Audit Timeline Card --}}
            <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm space-y-4">
                <h3 class="font-bold text-sm text-[#001e40] border-b border-[#eeedf2] pb-2">Document Audit Timeline</h3>
                <div class="space-y-4">
                    @forelse($folder->logs as $log)
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

            {{-- Compliance Reminders --}}
            <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm text-xs space-y-3">
                <h4 class="font-bold text-[#001e40] flex items-center gap-1 border-b border-[#eeedf2] pb-1.5">
                    <span class="material-symbols-outlined text-sm">gavel</span>
                    GSU Verification Reminders
                </h4>
                <div class="space-y-2 text-[#43474f] leading-relaxed">
                    <p>1. <strong>Physical vs. Digital Match:</strong> Ensure the physical documents received match the compiled system record before accepting.</p>
                    <p>2. <strong>Completeness Check:</strong> Verify all items listed in the Cover Letter's document checklist are present in the physical package.</p>
                    <p>3. <strong>Sequential PR Numbering:</strong> Assign the next official PR number in sequence to maintain audit compliance.</p>
                </div>
            </div>

        </div>
    </div>
</div>
