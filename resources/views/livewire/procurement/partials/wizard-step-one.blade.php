@error('basket')
    <div class="text-sm font-bold text-[#ba1a1a] bg-red-50 px-4 py-2 rounded-xl border border-red-200">{{ $message }}</div>
@enderror

<!-- Catalog Layout -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
    <!-- Left Column: APP Catalog list -->
    <div class="lg:col-span-2 space-y-4 my-8">
        @forelse($this->appLineItems as $item)
            @php
                $available = $item->approved_budget - $item->utilized_budget;
                $inBasket = $this->isInBasket($item->id);
            @endphp
            <div class="bg-white border rounded-xl p-4 transition-all duration-200 border-[#eeedf2] hover:border-[#c3c6d1] hover:shadow-2xs relative">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded bg-[#eeedf2] text-[#43474f]">{{ $item->procurement_mode }}</span>
                            @if($item->is_epa)
                                <span class="px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded bg-blue-50 text-blue-700 border border-blue-100/50">EPA</span>
                            @endif
                            @if($inBasket)
                                <span class="px-2 py-0.5 bg-green-50 text-green-700 border border-green-200/60 text-[9px] font-bold uppercase rounded">Selected</span>
                            @endif
                        </div>
                        <div>
                            <!-- Highlighted Description -->
                            <h4 class="font-bold text-[#001e40] text-sm leading-snug">{{ $item->description }}</h4>
                            <!-- Muted Project Title -->
                            <p class="text-[11px] text-[#43474f]/70 mt-0.5 truncate" title="{{ $item->project_title }}">{{ $item->project_title }}</p>
                        </div>
                    </div>

                    <!-- Budget Info & Action Actions -->
                    <div class="flex items-center gap-6 shrink-0 justify-between md:justify-end">
                        <div class="text-left md:text-right">
                            <span class="text-[#43474f]/60 font-semibold block uppercase text-[9px] tracking-wider">Remaining Budget</span>
                            <span class="font-bold text-xs text-green-700">₱{{ number_format($available, 2) }}</span>
                            <span class="text-[10px] text-[#43474f]/40 block hidden md:block">of ₱{{ number_format($item->approved_budget, 2) }}</span>
                        </div>

                        <div>
                            @if($inputsDisabled)
                                @if(!$inBasket)
                                    <button disabled class="bg-gray-100 text-gray-400 border border-gray-200 px-3.5 py-2 rounded-lg text-xs font-bold cursor-not-allowed flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[14px]">add_shopping_cart</span>Select
                                    </button>
                                @else
                                    <button disabled class="text-xs font-bold text-gray-400 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200 cursor-not-allowed flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">remove_shopping_cart</span>Selected
                                    </button>
                                @endif
                            @else
                                @if(!$inBasket)
                                    @if($available > 0)
                                        <button wire:click="toggleSelection({{ $item->id }})" class="bg-[#001e40] text-white px-3.5 py-2 rounded-lg text-xs font-bold hover:bg-[#001e40]/90 active:scale-95 transition-all flex items-center gap-1.5 shadow-xs">
                                            <span class="material-symbols-outlined text-[14px]">add_shopping_cart</span>Select
                                        </button>
                                    @else
                                        <span class="text-[11px] text-[#ba1a1a] font-bold italic flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">block</span> Exhausted
                                        </span>
                                    @endif
                                @else
                                    <button wire:click="toggleSelection({{ $item->id }})" class="text-xs font-bold text-[#ba1a1a] hover:bg-red-50 px-3 py-2 rounded-lg border border-red-100 transition-all flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">remove_shopping_cart</span>Deselect
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white border border-[#c3c6d1] rounded-2xl p-16 text-center">
                <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">search_off</span>
                <p class="font-bold text-[#001e40] mt-2">No APP line items found.</p>
            </div>
        @endforelse

        <div class="pt-2">
            @if($this->appLineItems->hasPages())
                <div class="flex items-center justify-between border border-[#eeedf2] bg-white px-5 py-3 rounded-xl shadow-2xs">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button wire:click="previousPage" class="px-4 py-2 bg-white border border-[#c3c6d1] hover:border-[#001e40] text-[#43474f] font-bold text-xs rounded-xl active:scale-95 transition-all" @if($this->appLineItems->onFirstPage()) disabled @endif>
                            Previous
                        </button>
                        <button wire:click="nextPage" class="ml-3 px-4 py-2 bg-white border border-[#c3c6d1] hover:border-[#001e40] text-[#43474f] font-bold text-xs rounded-xl active:scale-95 transition-all" @if(!$this->appLineItems->hasMorePages()) disabled @endif>
                            Next
                        </button>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs text-[#43474f]">
                                Showing <span class="font-bold">{{ $this->appLineItems->firstItem() }}</span> to <span class="font-bold">{{ $this->appLineItems->lastItem() }}</span> of <span class="font-bold">{{ $this->appLineItems->total() }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="flex items-center gap-1.5" aria-label="Pagination">
                                {{-- Previous Page Button --}}
                                <button wire:click="previousPage" class="bg-white border border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40] p-1.5 rounded-lg disabled:opacity-40 disabled:pointer-events-none transition-all active:scale-95 flex items-center justify-center" @if($this->appLineItems->onFirstPage()) disabled @endif>
                                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                                </button>

                                {{-- Custom Page Numbers --}}
                                @php
                                    $currentPage = $this->appLineItems->currentPage();
                                    $lastPage = $this->appLineItems->lastPage();
                                @endphp

                                @if($lastPage <= 7)
                                    {{-- If total pages is small, show all pages --}}
                                    @for($i = 1; $i <= $lastPage; $i++)
                                        <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                            {{ $i }}
                                        </button>
                                    @endfor
                                @else
                                    @if($currentPage <= 4)
                                        {{-- Show pages 1 to 5 --}}
                                        @for($i = 1; $i <= 5; $i++)
                                            <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor

                                        {{-- Show ellipsis --}}
                                        <span class="text-[#43474f]/50 border-none font-bold min-w-[20px] select-none text-center">...</span>

                                        {{-- Show last 2 pages --}}
                                        @for($i = $lastPage - 1; $i <= $lastPage; $i++)
                                            <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor
                                    @elseif($currentPage >= $lastPage - 3)
                                        {{-- Show first 2 pages --}}
                                        @for($i = 1; $i <= 2; $i++)
                                            <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor

                                        {{-- Show ellipsis --}}
                                        <span class="text-[#43474f]/50 border-none font-bold min-w-[20px] select-none text-center">...</span>

                                        {{-- Show last 5 pages --}}
                                        @for($i = $lastPage - 4; $i <= $lastPage; $i++)
                                            <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor
                                    @else
                                        {{-- Show first 2 pages --}}
                                        @for($i = 1; $i <= 2; $i++)
                                            <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor

                                        {{-- Show ellipsis --}}
                                        <span class="text-[#43474f]/50 border-none font-bold min-w-[20px] select-none text-center">...</span>

                                        {{-- Show sliding window of 3 pages --}}
                                        @for($i = $currentPage - 1; $i <= $currentPage + 1; $i++)
                                            <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor

                                        {{-- Show ellipsis --}}
                                        <span class="text-[#43474f]/50 border-none font-bold min-w-[20px] select-none text-center">...</span>

                                        {{-- Show last 2 pages --}}
                                        @for($i = $lastPage - 1; $i <= $lastPage; $i++)
                                            <button wire:click="gotoPage({{ $i }})" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all active:scale-95 flex items-center justify-center min-w-[32px] min-h-[32px] {{ $currentPage === $i ? 'bg-[#001e40] text-white border-[#001e40] shadow-xs' : 'bg-white border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40]' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor
                                    @endif
                                @endif

                                {{-- Next Page Button --}}
                                <button wire:click="nextPage" class="bg-white border border-[#c3c6d1] text-[#43474f] hover:border-[#001e40] hover:text-[#001e40] p-1.5 rounded-lg disabled:opacity-40 disabled:pointer-events-none transition-all active:scale-95 flex items-center justify-center" @if(!$this->appLineItems->hasMorePages()) disabled @endif>
                                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Right Column: Sidebar (Selected Item details & History) -->
    <div class="lg:col-span-1 sticky top-[320px] space-y-4 my-4">
        @if($this->selectedAppLine)
            <div class="bg-white border border-[#eeedf2] rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center gap-2 border-b border-[#eeedf2] pb-3">
                    <div class="w-8 h-8 rounded-lg bg-[#001e40]/10 flex items-center justify-center text-[#001e40]">
                        <span class="material-symbols-outlined text-[18px]">info</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider">Selected Item Details</h4>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div>
                        <span class="text-[9px] uppercase font-bold tracking-wider text-[#43474f]/60">Description</span>
                        <p class="text-xs font-bold text-[#001e40] leading-snug">{{ $this->selectedAppLine->description }}</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <span class="text-[9px] uppercase font-bold tracking-wider text-[#43474f]/60">Procurement Mode</span>
                            <p class="text-xs font-bold text-[#001e40]">{{ $this->selectedAppLine->procurement_mode }}</p>
                        </div>
                        <div>
                            <span class="text-[9px] uppercase font-bold tracking-wider text-[#43474f]/60">Remaining Budget</span>
                            <p class="text-xs font-bold text-green-700">₱{{ number_format($this->selectedAppLine->approved_budget - $this->selectedAppLine->utilized_budget, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Section -->
            <div class="bg-white border border-[#eeedf2] rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center gap-2 border-b border-[#eeedf2] pb-3">
                    <div class="w-8 h-8 rounded-lg bg-[#fffbe6] flex items-center justify-center text-[#d48806]">
                        <span class="material-symbols-outlined text-[18px]">history</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider">Item Activity Log</h4>
                    </div>
                </div>

                @if($this->recentItemHistory->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($this->recentItemHistory as $trackingNumber => $group)
                            @php
                                $first = $group->first();
                                $prDisplay = $first->pr_number ? "{$first->pr_number} ({$trackingNumber})" : $trackingNumber;
                            @endphp
                            <div x-data="{ expanded: false }" class="bg-[#f9f9fe] border border-[#eeedf2] rounded-xl text-xs shadow-2xs overflow-hidden">
                                <!-- Group Header: Tracking & Status -->
                                <div x-on:click="expanded = !expanded" class="p-3.5 flex items-start justify-between gap-3 cursor-pointer hover:bg-[#eeedf2]/40 transition-all select-none">
                                    <div class="space-y-1 flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 font-bold text-[#001e40]">
                                            <span class="material-symbols-outlined text-[18px] text-[#001e40]/60 transition-transform duration-200 flex-shrink-0" :class="expanded ? 'rotate-180' : ''">expand_more</span>
                                            <span class="truncate">{{ $prDisplay }}</span>
                                        </div>
                                        <div class="text-[10px] text-[#43474f] leading-snug pl-6 mt-1">
                                            <span class="italic text-[#43474f]/80 block break-words">
                                                <strong class="text-[#43474f]/60 font-bold uppercase text-[8px] tracking-wider">Purpose:</strong> 
                                                {{ $first->overall_purpose ?: 'No purpose specified' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <a href="{{ route('procurement.pr.pdf', $first->folder_id) }}" target="_blank" x-on:click.stop class="p-1 hover:bg-[#eeedf2] text-[#43474f] hover:text-[#001e40] rounded-lg transition-all flex items-center justify-center" title="View PR PDF">
                                            <span class="material-symbols-outlined text-[16px] text-red-600">picture_as_pdf</span>
                                        </a>
                                        @php
                                            $statusColors = [
                                                'DRAFT' => 'bg-[#eeedf2] text-[#43474f]',
                                                'SUBMITTED_TO_GSU' => 'bg-[#e0f7fa] text-[#006064] border border-[#00acc1]/20',
                                                'ROUTING' => 'bg-[#fff9c4] text-[#f57f17] border border-[#fbc02d]/30',
                                                'APPROVED' => 'bg-green-50 text-green-800 border border-green-200',
                                                'PR_PRINTED' => 'bg-[#ffdbca] text-[#341100]',
                                                'RFQ_SENT' => 'bg-[#d8e1ea] text-[#5b646b]',
                                                'AWARDED' => 'bg-green-100 text-green-800',
                                                'PO_RELEASED' => 'bg-[#d5e3ff] text-[#001b3c]',
                                                'CANCELLED' => 'bg-red-50 text-red-700 border border-red-200',
                                                'CANCELLED_BY_USER' => 'bg-red-50 text-red-700 border border-red-200',
                                                'RETURNED_FOR_EDIT' => 'bg-amber-50 text-amber-800 border border-amber-200',
                                                'RETURNED_FOR_COMPLIANCE' => 'bg-purple-50 text-purple-800 border border-purple-200',
                                            ];
                                            $color = $statusColors[$first->status] ?? 'bg-blue-50 text-blue-700 border border-blue-200';
                                        @endphp
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $color }}">
                                            {{ $first->status_label }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Group Body (Collapsible) -->
                                <div x-show="expanded" x-cloak class="px-3.5 pb-3.5 space-y-3 pt-1 border-t border-[#eeedf2]/40" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                                    <!-- Group Body: List of Items in the PR -->
                                    <div class="space-y-2">
                                        @foreach($group as $item)
                                            <div class="bg-white p-2.5 rounded-lg border border-[#eeedf2]/60 space-y-1">
                                                <div class="font-medium text-[#001e40] leading-snug">{{ $item->item_desc }}</div>
                                                <div class="flex justify-between items-center text-[10px] text-[#43474f]/60 pt-0.5">
                                                    <div>
                                                        Price: <span class="font-semibold text-[#001e40]">₱{{ number_format($item->unit_price, 2) }}</span>
                                                    </div>
                                                    <div>
                                                        Qty: <span class="font-bold text-[#001e40]">{{ $item->quantity }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6 text-xs text-[#43474f]/50 italic">
                        No recent PR activity for this item from your office.
                    </div>
                @endif
            </div>
        @else
            <!-- Unselected sidebar placeholder -->
            <div class="bg-[#f9f9fe] border border-dashed border-[#c3c6d1] rounded-2xl p-8 text-center text-[#43474f]/50">
                <span class="material-symbols-outlined text-[36px] mb-2 block">ads_click</span>
                <p class="text-xs font-bold">Select an APP Catalog item to inspect details and recent office procurement history.</p>
            </div>
        @endif
    </div>
</div>
