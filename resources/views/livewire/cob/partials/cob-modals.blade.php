{{-- Modal: New Budget Year --}}
@if($showYearForm)
@teleport('body')
<div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showYearForm', false)"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 z-10">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h3 class="text-xl font-bold text-[#001e40]">Create Budget Year</h3>
                <p class="text-[13px] text-[#43474f] mt-1">Define a new fiscal period for the COB system.</p>
            </div>
            <button wire:click="$set('showYearForm', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="space-y-4">
            <x-form-input label="Fiscal Year" icon="calendar_month" placeholder="e.g. 2026" wire:model="newYear" type="number" />
        </div>
        @error('newYear')<p class="text-[12px] text-[#ba1a1a] font-bold mt-2">{{ $message }}</p>@enderror
        <div class="flex justify-end gap-3 mt-6">
            <x-primary-button variant="secondary" wire:click="$set('showYearForm', false)">Cancel</x-primary-button>
            <x-primary-button icon="add" wire:click="createBudgetYear">Create Budget Year</x-primary-button>
        </div>
    </div>
</div>
@endteleport
@endif

{{-- Modal: New COB Version --}}
@if($showVersionForm)
@teleport('body')
<div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showVersionForm', false)"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 z-10">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h3 class="text-xl font-bold text-[#001e40]">Initialize COB Version</h3>
                <p class="text-[13px] text-[#43474f] mt-1">Create a version container before uploading the WFP Excel file.</p>
            </div>
            <button wire:click="$set('showVersionForm', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="space-y-4">
            <x-form-select label="Budget Year" icon="calendar_month" wire:model="selectedYearId"
                :options="$budgetYears->pluck('fiscal_year', 'id')->map(fn($year) => 'FY ' . $year)->toArray()" placeholder="Select a year" />
            <x-form-input label="Version Name" icon="label" placeholder="e.g. Original" wire:model="versionName" />
        </div>
        @error('selectedYearId')<p class="text-[12px] text-[#ba1a1a] font-bold mt-2">{{ $message }}</p>@enderror
        <div class="flex justify-end gap-3 mt-6">
            <x-primary-button variant="secondary" wire:click="$set('showVersionForm', false)">Cancel</x-primary-button>
            <x-primary-button icon="folder_open" wire:click="createVersion">Initialize Version</x-primary-button>
        </div>
    </div>
</div>
@endteleport
@endif

{{-- Modal: Upload WFP Excel --}}
@if($showUploadModal)
@teleport('body')
<div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showUploadModal', false)"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl p-8 z-10" 
         x-data="{ isDragging: false }"
         @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))">
        
        <div class="flex justify-between items-start mb-6">
            <div>
                <h3 class="text-xl font-bold text-[#001e40]">Upload WFP Document</h3>
                <p class="text-[13px] text-[#43474f] mt-1">Upload the Work and Financial Plan (Excel/CSV) for processing.</p>
            </div>
            <button wire:click="$set('showUploadModal', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="space-y-6">
            {{-- Drop Zone --}}
            <div class="relative border-2 border-dashed rounded-2xl p-10 text-center transition-all duration-200"
                 :class="isDragging ? 'border-[#001e40] bg-[#f4f3f8]' : 'border-[#c3c6d1] bg-white'">
                
                <input type="file" class="hidden" x-ref="fileInput" wire:model="wfpFile" accept=".xlsx,.xls,.csv" />

                @if(!$wfpFile)
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-[#f4f3f8] flex items-center justify-center">
                            <span class="material-symbols-outlined text-[32px] text-[#001e40]">cloud_upload</span>
                        </div>
                        <div>
                            <p class="font-bold text-[#001e40]">Click to upload or drag and drop</p>
                            <p class="text-[12px] text-[#43474f] mt-1">Excel or CSV files up to 20MB</p>
                        </div>
                        <button type="button" @click="$refs.fileInput.click()" 
                                class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#003366] transition-all shadow-md">
                            Select File
                        </button>
                    </div>
                @else
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-green-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[32px] text-green-700">description</span>
                        </div>
                        <div>
                            <p class="font-bold text-green-800">File Selected</p>
                            <p class="text-[14px] text-[#001e40] font-mono mt-1">{{ $wfpFile->getClientOriginalName() }}</p>
                        </div>
                        <button type="button" wire:click="$set('wfpFile', null)" class="text-[12px] text-red-600 font-bold hover:underline">Remove File</button>
                    </div>
                @endif

                <div wire:loading wire:target="wfpFile" class="absolute inset-0 bg-white/80 backdrop-blur-[2px] flex items-center justify-center rounded-2xl">
                    <div class="flex flex-col items-center gap-3">
                        <span class="material-symbols-outlined animate-spin text-[32px] text-[#001e40]">sync</span>
                        <span class="text-sm font-bold text-[#001e40]">Uploading to server...</span>
                    </div>
                </div>
            </div>

            @if($wfpFile && !$uploading)
                <button wire:click="uploadWfp" 
                        class="w-full bg-[#001e40] text-white font-bold py-4 rounded-xl hover:bg-[#003366] transition-all shadow-lg flex items-center justify-center gap-3">
                    <span class="material-symbols-outlined">play_circle</span>
                    Process & Map Budget DNA
                </button>
            @endif

            @if($uploading)
                <div class="w-full bg-[#f4f3f8] rounded-xl p-6 flex flex-col items-center gap-4">
                    <span class="material-symbols-outlined animate-spin text-[40px] text-[#001e40]">database</span>
                    <div class="text-center">
                        <p class="font-bold text-[#001e40]">Processing Budget Items...</p>
                        <p class="text-[12px] text-[#43474f] mt-1">Please do not close this window. We are seeding the database using optimized chunking.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endteleport
@endif

{{-- Modal: Create Revision --}}
@if($showRevisionModal)
@teleport('body')
<div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showRevisionModal', false)"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 z-10">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h3 class="text-xl font-bold text-[#001e40]">Create Revision</h3>
                <p class="text-[13px] text-[#43474f] mt-1">Initialize a new draft version. The current version will remain active until the revision is approved.</p>
            </div>
            <button wire:click="$set('showRevisionModal', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="space-y-4">
            <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl flex gap-3">
                <span class="material-symbols-outlined text-blue-600 text-[20px]">info</span>
                <p class="text-[12px] text-blue-800 leading-snug">
                    All budget lines and current encumbrances will be cloned into a new <b>DRAFT</b> version.
                </p>
            </div>
            
            <div class="space-y-1">
                <label class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider ml-1">Revision Remarks / Justification</label>
                <textarea wire:model="revisionRemarks" 
                          class="w-full bg-[#f4f3f8] border border-[#c3c6d1] rounded-xl p-4 text-sm focus:ring-[#001e40] focus:border-[#001e40] min-h-[120px]"
                          placeholder="Describe why this revision is being created (e.g., Mid-Year Adjustment, Supplemental Budget)..."></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-8">
            <x-primary-button variant="secondary" wire:click="$set('showRevisionModal', false)" wire:loading.attr="disabled">Cancel</x-primary-button>
            <x-primary-button wire:click="confirmRevision" wire:loading.attr="disabled" wire:target="confirmRevision" class="min-w-[180px]">
                <span class="flex items-center justify-center gap-2 whitespace-nowrap">
                    <span wire:loading.remove wire:target="confirmRevision" class="material-symbols-outlined text-[20px]">content_copy</span>
                    <svg wire:loading wire:target="confirmRevision" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    
                    <span wire:loading.remove wire:target="confirmRevision">Initialize Revision</span>
                    <span wire:loading wire:target="confirmRevision">Initializing...</span>
                </span>
            </x-primary-button>
        </div>
    </div>
</div>
@endteleport
@endif

{{-- Modal: Import Result --}}
@if($showResultModal)
@teleport('body')
<div class="fixed inset-0 z-[110] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#001e40]/60 backdrop-blur-md" wire:click="$set('showResultModal', false)"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden z-10">
        <div class="p-8 text-center">
            @if($importError)
                <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="material-symbols-outlined text-[40px] text-red-600">error</span>
                </div>
                <h3 class="text-xl font-bold text-[#001e40]">Import Failed</h3>
                <p class="text-sm text-[#43474f] mt-2 leading-relaxed">{{ $importError }}</p>
            @else
                <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="material-symbols-outlined text-[40px] text-green-600">check_circle</span>
                </div>
                <h3 class="text-xl font-bold text-[#001e40]">Import Successful</h3>
                <div class="mt-4 p-4 bg-[#f4f3f8] rounded-2xl inline-block">
                    <p class="text-3xl font-black text-[#001e40]">{{ number_format($importCount) }}</p>
                    <p class="text-[10px] font-bold text-[#43474f] uppercase tracking-widest mt-1">Budget Lines Mapped</p>
                </div>
                @if($importCount === 0)
                    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-[12px] text-amber-800 leading-tight">
                        <b>Zero items imported.</b> This usually means the Excel column headers don't match our required mapping.
                    </div>
                @else
                    <p class="text-sm text-[#43474f] mt-4 leading-relaxed">The Master DNA has been successfully seeded. You can now proceed to review the budget items.</p>
                @endif
            @endif
        </div>
        <div class="p-6 bg-[#f9f9fe] border-t border-[#eeedf2] flex justify-center">
            <button wire:click="$set('showResultModal', false)" 
                    class="px-8 py-3 bg-[#001e40] text-white font-bold rounded-xl hover:bg-[#003366] transition-all shadow-lg active:scale-95">
                Continue
            </button>
        </div>
    </div>
</div>
@endteleport
@endif

{{-- Modal: APP Upload & Activation --}}
@if($showAppModal && $appModalYear)
@teleport('body')
<div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showAppModal', false)"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl p-6 z-10 max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex justify-between items-start mb-4 pb-4 border-b border-[#eeedf2]">
            <div>
                <h3 class="text-lg font-bold text-[#001e40] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[24px]">gavel</span>
                    APP Upload & Activation (FY {{ $appModalYear }})
                </h3>
                <p class="text-xs text-[#43474f] mt-1">Upload CSV layout files, final signed PDF, and activate the procurement portal for this fiscal year.</p>
            </div>
            <button wire:click="$set('showAppModal', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="space-y-6">
            <livewire:procurement.app-manager :fiscal-year="$appModalYear" :is-modal="true" :key="'app-manager-modal-' . $appModalYear" />
        </div>
    </div>
</div>
@endteleport
@endif
