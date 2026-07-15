<!-- resources/views/livewire/admin/category-request/category-request-portal.blade.php -->
<div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-on-surface">Category Request Manager</h1>
            <p class="text-xs text-on-surface-variant">Review, approve, and map custom user items to official COA-UACS compliance codes.</p>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
        <table class="w-full border-collapse text-left text-xs">
            <thead class="bg-surface-container-high border-b border-outline-variant font-bold text-on-surface-variant">
                <tr>
                    <th class="p-4">Requested Item</th>
                    <th class="p-4">Requested By</th>
                    <th class="p-4">Office/Section</th>
                    <th class="p-4">Date Submitted</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant font-semibold text-on-surface">
                @forelse($requests as $req)
                    <tr class="hover:bg-surface-container-low transition-colors">
                        <td class="p-4">
                            <span class="font-bold text-sm block">{{ $req->requested_name }}</span>
                            <span class="text-[10px] text-outline font-normal block max-w-xs truncate" title="{{ $req->justification }}">
                                "{{ $req->justification }}"
                            </span>
                        </td>
                        <td class="p-4">{{ $req->user->name }}</td>
                        <td class="p-4">{{ $req->user->office->acronym ?? 'N/A' }}</td>
                        <td class="p-4">{{ $req->created_at->format('M d, Y h:i A') }}</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold 
                                @if($req->status === 'PENDING') bg-warning-container text-on-warning-container
                                @elseif($req->status === 'APPROVED') bg-success-container text-on-success-container
                                @else bg-error-container text-on-error-container @endif">
                                {{ $req->status }}
                            </span>
                        </td>
                        <td class="p-4 text-right space-x-1.5">
                            @if($req->status === 'PENDING')
                                <button wire:click="selectRequest({{ $req->id }}, 'approve')" class="px-2.5 py-1 bg-primary text-on-primary hover:bg-primary-hover rounded text-[11px] font-bold shadow-sm transition-colors">Approve</button>
                                <button wire:click="selectRequest({{ $req->id }}, 'reject')" class="px-2.5 py-1 bg-error text-on-error hover:bg-error-hover rounded text-[11px] font-bold shadow-sm transition-colors">Reject</button>
                            @else
                                <span class="text-outline-variant text-[11px] italic">Resolved</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-on-surface-variant">No user requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 bg-surface-container-low border-t border-outline-variant">
            {{ $requests->links() }}
        </div>
    </div>

    <!-- Holy Trinity Modals Inclusion -->
    @include('livewire.admin.category-request.partials.action-modals')
</div>
