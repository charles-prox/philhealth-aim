<?php

use Livewire\Volt\Component;
use App\Models\ApprovalTask;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public string $activeTab = 'PENDING';

    public function mount()
    {
        $employeeId = auth()->user()->employee_id;
        if (!$employeeId || !\App\Models\SignatoryRegistry::isEmployeeSignatory($employeeId)) {
            abort(403, "Access Denied: You are not registered in the Signatory Matrix.");
        }
    }

    #[Computed]
    public function tasks()
    {
        $query = ApprovalTask::where('target_employee_id', auth()->user()->employee_id);
        if ($this->activeTab === 'PENDING') {
            return $query->where('status', 'PENDING')->oldest()->get();
        } else {
            return $query->whereIn('status', ['SIGNED', 'REJECTED'])->latest()->get();
        }
    }

    #[Computed]
    public function stats()
    {
        $empId = auth()->user()->employee_id;
        return [
            'pending'  => ApprovalTask::where('target_employee_id', $empId)->where('status', 'PENDING')->count(),
            'signed'   => ApprovalTask::where('target_employee_id', $empId)->where('status', 'SIGNED')->count(),
            'rejected' => ApprovalTask::where('target_employee_id', $empId)->where('status', 'REJECTED')->count(),
        ];
    }
}; ?>

<div class="p-gutter space-y-6">
    @section('header_title', 'Unified Approval Desk')

    {{-- Metric Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
        {{-- Card 1: Pending --}}
        <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-700">
                <span class="material-symbols-outlined text-[28px]">pending_actions</span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider">Pending Action</p>
                <h3 class="text-2xl font-bold text-[#001e40] mt-0.5">{{ $this->stats['pending'] }}</h3>
            </div>
        </div>

        {{-- Card 2: Signed --}}
        <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-700">
                <span class="material-symbols-outlined text-[28px]">verified_user</span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider">Signed / Approved</p>
                <h3 class="text-2xl font-bold text-[#001e40] mt-0.5">{{ $this->stats['signed'] }}</h3>
            </div>
        </div>

        {{-- Card 3: Rejected --}}
        <div class="bg-white p-6 border border-[#c3c6d1] rounded-2xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-700">
                <span class="material-symbols-outlined text-[28px]">assignment_return</span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider">Returned / Rejected</p>
                <h3 class="text-2xl font-bold text-[#001e40] mt-0.5">{{ $this->stats['rejected'] }}</h3>
            </div>
        </div>
    </div>

    {{-- Main Document Table --}}
    <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden">
        <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe]">
            <h3 class="font-h2 text-h2 text-[#001e40]">Tasks Requiring Your Signature</h3>
            <p class="text-xs text-[#43474f] mt-1">Review the document details thoroughly before signing off. Blind approvals are prohibited by COA audit rules.</p>
        </div>

        <!-- Tabs Selector -->
        <div class="flex border-b border-[#eeedf2] bg-[#f9f9fe] px-6 py-2 gap-2 flex-shrink-0">
            <button wire:click="$set('activeTab', 'PENDING')" 
                    class="px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-2 shrink-0 {{ $activeTab === 'PENDING' ? 'bg-[#001e40] text-white shadow-sm' : 'hover:bg-[#eeedf2] text-[#43474f] hover:text-[#001e40]' }}">
                <span class="material-symbols-outlined text-[16px]">pending_actions</span>
                <span>Pending Approvals</span>
                @if($this->stats['pending'] > 0)
                    <span class="bg-[#ba1a1a] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $this->stats['pending'] }}</span>
                @endif
            </button>
            <button wire:click="$set('activeTab', 'HISTORY')" 
                    class="px-4 py-2 text-xs font-bold rounded-lg transition-all flex items-center gap-2 shrink-0 {{ $activeTab === 'HISTORY' ? 'bg-[#001e40] text-white shadow-sm' : 'hover:bg-[#eeedf2] text-[#43474f] hover:text-[#001e40]' }}">
                <span class="material-symbols-outlined text-[16px]">history</span>
                <span>Action History</span>
                @php
                    $historyCount = $this->stats['signed'] + $this->stats['rejected'];
                @endphp
                @if($historyCount > 0)
                    <span class="bg-[#c3c6d1] text-[#43474f] text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none">{{ $historyCount }}</span>
                @endif
            </button>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full border-collapse text-left bg-white">
                <thead>
                    <tr class="bg-[#eeedf2] border-b border-[#c3c6d1] text-xs font-bold text-[#001e40] uppercase tracking-wider">
                        <th class="p-table-cell-padding">Document Type</th>
                        <th class="p-table-cell-padding">Tracking Number</th>
                        <th class="p-table-cell-padding">Originating Office</th>
                        <th class="p-table-cell-padding">Date Received</th>
                        @if($activeTab === 'HISTORY')
                            <th class="p-table-cell-padding">Outcome</th>
                        @endif
                        <th class="p-table-cell-padding text-right">Operations</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                    @forelse($this->tasks as $task)
                        <tr class="hover:bg-[#f4f3f8] transition-colors">
                            <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $task->document_label }}</td>
                            <td class="p-table-cell-padding font-mono font-bold text-[#1a1c1f]">{{ $task->tracking_number }}</td>
                            <td class="p-table-cell-padding font-medium text-[#43474f]">{{ $task->originating_office }}</td>
                            <td class="p-table-cell-padding text-xs text-[#43474f]/70">{{ $task->created_at->format('M d, Y h:i A') }}</td>
                            @if($activeTab === 'HISTORY')
                                <td class="p-table-cell-padding">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                        {{ $task->status === 'SIGNED' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                        {{ $task->status }}
                                    </span>
                                </td>
                            @endif
                            <td class="p-table-cell-padding text-right">
                                <a href="{{ route('admin.document-workspace', $task->id) }}" 
                                   class="inline-flex items-center gap-1.5 bg-[#001e40] hover:bg-[#003272] text-white text-xs font-bold px-3 py-2 rounded-lg shadow-sm transition-all active:scale-95">
                                    <span class="material-symbols-outlined text-[16px]">{{ $activeTab === 'PENDING' ? 'draw' : 'visibility' }}</span>
                                    <span>{{ $activeTab === 'PENDING' ? 'Review & Sign' : 'View Record' }}</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $activeTab === 'HISTORY' ? 6 : 5 }}" class="p-16 text-center text-[#43474f]/60 italic">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">
                                        {{ $activeTab === 'PENDING' ? 'task_alt' : 'history' }}
                                    </span>
                                    <p class="font-bold text-[#001e40]">
                                        {{ $activeTab === 'PENDING' ? 'No Pending Approvals' : 'No History Found' }}
                                    </p>
                                    <p class="text-xs">
                                        {{ $activeTab === 'PENDING' 
                                            ? 'All documents have been processed. Enjoy your empty inbox!' 
                                            : 'You have not processed any approval tasks in this cycle yet.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
