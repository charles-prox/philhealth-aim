{{-- Step 3: Signature & Generation Review --}}
<div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm space-y-6 mt-4 mb-6">
    <div class="border-b border-[#eeedf2] pb-4 flex justify-between items-center">
        <div>
            <h3 class="text-xl font-bold text-[#001e40]">Review & Lock Document</h3>
            <p class="text-xs text-[#43474f] mt-1">Review the bundled items and metadata before final database lock.</p>
        </div>
        <span class="px-3 py-1.5 bg-[#fff8e1] border border-[#ffe082] text-[#f9a825] text-[10px] font-bold rounded-full uppercase tracking-wider">Awaiting Generation</span>
    </div>

    {{-- PR Preview Box --}}
    <div class="border-2 border-dashed border-[#c3c6d1] rounded-2xl p-6 bg-[#f9f9fe] space-y-5">
        <!-- PhilHealth Paperwork Header -->
        <div class="flex justify-between items-start">
            <div class="space-y-1.5">
                <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">PhilHealth AIM · Region X</p>
                <h4 class="text-lg font-bold text-[#001e40]">Purchase Request Bundle</h4>
                @if($compilePurpose)
                    <p class="text-[12px] text-[#43474f] leading-relaxed max-w-2xl"><strong class="text-[#001e40]">Purpose:</strong> {{ $compilePurpose }}</p>
                @endif
            </div>
            <div class="flex items-start gap-4">
                <div class="text-right">
                    <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/50">Tracking Number</p>
                    <p class="font-mono text-sm font-bold text-[#43474f]/70 bg-[#eeedf2]/50 px-2.5 py-1 rounded-lg inline-block border border-[#c3c6d1] mt-1">{{ $compileTrackingNumber }}</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/50">PR Number</p>
                    <p class="font-mono text-sm font-bold text-[#001e40] bg-[#001e40]/5 px-2.5 py-1 rounded-lg inline-block border border-[#001e40]/10 mt-1">{{ $compilePrNumber }}</p>
                </div>
            </div>
        </div>

        <!-- Grouped Items Table -->
        <div class="border border-[#c3c6d1] rounded-xl overflow-hidden bg-white">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#f4f3f8] border-b border-[#c3c6d1]">
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Category</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Item Particulars</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Qty</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit Cost</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Total Est. Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eeedf2]">
                    @foreach($this->reviewItems as $item)
                        <tr>
                            <td class="px-4 py-3 text-xs text-[#43474f]">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-[#43474f]">
                                    {{ $item['category'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs font-bold text-[#1a1c1f]">{{ $item['particulars'] }}</td>
                            <td class="px-4 py-3 text-xs text-right font-bold text-[#001e40]">{{ number_format($item['quantity']) }}</td>
                            <td class="px-4 py-3 text-xs text-center text-[#43474f] font-semibold">{{ $item['unit'] }}</td>
                            <td class="px-4 py-3 text-xs text-right text-[#43474f]">₱{{ number_format($item['unit_cost'], 2) }}</td>
                            <td class="px-4 py-3 text-xs text-right font-bold text-[#1a1c1f]">₱{{ number_format($item['total_cost'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Overall Totals Box -->
        <div class="flex justify-between items-center bg-[#001e40]/5 px-5 py-3.5 rounded-xl border border-[#001e40]/10">
            <span class="text-xs font-bold text-[#001e40] uppercase tracking-wider">Estimated Total Budget</span>
            <span class="text-lg font-black text-[#001e40]">₱{{ number_format($this->selectionEstimatedValue, 2) }}</span>
        </div>

        {{-- Signatories Preview Grid --}}
        @if($this->recommendedById && $this->approvedById)
            @php
                $reqEmp  = \App\Models\Employee::where('fullname', auth()->user()->name)->first();
                $reqName = auth()->user()->name;
                $reqDesig = $reqEmp ? $reqEmp->designation : 'Requesting Officer';

                $recEmp  = \App\Models\Employee::find($this->recommendedById);
                $appEmp  = \App\Models\Employee::find($this->approvedById);
            @endphp
            @if($recEmp && $appEmp)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 border-t border-[#eeedf2] pt-5 mt-5">
                    <div class="space-y-1">
                        <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">Requested By</p>
                        <p class="text-xs font-bold text-[#001e40]">{{ $reqName }}</p>
                        <p class="text-[10px] text-[#43474f]/70 italic">{{ $reqDesig }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">Recommended By</p>
                        <p class="text-xs font-bold text-[#001e40]">{{ $recEmp->fullname }}</p>
                        <p class="text-[10px] text-[#43474f]/70 italic">{{ $recEmp->designation }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">Approved By</p>
                        <p class="text-xs font-bold text-[#001e40]">{{ $appEmp->fullname }}</p>
                        <p class="text-[10px] text-[#43474f]/70 italic">{{ $appEmp->designation }}</p>
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Lock Notice --}}
    <div class="flex items-start gap-3 bg-[#fff8e1] border border-[#ffe082] rounded-xl px-4 py-3">
        <span class="material-symbols-outlined text-[#f9a825] text-[20px] flex-shrink-0 mt-0.5">lock</span>
        <div class="text-xs text-[#5d4037] leading-relaxed">
            <strong>Lock Notice:</strong> All <strong>{{ $this->selectionCount }}</strong> selected allocation{{ $this->selectionCount > 1 ? 's' : '' }} will be locked on generate. They will be bound to the new PR Folder and cannot be realigned or reallocated.
        </div>
    </div>

    {{-- Step 3 Buttons --}}
    <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
        <button wire:click="prevStep"
                class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Back to Details
        </button>
        <div class="flex items-center gap-3">
            <button x-on:click="$dispatch('close-pr-creation')"
                    class="px-5 py-2.5 text-sm font-bold text-[#ba1a1a] hover:bg-[#ba1a1a]/5 rounded-xl transition-all">
                Cancel PR
            </button>
            <button wire:click="processPrGeneration"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md disabled:opacity-60">
                <span wire:loading wire:target="processPrGeneration" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span wire:loading.remove wire:target="processPrGeneration" class="material-symbols-outlined text-[18px]">auto_awesome</span>
                Confirm & Generate PR
            </button>
        </div>
    </div>
</div>
