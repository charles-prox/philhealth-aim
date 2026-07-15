{{-- Audit Trail History Modal --}}
@if($viewingHistoryFolderId)
    @php
        $historyFolder = \App\Models\ProcurementFolder::with('logs.actor')->find($viewingHistoryFolderId);
    @endphp
    <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-white border border-[#eeedf2] rounded-xl max-w-xl w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div class="flex justify-between items-center border-b border-[#eeedf2] pb-4">
                <h4 class="text-base font-bold text-[#001e40] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[24px]">history</span>
                    Audit Trail: {{ $historyFolder?->pr_number ?? $historyFolder?->tracking_number }}
                </h4>
                <button wire:click="closeHistory" class="p-1.5 hover:bg-[#eeedf2] rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div class="max-h-[350px] overflow-y-auto pr-2 custom-scrollbar space-y-4">
                @if($historyFolder && $historyFolder->logs->isNotEmpty())
                    <div class="relative border-l-2 border-[#eeedf2] ml-4 pl-6 flex flex-col gap-6">
                        @foreach($historyFolder->logs as $log)
                            @php
                                $actionClasses = [
                                    'REJECTED' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    'RESUBMITTED' => 'bg-blue-50 text-blue-700 border-blue-100',
                                    'APPROVED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                ];
                                $class = $actionClasses[$log->action] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                            @endphp
                            <div class="relative">
                                {{-- Timeline bullet --}}
                                <div class="absolute -left-[31px] top-1 w-4 h-4 rounded-full border-4 border-white bg-[#001e40] shadow-sm"></div>
                                
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 text-[9px] font-bold rounded-full uppercase border {{ $class }}">{{ $log->action }}</span>
                                        <span class="text-[10px] text-[#43474f]/60 font-semibold">{{ $log->created_at->format('M d, Y · h:i A') }}</span>
                                    </div>
                                    <p class="text-xs text-[#001e40] font-bold mt-1">
                                        By: {{ $log->actor?->fullname ?? 'Unknown Actor' }} <span class="text-[10px] text-[#43474f]/70 font-normal italic">({{ $log->actor?->designation }})</span>
                                    </p>
                                    @if($log->remarks)
                                        <div class="bg-[#f4f3f8] border border-[#eeedf2] rounded-xl p-3 mt-1.5 text-xs text-[#43474f] italic leading-relaxed">
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

            <div class="flex justify-end pt-3 border-t border-[#eeedf2]">
                <button wire:click="closeHistory" class="px-4 py-2 bg-[#eeedf2] hover:bg-[#f4f3f8] text-[#43474f] font-bold text-xs rounded-lg transition-all">
                    Close Trail
                </button>
            </div>
        </div>
    </div>
@endif
