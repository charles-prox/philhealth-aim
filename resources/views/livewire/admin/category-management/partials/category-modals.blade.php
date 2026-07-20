<div>
    <!-- 1. Manage (Create / Edit) Modal -->
    @if($showManageModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-md bg-surface border border-outline-variant rounded-xl shadow-2xl p-6 space-y-4">
                <h3 class="text-base font-bold text-on-surface">
                    {{ $selectedCategoryId ? '📝 Edit Category Details' : '🏗️ Register New Category' }}
                </h3>

                <form wire:submit.prevent="save" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold mb-1 text-on-surface">Category Name (Plain English UI Label)</label>
                        <input type="text" wire:model="form.name" placeholder="e.g., Generator Fuel" class="w-full text-xs p-2 bg-surface border border-outline-variant rounded focus:outline-primary" />
                        @error('form.name') <span class="text-error text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold mb-1 text-on-surface">COA UACS Code</label>
                            <input type="text" placeholder="8-Digit Code" wire:model="form.uacs_code" class="w-full text-xs p-2 bg-surface border border-outline-variant rounded focus:outline-primary" />
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
                                <option value="CONSUMABLE">Consumable (Stock Log)</option>
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
                        <button type="button" wire:click="$set('showManageModal', false)" class="px-3.5 py-1.5 bg-surface-container-highest text-on-surface hover:bg-surface-container-high rounded text-xs">Cancel</button>
                        <button type="submit" class="px-3.5 py-1.5 bg-primary text-on-primary hover:bg-primary-hover rounded text-xs font-bold shadow">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- 2. Delete Confirmation Modal -->
    @if($showDeleteModal && $this->selectedCategory)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-sm bg-surface border border-outline-variant rounded-xl shadow-2xl p-5 space-y-4">
                <h3 class="text-sm font-bold text-on-surface">⚠️ Retire Procurement Category</h3>
                <p class="text-xs text-on-surface-variant">Are you sure you want to delete the category: <span class="font-bold text-on-surface">{{ $this->selectedCategory->name }}</span>?</p>

                <blockquote class="p-3 bg-warning-container text-on-warning-container rounded-lg text-[10px] font-semibold border-l-4 border-warning">
                    Warning: This action will permanently remove this category from the dropdown active listing. It will fail automatically if any PR documents are currently using it.
                </blockquote>

                <div class="flex justify-end gap-2 pt-3 border-t border-outline-variant">
                    <button type="button" wire:click="$set('showDeleteModal', false)" class="px-3.5 py-1.5 bg-surface-container-highest text-on-surface rounded text-xs">Cancel</button>
                    <button type="button" wire:click="destroy" class="px-3.5 py-1.5 bg-error text-on-error hover:bg-error-hover rounded text-xs font-bold shadow">Yes, Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
