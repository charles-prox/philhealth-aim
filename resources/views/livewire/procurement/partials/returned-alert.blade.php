@if($this->folder && in_array($this->folder->status, ['RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE']))
    @php
        $latestLog = $this->folder->logs->first();
    @endphp
    @if($latestLog)
        <div class="mb-6 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200 shadow-sm animate-in fade-in slide-in-from-top duration-300">
            <span class="font-bold flex items-center gap-2 text-sm text-red-700">
                <span class="material-symbols-outlined">assignment_return</span> 
                Action Required: Document Returned by {{ $latestLog->actor?->fullname ?? 'Officer' }}
            </span>
            <p class="text-xs mt-2 bg-white p-3 rounded-lg border border-gray-200 font-mono text-[#1a1c1f]">
                <strong>REJECTION TYPE:</strong> {{ str_replace('_', ' ', str_replace('DOCUMENT_REJECTION_', '', $latestLog->action)) }}<br>
                <strong>REMARKS:</strong> "{{ $latestLog->remarks }}"
            </p>
        </div>
    @endif
@endif
