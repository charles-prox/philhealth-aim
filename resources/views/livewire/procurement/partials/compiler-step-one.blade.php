{{-- Filter Room --}}
<div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm px-5 py-4 flex flex-wrap gap-3 items-center">
    <p class="text-[11px] font-bold uppercase tracking-widest text-[#43474f] mr-2">Filters</p>

    {{-- Search --}}
    <div class="relative flex-1 min-w-[200px]">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
        <input type="text" wire:model="search"
               placeholder="Search particulars…"
               class="w-full pl-9 pr-4 py-2.5 bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all placeholder-[#43474f]/40"/>
    </div>

    {{-- Office Filter --}}
    <div class="w-64">
        <x-form-select label="" 
                       placeholder="All Offices" 
                       icon="corporate_fare" 
                       searchable
                       wire:model="filterOfficeId" 
                       :options="$this->offices->pluck('name', 'id')->toArray()" />
    </div>

    {{-- Category Filter --}}
    <div class="w-64">
        <x-form-select label="" 
                       placeholder="All Categories" 
                       icon="category" 
                       searchable
                       wire:model="filterCategory" 
                       :options="$this->categories->combine($this->categories)->toArray()" />
    </div>

    {{-- Apply Filters Button --}}
    <button wire:click="applyFilters"
            class="px-4 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-1.5 shadow-sm">
        <span class="material-symbols-outlined text-[18px]">filter_alt</span>
        Apply
    </button>

    {{-- Clear --}}
    @if($search || $filterOfficeId || $filterCategory)
        <button wire:click="clearFilters"
                class="px-3 py-2.5 text-[12px] font-bold text-[#43474f] hover:text-[#ba1a1a] transition-colors flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>Clear
        </button>
    @endif
</div>

{{-- Selection Basket Bar --}}
@if($this->selectionCount > 0)
    <div class="bg-[#001e40] text-white px-5 py-3 rounded-xl flex items-center gap-4 shadow-lg">
        <span class="material-symbols-outlined text-[#7ba8e0]">shopping_bag</span>
        <p class="font-bold text-sm flex-1">
            <span class="text-white">{{ $this->selectionCount }}</span>
            <span class="text-white/70"> allocation{{ $this->selectionCount > 1 ? 's' : '' }} selected</span>
            @if($this->selectionEstimatedValue > 0)
                · Est. <span class="text-white">₱{{ number_format($this->selectionEstimatedValue, 2) }}</span>
            @endif
        </p>
        <button wire:click="clearSelection"
                class="text-[12px] font-bold text-white/70 hover:text-white transition-colors flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">delete_sweep</span>Clear
        </button>
        <button x-on:click="$dispatch('close-pr-creation')"
                class="text-[12px] font-bold text-red-300 hover:text-red-100 transition-colors flex items-center gap-1 mr-2">
            <span class="material-symbols-outlined text-[16px]">close</span>Cancel PR
        </button>
        <button wire:click="nextStep"
                class="bg-white text-[#001e40] px-4 py-2 rounded-lg font-bold text-sm hover:bg-[#eeedf2] active:scale-95 transition-all flex items-center gap-2">
            Next: Details
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
    </div>
@endif

{{-- Modern Card Registry --}}
<div class="space-y-4 relative">
    
    {{-- List Header Card --}}
    <div class="bg-white border border-[#c3c6d1] rounded-2xl px-6 py-4 flex items-center justify-between gap-4 shadow-2xs">
        <h3 class="font-bold text-[#001e40] text-sm md:text-base flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-[#001e40]/70">inventory_2</span>
            {{ $cobItems->total() }} item{{ $cobItems->total() !== 1 ? 's' : '' }} pending procurement
        </h3>
        <div class="flex items-center gap-3">
            <button wire:click="selectAll" class="text-[12px] font-bold text-[#001e40] hover:text-[#1f3f66] flex items-center gap-1.5 transition-colors">
                <span class="material-symbols-outlined text-[18px]">select_all</span>Select All (Page)
            </button>
        </div>
    </div>

    {{-- Unified Loading Overlay --}}
    <div wire:loading class="absolute inset-0 bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center rounded-2xl transition-all">
        <div class="flex flex-col items-center gap-2 bg-white/80 p-5 rounded-2xl border border-[#eeedf2] shadow-sm">
            <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
            <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
        </div>
    </div>

    {{-- Cards Container --}}
    <div class="space-y-4" x-data="{ expandedIds: [] }">
        @forelse($cobItems as $cobItem)
            @php
                $distIds = $cobItem->distributions->pluck('id')->toArray();
                $isFullySelected = !empty($distIds) && empty(array_diff($distIds, $selectedIds));
                $totalAllocatedQty = $cobItem->distributions->sum('allocated_quantity');
                $recomQty = $cobItem->recom_qty ?? 0;
                $unitCost = $recomQty > 0 ? ((float) ($cobItem->recom_amount ?? 0) / $recomQty) : 0.0;
                $totalCost = $totalAllocatedQty * $unitCost;
            @endphp
            
            <!-- Modern Rich Card for COB Item -->
            <div class="bg-white border-2 rounded-2xl transition-all duration-200 relative overflow-hidden shadow-2xs
                        {{ $isFullySelected ? 'border-[#001e40] bg-[#f9f9fe] shadow-sm' : 'border-[#eeedf2] hover:border-[#c3c6d1] hover:shadow-xs' }}">
                
                <!-- Card Body -->
                <div class="p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    
                    <!-- Left Details Section -->
                    <div class="flex items-start gap-4 flex-1">
                        <!-- Checkbox Trigger Box -->
                        <div wire:click="toggleCobItemSelection('{{ $cobItem->id }}')" 
                             class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all cursor-pointer flex-shrink-0 mt-1
                                    {{ $isFullySelected ? 'bg-[#001e40] border-[#001e40]' : 'border-[#c3c6d1] hover:border-[#001e40]' }}">
                            @if($isFullySelected)
                                <span class="material-symbols-outlined text-white text-[16px] font-bold">check</span>
                            @endif
                        </div>
                        
                        <div class="space-y-1.5 flex-1">
                            <!-- Category Badge -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-md bg-[#eeedf2] text-[#43474f]">
                                    {{ $cobItem->exp_desc ?: 'Uncategorized' }}
                                </span>
                            </div>
                            
                            <!-- Particulars Title -->
                            <h4 class="font-bold text-[#001e40] text-base leading-snug">
                                {{ $cobItem->full_particulars ?? $cobItem->exp_desc ?? '—' }}
                            </h4>
                        </div>
                    </div>
                    
                    <!-- Middle Metrics Section -->
                    <div class="flex flex-wrap items-center justify-between sm:justify-start gap-6 sm:gap-10 border-t lg:border-t-0 border-[#eeedf2] pt-4 lg:pt-0">
                        <!-- Qty Metric -->
                        <div class="space-y-0.5">
                            <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/60">Total Qty</p>
                            <p class="text-sm font-bold text-[#001e40]">
                                {{ number_format($totalAllocatedQty) }} <span class="text-xs font-normal text-[#43474f]">{{ $cobItem->unit }}</span>
                            </p>
                        </div>
                        
                        <!-- Unit Cost Metric -->
                        <div class="space-y-0.5">
                            <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/60">Unit Cost</p>
                            <p class="text-sm font-bold text-[#001e40]">
                                {{ $unitCost > 0 ? '₱' . number_format($unitCost, 2) : '—' }}
                            </p>
                        </div>
                        
                        <!-- Total Value Metric -->
                        <div class="space-y-0.5">
                            <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/60">Estimated Total</p>
                            <div class="px-3 py-1 bg-[#001e40]/5 rounded-xl border border-[#001e40]/10">
                                <p class="text-sm font-extrabold text-[#001e40]">
                                    ₱{{ number_format($totalCost, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Action Button -->
                    <div class="flex items-center gap-2 border-t lg:border-t-0 border-[#eeedf2] pt-4 lg:pt-0">
                        <button x-on:click="expandedIds.includes('{{ $cobItem->id }}') ? expandedIds = expandedIds.filter(id => id !== '{{ $cobItem->id }}') : expandedIds.push('{{ $cobItem->id }}')"
                                class="w-full sm:w-auto flex items-center justify-center gap-1.5 px-3 py-2 hover:bg-[#eeedf2] rounded-xl text-xs font-bold text-[#001e40] transition-all focus:outline-none border border-[#eeedf2] hover:border-[#c3c6d1]"
                                title="Toggle Distributions">
                            <span class="material-symbols-outlined text-[18px] transition-transform duration-200"
                                  :class="expandedIds.includes('{{ $cobItem->id }}') ? 'rotate-180' : ''">
                                expand_more
                            </span>
                            Distributions
                        </button>
                    </div>
                </div>
                
                <!-- Collapsible Allocation Details Distributions Section -->
                <div x-show="expandedIds.includes('{{ $cobItem->id }}')" 
                     x-transition 
                     class="border-t border-[#eeedf2] bg-[#fdfdfd] p-5"
                     style="display: none;">
                    <div class="bg-white border border-[#eeedf2] rounded-xl p-4 shadow-2xs space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-[#eeedf2]">
                            <h5 class="text-xs font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-[#001e40]/70">hub</span>
                                Distributions
                            </h5>
                            <span class="text-[10px] font-bold text-[#43474f]/70 bg-[#eeedf2] px-2 py-0.5 rounded-full uppercase">
                                {{ count($cobItem->distributions) }} Office Allocation(s)
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($cobItem->distributions as $dist)
                                <div class="bg-[#f9f9fe] border border-[#eeedf2] rounded-lg p-3 flex flex-col justify-between hover:shadow-2xs transition-all">
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="space-y-1">
                                            <p class="text-xs font-bold text-[#001e40] leading-tight">
                                                {{ $dist->employee?->fullname ?? 'Unassigned Sub-End User' }}
                                            </p>
                                            <p class="text-[10px] font-semibold text-[#43474f]/80 uppercase tracking-wide">
                                                {{ $dist->office?->name ?? 'Unknown Office' }}
                                            </p>
                                        </div>
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-white border border-[#eeedf2] rounded-lg text-[#001e40] whitespace-nowrap shadow-2xs">
                                            {{ number_format($dist->allocated_quantity) }} {{ $cobItem->unit }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white border border-[#c3c6d1] rounded-2xl p-16 text-center">
                <div class="flex flex-col items-center gap-3 text-[#43474f]">
                    <span class="material-symbols-outlined text-[64px] text-[#c3c6d1]">inbox</span>
                    <p class="font-bold text-[#001e40] text-lg">No Unprocured Allocations</p>
                    <p class="text-[13px] max-w-sm">
                        @if($search || $filterOfficeId || $filterCategory)
                            No allocations match your current filters.
                        @else
                            All distributions have been compiled into PRs, or no allocations have been set up yet.
                            <a href="{{ route('cob.distribution') }}" wire:navigate class="font-bold text-[#001e40] underline">Go to Distribution Matrix →</a>
                        @endif
                    </p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination Card --}}
    @if($cobItems->hasPages())
        <div class="px-6 py-4 bg-white border border-[#c3c6d1] rounded-2xl flex flex-col md:flex-row justify-between items-center gap-4 shadow-2xs">
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                Showing {{ $cobItems->firstItem() }}–{{ $cobItems->lastItem() }} of {{ number_format($cobItems->total()) }} items
            </p>
            <div class="cob-pagination">
                {{ $cobItems->links() }}
            </div>
        </div>
        
        <style>
            /* Custom styles to fix/beautify Livewire pagination */
            .cob-pagination nav div:first-child { display: none; }
            .cob-pagination nav div:last-child { display: flex; gap: 0.5rem; }
            .cob-pagination nav span[aria-current="page"] span {
                background-color: #001e40 !important;
                color: white !important;
                border-color: #001e40 !important;
                border-radius: 0.5rem;
                padding: 0.5rem 0.85rem;
                font-weight: 700;
                font-size: 0.875rem;
            }
            .cob-pagination nav a, .cob-pagination nav span[aria-disabled="true"] span {
                background-color: white;
                color: #43474f;
                border: 1px solid #c3c6d1;
                border-radius: 0.5rem;
                padding: 0.5rem 0.85rem;
                font-weight: 700;
                font-size: 0.875rem;
                text-decoration: none;
                transition: all 0.2s;
            }
            .cob-pagination nav a:hover {
                background-color: #f4f3f8;
                border-color: #001e40;
                color: #001e40;
            }
            .cob-pagination svg { width: 1.25rem; height: 1.25rem; }
        </style>
    @endif
</div>
