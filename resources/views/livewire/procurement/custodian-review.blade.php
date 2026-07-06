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

    public function mount(string $folderId)
    {
        $user = auth()->user();

        $this->folder = ProcurementFolder::with(['attachments', 'prItems.appLineItem', 'logs', 'requestedBy', 'office'])
            ->findOrFail($folderId);

        // Security check: must be owner/same office or admin
        $isAuthorized = $user->hasRole('Admin') 
            || $this->folder->created_by_id === $user->id 
            || $this->folder->office_id === $user->office_id;

        if (!$isAuthorized) {
            abort(403, 'Unauthorized access to this Purchase Request review workspace.');
        }

        if (!in_array($this->folder->status, ['DRAFT', 'RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE'])) {
            abort(403, 'This request has already been submitted or processed.');
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
    }

    public function submitToGsu()
    {
        $actor = auth()->user()->employee;
        if (!$actor) {
            session()->flash('error', 'System error: Your account is not linked to an Employee record.');
            return;
        }

        DB::transaction(function () use ($actor) {
            $hasRejection = $this->folder->logs()->where('action', 'REJECTED')->exists();

            // Set signatory details to self
            $this->folder->update([
                'status'              => 'SUBMITTED_TO_GSU',
                'requested_signed_at' => now(),
                'current_signatory_id'=> $actor->id,
            ]);

            ProcurementLog::create([
                'procurement_folder_id' => $this->folder->id,
                'action'                => $hasRejection ? 'RESUBMITTED' : 'SUBMITTED',
                'actor_id'              => $actor->id,
                'remarks'               => $hasRejection 
                    ? 'PR resubmitted to GSU Inbox. Digitally signed: Purchase Request (PR) and Cover Letter.' 
                    : 'PR submitted to GSU Inbox. Digitally signed: Purchase Request (PR) and Cover Letter.',
            ]);
        });

        // Regenerate PDFs with end-user signature stamp
        \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($this->folder);

        session()->flash('success', "PR submitted to GSU Inbox successfully!");
        
        $user = auth()->user();
        if ($user->hasAnyRole(['Admin', 'Procurement Officer'])) {
            return $this->redirectRoute('procurement.admin', navigate: true);
        }
        return $this->redirectRoute('procurement.portal', navigate: true);
    }
}; ?>

<div class="p-gutter space-y-6">
    @section('header_title', 'PR Creator Desk — Sign & Submit')

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
        <a href="{{ auth()->user()->hasAnyRole(['Admin', 'Procurement Officer']) ? route('procurement.admin') : route('procurement.portal') }}" class="inline-flex items-center gap-1.5 text-xs text-[#001e40] font-bold hover:underline">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            Back to Registry
        </a>
        <div class="flex items-center gap-3">
            <span class="text-xs text-[#43474f] font-mono bg-[#eeedf2] px-2.5 py-1 rounded-lg border border-[#c3c6d1]">{{ $folder->tracking_number }}</span>
            <span class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200">
                Draft Request
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
                        <span class="text-[#43474f] font-semibold">Date Compiled</span>
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

            {{-- Review & Submit Action Card --}}
            <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden">
                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe]">
                    <h3 class="font-bold text-sm text-[#001e40]">Document Sign-Off & Submission</h3>
                </div>
                <div class="p-6 space-y-6">

                    <div class="space-y-4">
                        <div class="p-4 bg-blue-50 text-[#001b3c] rounded-xl border border-blue-100 text-xs space-y-2">
                            <span class="font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">print</span>
                                1. Print and Review
                            </span>
                            <p>Download and print the generated Purchase Request, Cover Letter, and Approved Budget for the Contract (ABC) documents shown on the left viewport.</p>
                        </div>

                        <div class="p-4 bg-blue-50 text-[#001b3c] rounded-xl border border-blue-100 text-xs space-y-2">
                            <span class="font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">draw</span>
                                2. Sign Off Physically
                            </span>
                            <p>Affix your physical signature to the printed hard copy documents. Stamping your physical signature is mandatory for GSU verification.</p>
                        </div>

                        <div class="p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-100 text-xs space-y-2">
                            <span class="font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">verified</span>
                                3. Confirm & Submit
                            </span>
                            <p>By clicking the button below, you certify that you have signed and prepared all required documents. This will digitally sign the PDFs and route them to the GSU Inbox.</p>
                        </div>

                        <button wire:click="submitToGsu"
                                wire:loading.attr="disabled"
                                class="w-full flex items-center justify-center gap-2 bg-[#001e40] hover:bg-[#003272] text-white font-bold py-3 px-4 rounded-xl shadow-md transition-all active:scale-95 text-sm disabled:opacity-60">
                            <span class="material-symbols-outlined" wire:loading.remove wire:target="submitToGsu">done_all</span>
                            <span class="material-symbols-outlined animate-spin" wire:loading wire:target="submitToGsu" style="display:none">progress_activity</span>
                            Sign & Submit to GSU Inbox
                        </button>
                    </div>

                </div>
            </div>

            {{-- Audit Timeline Card --}}
            <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm space-y-4">
                <h3 class="font-bold text-sm text-[#001e40] border-b border-[#eeedf2] pb-2">Document Log</h3>
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

        </div>
    </div>
</div>
