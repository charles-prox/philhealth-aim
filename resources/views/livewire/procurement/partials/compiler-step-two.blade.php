{{-- Step 2: PR Details --}}
<div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm flex flex-col gap-6 mt-4 mb-6">
    <div class="border-b border-[#eeedf2] pb-4">
        <h3 class="text-xl font-bold text-[#001e40]">Enter Purchase Request Details</h3>
        <p class="text-xs text-[#43474f] mt-1">Specify the official PR tracking number and operational purpose for this compiled bundle.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
        {{-- Left Side: Form Inputs --}}
        <div class="space-y-5 flex flex-col h-full">
            {{-- Numbers Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Tracking Number --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">
                        Tracking Number <span class="text-[#43474f]/50">(Auto-generated)</span>
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f]/60 text-[18px]">lock</span>
                        <input type="text" wire:model="compileTrackingNumber" readonly disabled
                               class="w-full pl-9 pr-4 py-3 bg-[#eeedf2]/50 border border-[#c3c6d1] rounded-xl text-sm outline-none transition-all font-mono font-bold text-[#43474f]/70 cursor-not-allowed"/>
                    </div>
                    <p class="text-[9px] text-[#43474f]/60 mt-1">System-managed tracking reference.</p>
                </div>

                {{-- PR Number --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">
                        Purchase Request (PR) Number <span class="text-[#ba1a1a]">*</span>
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#001e40] text-[18px]">edit_note</span>
                        <input type="text" wire:model="compilePrNumber"
                               placeholder="e.g. 2601PR-001"
                               class="w-full pl-9 pr-4 py-3 bg-[#f9f9fe] border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all font-mono font-bold text-[#001e40]"/>
                    </div>
                    @error('compilePrNumber') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                    <p class="text-[9px] text-[#43474f]/60 mt-1">Initially matches the next available PR series format (YYMMPR-XXX). You may customize it.</p>
                </div>
            </div>

            {{-- Signatories Selection --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">
                        Recommended By <span class="text-[#ba1a1a]">*</span>
                    </label>
                    <x-form-select label="" 
                                   placeholder="Select Recommending Officer..." 
                                   icon="recommend" 
                                   searchable
                                   wire:model="recommendedById" 
                                   :options="$this->validRecommenders" />
                    @error('recommendedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">
                        Approved By <span class="text-[#ba1a1a]">*</span>
                    </label>
                    <x-form-select label="" 
                                   placeholder="Select Approving Officer..." 
                                   icon="person_check" 
                                   searchable
                                   wire:model="approvedById" 
                                   :options="$this->validApprovers" />
                    @error('approvedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Purpose / Justification --}}
            <div class="flex-1 flex flex-col">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">
                    Purpose / Justification <span class="text-[#ba1a1a]">*</span>
                </label>
                <textarea wire:model="compilePurpose"
                          placeholder="Provide the official purpose or justification for compiling these items into a Purchase Request..."
                          class="w-full flex-1 px-4 py-3 bg-[#f9f9fe] border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all resize-none leading-relaxed min-h-[160px]"></textarea>
                @error('compilePurpose') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Right Side: Minimal Summary Panel --}}
        <div class="bg-[#f9f9fe] border border-[#eeedf2] rounded-2xl p-6 flex flex-col justify-between h-full">
            <div class="flex flex-col gap-6">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-[#001e40]/10 flex items-center justify-center text-[#001e40]">
                        <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider">PR Compilation Summary</h4>
                        <p class="text-[10px] text-[#43474f]/70">Review the metadata aggregates below before continuing</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white border border-[#eeedf2] rounded-xl p-4 shadow-sm">
                        <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Total Items</span>
                        <span class="text-2xl font-bold text-[#001e40] block mt-1">{{ $this->selectionUniqueItemsCount }}</span>
                    </div>
                    <div class="bg-white border border-[#eeedf2] rounded-xl p-4 shadow-sm">
                        <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Estimated Value</span>
                        <span class="text-2xl font-bold text-green-700 block mt-1">₱{{ number_format($this->selectionEstimatedValue, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-[#eeedf2] pt-4 space-y-3 text-xs mt-auto">
                <div class="flex justify-between items-center">
                    <span class="text-[#43474f]">Status</span>
                    <span class="px-2.5 py-0.5 text-[9px] font-bold bg-[#eeedf2] text-[#001e40] rounded-full uppercase tracking-wider">DRAFT (STAGED)</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Step 2 Buttons --}}
    <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
        <button wire:click="prevStep"
                class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Back to Selection
        </button>
        <button wire:click="nextStep"
                class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md">
            Next: Review & Lock
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
    </div>
</div>
