{{-- Audit Timeline Log --}}
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
