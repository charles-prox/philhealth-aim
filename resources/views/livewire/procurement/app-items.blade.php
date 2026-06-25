<?php

use App\Models\AppHeader;
use App\Models\AppLineItem;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public AppHeader $header;

    public string $search = '';
    public array $filterUnit = [];
    public array $filterMode = [];
    public array $filterFund = [];

    public string $sortField = 'project_title';
    public string $sortDirection = 'asc';

    public function mount(AppHeader $header): void
    {
        $this->header = $header->load('uploadedBy');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUnit(): void
    {
        $this->resetPage();
    }

    public function updatingFilterMode(): void
    {
        $this->resetPage();
    }

    public function updatingFilterFund(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterUnit', 'filterMode', 'filterFund']);
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function with(): array
    {
        $query = $this->header->lineItems();

        if ($this->search) {
            $query->where(function($q) {
                $q->where('project_title', 'ilike', '%' . $this->search . '%')
                  ->orWhere('description', 'ilike', '%' . $this->search . '%')
                  ->orWhere('implementing_unit', 'ilike', '%' . $this->search . '%')
                  ->orWhere('procurement_mode', 'ilike', '%' . $this->search . '%');
            });
        }

        if ($this->filterUnit && count($this->filterUnit) > 0) {
            $query->whereIn('implementing_unit', $this->filterUnit);
        }

        if ($this->filterMode && count($this->filterMode) > 0) {
            $query->whereIn('procurement_mode', $this->filterMode);
        }

        if ($this->filterFund && count($this->filterFund) > 0) {
            $query->whereIn('source_of_fund', $this->filterFund);
        }

        $itemsQuery = $query->orderBy($this->sortField, $this->sortDirection);

        return [
            'items' => $itemsQuery->paginate(50),
            'units' => AppLineItem::where('app_header_id', $this->header->id)
                ->whereNotNull('implementing_unit')
                ->where('implementing_unit', '!=', '')
                ->distinct()
                ->pluck('implementing_unit')
                ->sort()
                ->toArray(),
            'modes' => AppLineItem::where('app_header_id', $this->header->id)
                ->whereNotNull('procurement_mode')
                ->where('procurement_mode', '!=', '')
                ->distinct()
                ->pluck('procurement_mode')
                ->sort()
                ->toArray(),
            'funds' => AppLineItem::where('app_header_id', $this->header->id)
                ->whereNotNull('source_of_fund')
                ->where('source_of_fund', '!=', '')
                ->distinct()
                ->pluck('source_of_fund')
                ->sort()
                ->toArray(),
            'totalBudget' => AppLineItem::where('app_header_id', $this->header->id)->sum('approved_budget'),
            'totalUtilized' => AppLineItem::where('app_header_id', $this->header->id)->sum('utilized_budget'),
        ];
    }
}; ?>

<div class="p-container-padding bg-background space-y-6">

    {{-- Back + Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('cob.registry') }}" wire:navigate
           class="p-2 border border-[#c3c6d1] rounded-lg hover:bg-white hover:border-[#001e40] text-[#43474f] hover:text-[#001e40] transition-all">
            <span class="material-symbols-outlined text-[22px]">arrow_back</span>
        </a>
        <div>
            <h2 class="text-xl font-bold text-[#001e40]">Annual Procurement Plan (APP) — FY {{ $header->fiscal_year }}</h2>
            <p class="text-[12px] text-[#43474f] font-bold uppercase tracking-wider">
                {{ number_format($header->lineItems()->count()) }} Layout Lines · ₱{{ number_format($totalBudget, 2) }} Approved Budget · ₱{{ number_format($totalUtilized, 2) }} Utilized Budget
            </p>
        </div>
        @if($header->is_approved)
            <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase bg-green-50 text-green-700 border border-green-200 shadow-3xs">Live & Active</span>
        @else
            <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase bg-[#fff8e1] text-[#f57f17] border border-[#ffe082] shadow-3xs">Unapproved Draft</span>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-5 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[300px]">
            <x-form-input 
                label="Search APP Lines" 
                icon="search" 
                placeholder="Search by project title, description, mode..."
                wire:model="search"
                x-on:keydown.enter="$wire.applyFilters()" />
        </div>
        <div class="w-64">
            <x-form-select 
                label="Filter Implementing Unit" 
                icon="business"
                wire:model="filterUnit"
                :options="array_combine($units, $units)"
                placeholder="All Units"
                multiple
                searchable />
        </div>
        <div class="w-64">
            <x-form-select 
                label="Filter Procurement Mode" 
                icon="shopping_basket"
                wire:model="filterMode"
                :options="array_combine($modes, $modes)"
                placeholder="All Modes"
                multiple
                searchable />
        </div>
        <div class="w-64">
            <x-form-select 
                label="Filter Source of Fund" 
                icon="payments"
                wire:model="filterFund"
                :options="array_combine($funds, $funds)"
                placeholder="All Funds"
                multiple
                searchable />
        </div>
        <div class="pb-1.5 flex items-center gap-2">
            <x-primary-button icon="search" wire:click="applyFilters" class="!py-2.5">Search</x-primary-button>
            
            @if($search || count($filterUnit) > 0 || count($filterMode) > 0 || count($filterFund) > 0)
                <button wire:click="resetFilters" 
                        class="text-[12px] font-bold text-red-600 hover:bg-red-50 px-3 py-2.5 rounded-lg transition-all flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Items Table --}}
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden relative">
        {{-- Loading Overlay --}}
        <div wire:loading class="absolute inset-x-0 bottom-0 top-[45px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
            <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('project_title')">
                            <div class="flex items-center gap-1">
                                Project Title
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'project_title' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'project_title' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('implementing_unit')">
                            <div class="flex items-center gap-1">
                                Implementing Unit
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'implementing_unit' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'implementing_unit' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Description</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('procurement_mode')">
                            <div class="flex items-center gap-1">
                                Mode of Procurement
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'procurement_mode' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'procurement_mode' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center">EPA?</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Source of Fund</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('approved_budget')">
                            <div class="flex items-center justify-end gap-1">
                                Approved Budget
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'approved_budget' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'approved_budget' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('utilized_budget')">
                            <div class="flex items-center justify-end gap-1">
                                Utilized Budget
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'utilized_budget' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'utilized_budget' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Remaining Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                    @forelse($items as $item)
                    @php
                        $remaining = (float)$item->approved_budget - (float)$item->utilized_budget;
                    @endphp
                    <tr class="transition-colors hover:bg-[#f4f3f8]">
                        <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $item->project_title }}</td>
                        <td class="p-table-cell-padding font-medium text-[#43474f]">{{ $item->implementing_unit }}</td>
                        <td class="p-table-cell-padding max-w-[250px] truncate" title="{{ $item->description }}">{{ $item->description }}</td>
                        <td class="p-table-cell-padding text-[#43474f]">{{ $item->procurement_mode }}</td>
                        <td class="p-table-cell-padding text-center">
                            @if($item->is_epa)
                                <span class="px-2 py-0.5 bg-green-50 text-green-700 border border-green-200 text-[10px] font-bold rounded">EPA</span>
                            @else
                                <span class="text-[#c3c6d1]">—</span>
                            @endif
                        </td>
                        <td class="p-table-cell-padding text-[#43474f]">{{ $item->source_of_fund }}</td>
                        <td class="p-table-cell-padding text-right font-bold text-[#001e40]">₱{{ number_format($item->approved_budget, 2) }}</td>
                        <td class="p-table-cell-padding text-right font-bold text-blue-700">₱{{ number_format($item->utilized_budget, 2) }}</td>
                        <td class="p-table-cell-padding text-right font-bold {{ $remaining > 0 ? 'text-green-700' : 'text-red-700' }}">₱{{ number_format($remaining, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">list</span>
                                <p class="font-bold text-[#001e40] text-lg">No APP Lines Found</p>
                                <p class="text-[13px] text-[#43474f] max-w-xs">No lines match the search criteria or filters for this APP.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
        <div class="p-gutter border-t border-[#c3c6d1] bg-[#f9f9fe] flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ number_format($items->total()) }} items
            </p>
            <div class="cob-pagination">
                {{ $items->links() }}
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
</div>
