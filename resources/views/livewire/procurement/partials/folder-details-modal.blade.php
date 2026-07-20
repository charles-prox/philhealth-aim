{{-- Folder Details Modal --}}
@if($this->viewingFolder)
    @php
        $vf = $this->viewingFolder;
    @endphp
    <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-[#f1f3f6] border border-[#eeedf2] rounded-xl max-w-4xl w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200 relative flex flex-col my-8 h-[80vh]">
            <!-- Modal Header -->
            <div class="bg-white px-6 py-4 flex justify-between items-center border-b border-[#eeedf2] rounded-t-xl flex-shrink-0">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-bold text-[#001e40] uppercase tracking-wider">Purchase Request Details</h3>
                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                        {{ $vf->status === 'APPROVED' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 
                           (in_array($vf->status, ['CANCELLED', 'CANCELLED_BY_USER']) ? 'bg-rose-50 text-rose-700 border border-rose-200' : 
                           ($vf->status === 'DRAFT' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200')) }}">
                        {{ $vf->status_label }}
                    </span>
                    <span class="font-mono text-xs text-[#43474f]/70 bg-[#eeedf2]/50 px-2 py-0.5 rounded border border-[#c3c6d1] font-bold">{{ $vf->tracking_number }}</span>
                </div>
                <button wire:click="closeDetails" class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all">
                    <span class="material-symbols-outlined text-[20px] font-bold">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="overflow-y-auto px-6 py-5 flex-1 flex flex-col gap-6">
                <!-- Metadata Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white p-5 border border-[#eeedf2] rounded-xl space-y-3 shadow-xs">
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
                    <div class="bg-white p-5 border border-[#eeedf2] rounded-xl space-y-3 shadow-xs">
                        <h4 class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/60">Purpose</h4>
                        <p class="text-xs text-[#001e40] italic leading-relaxed">{{ $vf->overall_purpose ?: 'No purpose specified' }}</p>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="bg-white border border-[#c3c6d1] rounded-xl overflow-hidden shadow-xs">
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
                                    <td class="p-3 text-center text-[#43474f] font-semibold">{{ $item->total_qty }}</td>
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
            <div class="bg-white px-6 py-4 border-t border-[#eeedf2] rounded-b-xl flex justify-end flex-shrink-0">
                <button wire:click="closeDetails" class="px-4 py-2 bg-[#eeedf2] hover:bg-[#c3c6d1] text-[#43474f] font-bold text-xs rounded-lg transition-all active:scale-95">
                    Close Details
                </button>
            </div>
        </div>
    </div>
@endif
