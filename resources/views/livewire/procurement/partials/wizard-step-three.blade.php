<div class="bg-white border border-[#c3c6d1] rounded-2xl space-y-4 p-8 shadow-sm mt-8 mb-6">
    <div class="border-b border-[#eeedf2] pb-4 flex justify-between items-center">
        <div>
            <h3 class="text-xl font-bold text-[#001e40]">Review Purchase Request</h3>
            <p class="text-xs text-[#43474f] mt-1">Review the bundled items before final submission.</p>
        </div>
        <span class="px-3 py-1.5 bg-[#eeedf2] text-[#43474f] text-[10px] font-bold rounded-full uppercase tracking-wider">Unsubmitted Draft</span>
    </div>

    <div class="border-2 border-dashed border-[#c3c6d1] rounded-2xl p-6 bg-[#f9f9fe] space-y-5">
        <div class="flex justify-between items-start">
            <div class="space-y-1.5">
                <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">PhilHealth AIM · Region X</p>
                <h4 class="text-lg font-bold text-[#001e40]">PR Proposal</h4>
                @if($form->purpose)
                    <p class="text-[12px] text-[#43474f] leading-relaxed max-w-2xl"><strong class="text-[#001e40]">Purpose:</strong> {{ $form->purpose }}</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/50">Tracking Number</p>
                <p class="font-mono text-sm font-bold text-[#43474f]/70 bg-[#eeedf2]/50 px-2.5 py-1 rounded-lg inline-block border border-[#c3c6d1] mt-1">{{ $form->trackingNumber }}</p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="border border-[#c3c6d1] rounded-xl overflow-hidden bg-white">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#f4f3f8] border-b border-[#c3c6d1]">
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Project Header</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Particulars / Description</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Qty</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit Cost</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eeedf2]">
                    @foreach($basket as $item)
                        <tr>
                            <td class="px-4 py-3 text-xs text-[#43474f] font-bold">{{ $item['project_title'] }}</td>
                            <td class="px-4 py-3 text-xs text-[#1a1c1f]">{{ $item['description'] }}</td>
                            <td class="px-4 py-3 text-xs text-center text-[#43474f]">{{ $item['unit'] }}</td>
                            <td class="px-4 py-3 text-xs text-right font-bold text-[#001e40]">{{ $item['qty'] }}</td>
                            <td class="px-4 py-3 text-xs text-right text-[#43474f]">₱{{ number_format($item['unit_cost'], 2) }}</td>
                            <td class="px-4 py-3 text-xs text-right font-bold text-[#1a1c1f]">₱{{ number_format($item['total_cost'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Overall Total -->
        <div class="flex justify-between items-center bg-[#001e40]/5 px-5 py-3.5 rounded-xl border border-[#001e40]/10">
            <span class="text-xs font-bold text-[#001e40] uppercase tracking-wider">Estimated Total Value</span>
            <span class="text-lg font-black text-[#001e40]">₱{{ number_format($this->totalBasketValue, 2) }}</span>
        </div>

        {{-- Signatories --}}
        @if($form->recommendedById && $form->approvedById)
            @php
                $recEmp  = \App\Models\Employee::find($this->form->recommendedById);
                $appEmp  = \App\Models\Employee::find($this->form->approvedById);
            @endphp
            @if($recEmp && $appEmp)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-t border-[#eeedf2] pt-5 mt-5">
                    <div class="space-y-1">
                        <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">Recommended By</p>
                        <p class="text-xs font-bold text-[#001e40]">{{ $recEmp->fullname }}</p>
                        <p class="text-[10px] text-[#43474f]/70 italic">{{ $recEmp->designation }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">Approved By</p>
                        <p class="text-xs font-bold text-[#001e40]">{{ $appEmp->fullname }}</p>
                        <p class="text-[10px] text-[#43474f]/70 italic">{{ $appEmp->designation }}</p>
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Supporting Attachments Card --}}
    <div class="border-t border-[#eeedf2] pt-6 space-y-3">
        <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f]">
            Supporting Procurement Attachments
            @if($this->folder && $this->folder->status === 'RETURNED_FOR_COMPLIANCE')
                <span class="text-red-500 ml-0.5">*</span>
            @endif
        </label>
        
        @if(!$this->folder || $this->folder->status !== 'REJECTED')
            <div x-data="{ isDragging: false }" 
                 @dragover.prevent="isDragging = true" 
                 @dragleave.prevent="isDragging = false" 
                 @drop.prevent="isDragging = false; $wire.uploadMultiple('fileOthers', $event.dataTransfer.files)"
                 class="border-2 border-dashed rounded-2xl p-6 transition-all duration-200 text-center cursor-pointer relative flex flex-col items-center justify-center min-h-[140px]"
                 :class="isDragging ? 'border-[#001e40] bg-[#001e40]/5' : 'border-[#c3c6d1] hover:border-[#001e40] bg-white/50 hover:bg-white'">
                 
                <!-- Live Uploading Overlay State -->
                <div wire:loading wire:target="fileOthers" class="absolute inset-0 bg-white/85 rounded-2xl flex flex-col items-center justify-center gap-2 z-20">
                    <div class="w-8 h-8 border-4 border-[#001e40] border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs font-bold text-[#001e40]">Uploading attachments, please wait...</p>
                </div>

                <input type="file" 
                       x-ref="fileInput" 
                       wire:model="fileOthers" 
                       multiple 
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"/>
                       
                <div class="flex flex-col items-center justify-center gap-2 pointer-events-none">
                    <span class="material-symbols-outlined text-[32px] transition-colors" 
                          :class="isDragging ? 'text-[#001e40]' : 'text-[#43474f]/60'">
                        cloud_upload
                    </span>
                    <div class="text-xs">
                        <span class="font-bold text-[#001e40] underline">Click to upload</span> or drag and drop files here
                    </div>
                    <p class="text-[10px] text-[#43474f]/60">Supporting spec sheets, justifications, or compliance revisions (Max 5 files, 10MB per file).</p>
                </div>
            </div>
            @error('fileOthers') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
            @error('fileOthers.*') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
        @endif

        {{-- Staged Attachments (Ready to Save) --}}
        @if(!empty($this->stagedFiles))
            <div class="space-y-2 mt-4">
                <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Staged Attachments (Ready to Save)</span>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($this->stagedFiles as $index => $file)
                        <div class="p-4 bg-white border border-[#eeedf2] rounded-xl space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 truncate text-xs font-semibold text-[#001e40]">
                                    <span class="material-symbols-outlined text-[18px] text-[#001e40]/60">draft</span>
                                    <span class="truncate" title="{{ $file->getClientOriginalName() }}">{{ $file->getClientOriginalName() }}</span>
                                </span>
                                <button type="button" wire:click="removeStagedFile({{ $index }})" class="text-xs font-bold text-[#ba1a1a] hover:underline shrink-0">Remove</button>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Document Name <span class="text-red-600">*</span></label>
                                <input type="text" 
                                       wire:model="stagedFileNames.{{ $index }}" 
                                       placeholder="e.g. Technical Specification, Canvass Sheet" 
                                       class="w-full px-3 py-2 bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg text-xs outline-none focus:ring-2 focus:ring-[#001e40] transition-all"/>
                                @error('stagedFileNames.' . $index)
                                    <p class="text-[10px] font-bold text-[#ba1a1a] mt-0.5">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Existing Saved Attachments --}}
        @if($this->folder && $this->folder->attachments->isNotEmpty())
            <div class="space-y-2 mt-4">
                <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Existing Saved Attachments</span>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($this->folder->attachments as $attach)
                        <div class="p-3 bg-[#e8f5e9] text-[#2e7d32] border border-[#a5d6a7]/30 rounded-xl flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2 truncate pr-2">
                                <span class="material-symbols-outlined text-[18px]">
                                    {{ str_starts_with($attach->attachment_type, 'SYSTEM_') ? 'auto_stories' : 'description' }}
                                </span>
                                <span class="truncate font-semibold">{{ $attach->original_name }}</span>
                                <span class="text-[9px] px-1.5 py-0.5 bg-[#c8e6c9] text-[#1b5e20] rounded font-bold uppercase">{{ str_replace('SYSTEM_', '', $attach->attachment_type) }}</span>
                            </span>
                            <a href="{{ route('admin.file-stream', $attach->id) }}" target="_blank" class="font-bold underline hover:text-[#001e40] shrink-0">View</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Actions --}}
    <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
        <button wire:click="prevStep" class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Details
        </button>
        @if(!$entirelyLocked)
            <div class="flex items-center gap-3">
                <button wire:click="processPrGeneration" wire:loading.attr="disabled" class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md disabled:opacity-60">
                    <span wire:loading wire:target="processPrGeneration" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <span wire:loading.remove wire:target="processPrGeneration" class="material-symbols-outlined text-[18px]">visibility</span> 
                    <span>Compile & Review PR</span>
                </button>
            </div>
        @endif
    </div>
</div>
