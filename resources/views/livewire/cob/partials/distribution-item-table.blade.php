{{-- ================================================================
     LEFT PANEL — COB Item List (toolbar + table + pagination)
================================================================ --}}
<div class="flex-1 flex flex-col bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden min-w-0 relative">

    {{-- Loading Overlay --}}
    <div wire:loading wire:target="search, filterCategory, perPage, applyFilters, resetFilters, gotoPage, nextPage, previousPage" class="absolute inset-0 bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
        <div class="flex flex-col items-center gap-2">
            <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
            <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="px-5 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
            <input type="text" wire:model="search"
                   placeholder="Search particulars..."
                   x-on:keydown.enter="$wire.applyFilters()"
                   class="w-full pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all placeholder-[#43474f]/40"/>
        </div>

        {{-- Category filter --}}
        <div class="w-80">
            <x-form-select 
                label="" 
                icon="category"
                wire:model="filterCategory"
                :options="array_combine($this->categories, $this->categories)"
                placeholder="All Categories"
                searchable />
        </div>

        {{-- Search / Reset Buttons --}}
        <button wire:click="applyFilters"
                class="px-4 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">search</span>Search
        </button>

        @if($search || $filterCategory)
            <button wire:click="resetFilters"
                    class="text-[12px] font-bold text-red-600 hover:bg-red-50 px-3 py-2.5 rounded-lg transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>Clear
            </button>
        @endif
    </div>

    {{-- COB Item Table --}}
    <div class="overflow-y-auto flex-1 custom-scrollbar">
        <table class="w-full text-left border-collapse">
            <thead class="sticky top-0 z-10">
                <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                    <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Category</th>
                    <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Particulars</th>
                    <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center w-16">Unit</th>
                    <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right w-36">
                        Est. Unit Cost<br>
                        <span class="text-[9px] font-normal lowercase text-[#43474f]">(recom. amount/qty)</span>
                    </th>
                    <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right w-32">Est. Total Cost</th>
                    <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right w-24">Allocated</th>
                    <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right w-24">Remaining</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                @forelse($cobItems as $item)
                    @php
                        $recomQty     = (int) $item->recom_qty;
                        $allocated    = (int) ($item->allocated_qty_sum ?? 0);
                        $remaining    = max(0, $recomQty - $allocated);
                        $isSelected   = $selectedCobItemId === $item->id;
                        $isFullyAlloc = $remaining === 0 && $recomQty > 0;

                        // Est. Unit Cost calculation (recom_amount / recom_qty)
                        $unitCost = $recomQty > 0 ? ((float) $item->recom_amount / $recomQty) : (float) ($item->unit_cost ?? 0);
                        // Est. Total Cost = Est. Unit Cost * allocated
                        $totalCost = $unitCost * $allocated;
                    @endphp
                    <tr wire:click="selectItem('{{ $item->id }}')"
                        class="cursor-pointer transition-colors {{ $isSelected ? 'bg-[#d5e3ff]' : 'hover:bg-[#f4f3f8]' }}">
                        <td class="p-table-cell-padding text-[#43474f] font-medium">{{ $item->exp_desc ?? '—' }}</td>
                        <td class="p-table-cell-padding">
                            <div class="flex flex-col">
                                <span class="font-semibold text-[#001e40] leading-snug line-clamp-2">{{ $item->full_particulars ?? $item->exp_desc ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="p-table-cell-padding text-center text-[#43474f]">{{ $item->unit ?? '—' }}</td>
                        <td class="p-table-cell-padding text-right font-bold text-[#1a1c1f]">
                            {{ $unitCost > 0 ? '₱' . number_format($unitCost, 2) : '—' }}
                        </td>
                        <td class="p-table-cell-padding text-right font-bold text-[#001e40]">
                            {{ $totalCost > 0 ? '₱' . number_format($totalCost, 2) : '—' }}
                        </td>
                        <td class="p-table-cell-padding text-right text-[#1a1c1f]">{{ number_format($allocated) }}</td>
                        <td class="p-table-cell-padding text-right">
                            <span class="font-bold {{ $isFullyAlloc ? 'text-green-700' : ($remaining < $recomQty * 0.2 && $remaining > 0 ? 'text-amber-600' : 'text-[#001e40]') }}">
                                {{ number_format($remaining) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">inventory_2</span>
                                <p class="font-bold text-[#001e40]">No COB Items Found</p>
                                <p class="text-[13px] text-[#43474f]">Try adjusting your search or filters.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($cobItems->total() > 0)
        <div class="px-5 py-3 border-t border-[#c3c6d1] bg-[#f9f9fe] flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2.5 text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                    <span>Show</span>
                    <div class="w-24">
                        <x-form-select 
                            label="" 
                            wire:model.live="perPage"
                            :options="[10 => '10', 15 => '15', 25 => '25', 50 => '50', 100 => '100']"
                            placeholder="15"
                            placement="top" />
                    </div>
                </div>
                <div class="h-4 w-[1px] bg-[#c3c6d1]"></div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                    Showing {{ $cobItems->firstItem() }}–{{ $cobItems->lastItem() }} of {{ number_format($cobItems->total()) }} items
                </p>
            </div>
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
