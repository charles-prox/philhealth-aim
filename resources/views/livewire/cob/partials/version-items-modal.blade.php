{{-- Modal: Add/Edit Item --}}
@if($showItemModal)
@teleport('body')
<div class="fixed inset-0 z-[120] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#001e40]/60 backdrop-blur-md" wire:click="$set('showItemModal', false)"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden z-10">
        <div class="p-8">
            <div class="flex justify-between items-start mb-8">
                <div>
                    <h3 class="text-2xl font-bold text-[#001e40]">{{ $editingItemId ? 'Edit Budget Line' : 'Add New Budget Line' }}</h3>
                    <p class="text-sm text-[#43474f] mt-1">Manual entry for specific items not covered by the bulk import.</p>
                </div>
                <button wire:click="$set('showItemModal', false)" class="p-2 hover:bg-[#f4f3f8] rounded-xl transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div class="col-span-1">
                    <x-form-input label="PPA Code" icon="tag" placeholder="e.g. 1.1.1" wire:model="ppa_code" />
                </div>
                <div class="col-span-1">
                    <x-form-input label="Expense Class (exp_desc)" icon="category" placeholder="e.g. MOOE" wire:model="exp_desc" />
                </div>
                <div class="col-span-1">
                    <x-form-input label="Base" icon="database" placeholder="e.g. 2024 Base" wire:model="base" />
                </div>
                <div class="col-span-2">
                    <x-form-input label="PPA Description" icon="description" placeholder="Main PPA Description" wire:model="ppa_desc" />
                </div>
                <div class="col-span-2">
                    <x-form-input label="Sub PPA Description" icon="subdirectory_arrow_right" placeholder="Sub PPA Description" wire:model="sub_ppa_desc" />
                </div>
                <div class="col-span-2">
                    <x-form-input label="Full Particulars" icon="article" placeholder="Detailed item description" wire:model="full_particulars" />
                </div>
                <div class="col-span-1">
                    <x-form-input label="Qty" icon="numbers" type="number" wire:model="recom_qty" />
                </div>
                <div class="col-span-1">
                    <x-form-input label="Unit" icon="straighten" placeholder="e.g. Lot, Unit, Pc" wire:model="unit" />
                </div>
                <div class="col-span-2">
                    <x-form-input label="Recommended Amount" icon="payments" type="number" step="0.01" wire:model="recom_amount" />
                </div>
                <div class="col-span-2">
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider ml-1">Change Justification / Revision Remarks</label>
                        <textarea wire:model="revision_remarks"
                                  class="w-full bg-[#f4f3f8] border border-[#c3c6d1] rounded-xl p-4 text-sm focus:ring-[#001e40] focus:border-[#001e40] min-h-[100px]"
                                  placeholder="Explain why this budget line was changed or added during this revision..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 bg-[#f9f9fe] border-t border-[#eeedf2] flex justify-end gap-3">
            <x-primary-button variant="secondary" wire:click="$set('showItemModal', false)">Cancel</x-primary-button>
            <x-primary-button icon="save" wire:click="saveItem">
                {{ $editingItemId ? 'Update Budget Line' : 'Create Budget Line' }}
            </x-primary-button>
        </div>
    </div>
</div>
@endteleport
@endif
