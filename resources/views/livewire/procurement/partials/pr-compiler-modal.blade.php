{{-- PR Compiler Workspace Modal --}}
@if($isCreatingPr)
    <div class="fixed inset-0 bg-[#001e40]/40 backdrop-blur-md z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-[#f1f3f6] border border-[#eeedf2] rounded-xl max-w-7xl w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200 relative flex flex-col my-8 h-[90vh]">
            <!-- Modal Header -->
            <div class="bg-white px-6 py-4 flex justify-between items-center border-b border-[#eeedf2] rounded-t-xl flex-shrink-0">
                @php
                    $editingFolder = $editingFolderId ? \App\Models\ProcurementFolder::find($editingFolderId) : null;
                @endphp
                @if($editingFolder)
                    <div class="flex items-center gap-3">
                        <h3 class="text-sm font-bold text-[#001e40] uppercase tracking-wider">Edit Draft Purchase Request</h3>
                        <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200">Draft</span>
                        <span class="font-mono text-xs text-[#43474f]/70 bg-[#eeedf2]/50 px-2 py-0.5 rounded border border-[#c3c6d1]">{{ $editingFolder->tracking_number }}</span>
                    </div>
                @else
                    <h3 class="text-sm font-bold text-[#001e40] uppercase tracking-wider">Purchase Request Compilation Wizard</h3>
                @endif
                <button x-on:click="$dispatch('close-pr-creation')" class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all">
                    <span class="material-symbols-outlined text-[20px] font-bold">close</span>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="overflow-y-auto px-6 flex-1">
                 <livewire:procurement.end-user-portal :folder-id="$editingFolderId" :key="$editingFolderId ?? 'new-pr'" />
            </div>
        </div>
    </div>
@endif
