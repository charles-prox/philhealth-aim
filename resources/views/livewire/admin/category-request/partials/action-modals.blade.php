<!-- resources/views/livewire/admin/category-request/partials/action-modals.blade.php -->
<div>
    <!-- 1. Approve & Map Modal -->
    @if($showApproveModal && $this->selectedRequest)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-lg bg-surface border border-outline-variant rounded-xl shadow-2xl p-6 space-y-4">
                <h3 class="text-base font-bold text-on-surface">📋 Approve and Register Procurement Category</h3>
                
                <!-- Request Details Card -->
                <div class="bg-surface-container-low border border-outline-variant rounded-lg p-3 space-y-1.5 text-xs text-on-surface-variant">
                    <p><span class="font-bold text-on-surface">Requested Item:</span> {{ $this->selectedRequest->requested_name }}</p>
                    <p><span class="font-bold text-on-surface">Requested By:</span> {{ $this->selectedRequest->user->name }} ({{ $this->selectedRequest->user->office->acronym ?? 'N/A' }})</p>
                    <p><span class="font-bold text-on-surface">Justification:</span> <span class="italic">"{{ $this->selectedRequest->justification }}"</span></p>
                </div>

                <form wire:submit.prevent="processApproval" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-on-surface">Approved Category Name (UI Dropdown Label)</label>
                        <input type="text" wire:model="form.name" class="w-full text-xs p-2 bg-surface border border-outline-variant rounded focus:outline-primary" />
                        @error('form.name') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold mb-1 text-on-surface">COA UACS Code</label>
                            <input type="text" placeholder="e.g., 50203010" wire:model="form.uacs_code" class="w-full text-xs p-2 bg-surface border border-outline-variant rounded focus:outline-primary" />
                            @error('form.uacs_code') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1 text-on-surface">Budget Classification</label>
                            <select wire:model="form.budget_class" class="w-full text-xs p-2 bg-surface border border-outline-variant rounded focus:outline-primary">
                                <option value="">Select Class</option>
                                <option value="MOOE">MOOE</option>
                                <option value="CAPITAL_OUTLAY">Capital Outlay (PPE)</option>
                            </select>
                            @error('form.budget_class') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold mb-1 text-on-surface">Asset Tracking Type</label>
                            <select wire:model="form.tracking_type" class="w-full text-xs p-2 bg-surface border border-outline-variant rounded focus:outline-primary">
                                <option value="">Select Type</option>
                                <option value="CONSUMABLE">Consumable (Log only)</option>
                                <option value="UTILITY">Utility billing ledger</option>
                                <option value="CONTRACT">Service Contract</option>
                                <option value="SERVICE">Standard Service</option>
                                <option value="ICS">Inventory Custodian Slip (ICS)</option>
                                <option value="PAR">Property Ack. Receipt (PAR)</option>
                            </select>
                            @error('form.tracking_type') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1 text-on-surface">Audit/Required Attachment</label>
                            <input type="text" placeholder="e.g., Fuel Trip Tickets" wire:model="form.audit_requirement" class="w-full text-xs p-2 bg-surface border border-outline-variant rounded focus:outline-primary" />
                            @error('form.audit_requirement') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-outline-variant">
                        <button type="button" wire:click="$set('showApproveModal', false)" class="px-3.5 py-1.5 bg-surface-container-highest text-on-surface hover:bg-surface-container-high rounded text-xs">Cancel</button>
                        <button type="submit" class="px-3.5 py-1.5 bg-primary text-on-primary hover:bg-primary-hover rounded text-xs font-bold shadow">Approve & Register</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- 2. Reject Modal -->
    @if($showRejectModal && $this->selectedRequest)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-sm bg-surface border border-outline-variant rounded-xl shadow-2xl p-5 space-y-4">
                <h3 class="text-sm font-bold text-on-surface">❌ Reject Category Request</h3>
                <p class="text-xs text-on-surface-variant">Decline request for: <span class="font-bold text-on-surface">{{ $this->selectedRequest->requested_name }}</span></p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-on-surface">Rejection Reason (Sent to User)</label>
                        <textarea wire:model="form.rejection_reason" placeholder="Explain why this cannot be created..." class="w-full text-xs p-2 h-20 bg-surface border border-outline-variant rounded focus:outline-primary resize-none"></textarea>
                        @error('form.rejection_reason') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-outline-variant">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="px-3.5 py-1.5 bg-surface-container-highest text-on-surface rounded text-xs">Cancel</button>
                        <button type="button" wire:click="processRejection" class="px-3.5 py-1.5 bg-error text-on-error hover:bg-error-hover rounded text-xs font-bold shadow">Confirm Rejection</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
