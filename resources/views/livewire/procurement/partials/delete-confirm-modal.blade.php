{{-- Delete Confirmation Modal --}}
@if($confirmingDeleteId)
    <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-white border border-[#eeedf2] rounded-xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div class="flex items-center gap-3 text-rose-600">
                <span class="material-symbols-outlined text-[32px]">warning</span>
                <h4 class="text-lg font-bold">Confirm Deletion</h4>
            </div>
            <p class="text-xs text-[#43474f] leading-relaxed">
                Are you sure you want to delete this Purchase Request draft? This action is irreversible, and all locked allocations will be released back to the COB Matrix.
            </p>
            <div class="flex justify-end gap-3 pt-2">
                <button wire:click="$set('confirmingDeleteId', null)" class="px-4 py-2 bg-white border border-[#c3c6d1] hover:bg-[#f4f3f8] text-[#43474f] font-bold text-xs rounded-lg transition-all">
                    Cancel
                </button>
                <button wire:click="deletePr" class="px-4 py-2 bg-[#ba1a1a] hover:bg-[#ba1a1a]/95 text-white font-bold text-xs rounded-lg shadow-md transition-all text-center">
                    Yes, Delete & Release
                </button>
            </div>
        </div>
    </div>
@endif
