<!-- Search & Filters -->
<div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm px-5 py-4 w-full">
    <form wire:submit="performSearch" class="flex gap-3 w-full">
        <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
            <input type="text" wire:model="searchQuery"
                   placeholder="Search APP by project title, description, or mode..."
                   class="w-full pl-9 pr-4 py-2.5 bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all placeholder-[#43474f]/40"/>
        </div>
        <button type="submit" class="px-5 py-2.5 bg-[#001e40] hover:bg-[#1f3f66] text-white font-bold text-sm rounded-lg shadow-sm hover:shadow active:scale-95 transition-all flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">search</span>
            Search
        </button>
        @if($searchQuery !== '' || $search !== '')
            <button type="button" wire:click="clearSearch" class="px-4 py-2.5 bg-white border border-[#c3c6d1] hover:border-[#ba1a1a] text-[#43474f] hover:text-[#ba1a1a] font-bold text-sm rounded-lg active:scale-95 transition-all flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">close</span>
                Clear
            </button>
        @endif
    </form>
</div>

<!-- Selection Basket Summary Bar (Sticky) -->
@php
    $hasSelections = !is_null($selectedAppLineId);
@endphp
<div class="px-5 py-3 rounded-xl flex items-center gap-4 transition-all duration-300
    {{ $hasSelections ? 'bg-[#001e40] text-white shadow-lg' : 'bg-[#eeedf2] text-[#43474f] border border-[#c3c6d1]/40' }}">
    <span class="material-symbols-outlined {{ $hasSelections ? 'text-[#7ba8e0]' : 'text-[#43474f]/60' }}">shopping_bag</span>
    <p class="font-bold text-sm flex-1">
        @if($hasSelections && $this->selectedAppLine)
            Selected Source APP Line: <span class="underline">{{ $this->selectedAppLine->project_title }}</span>
        @else
            No APP line selected yet
        @endif
    </p>
    <button wire:click="nextStep" 
            @disabled(!$hasSelections)
            class="px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2
            {{ $hasSelections ? 'bg-white text-[#001e40] hover:bg-[#eeedf2] active:scale-95 shadow-sm' : 'bg-white/50 text-[#43474f]/40 cursor-not-allowed border border-[#c3c6d1]/40' }}">
        Next: Configure Items & PR Info
        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
    </button>
</div>
