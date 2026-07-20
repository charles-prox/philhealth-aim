<?php

use App\Models\CobVersion;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public CobVersion $version;
    
    // Item Form State
    public bool $showItemModal = false;
    public ?string $editingItemId = null;
    
    public string $ppa_code = '';
    public string $ppa_desc = '';
    public string $sub_ppa_desc = '';
    public string $full_particulars = '';
    public float $recom_amount = 0;
    public float $recom_qty = 1;
    public string $unit = 'Lot';
    public string $exp_desc = '';
    public string $base = '';
    public string $revision_remarks = '';

    public string $search = '';
    public array $filterCategory = [];
    public array $filterPpaDesc = [];
    public array $filterSubPpaDesc = [];

    public string $sortField = 'account';
    public string $sortDirection = 'asc';

    public function mount(CobVersion $version): void
    {
        $this->version = $version->load('budgetYear');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPpaDesc(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSubPpaDesc(): void
    {
        $this->resetPage();
    }

    public function openItemModal(?string $itemId = null): void
    {
        $this->reset(['ppa_code', 'ppa_desc', 'sub_ppa_desc', 'full_particulars', 'recom_amount', 'recom_qty', 'unit', 'exp_desc', 'editingItemId', 'revision_remarks']);
        
        if ($itemId) {
            $item = \App\Models\CobItem::findOrFail($itemId);
            $this->editingItemId = $itemId;
            $this->ppa_code = $item->ppa_code;
            $this->ppa_desc = $item->ppa_desc;
            $this->sub_ppa_desc = $item->sub_ppa_desc ?? '';
            $this->full_particulars = $item->full_particulars ?? '';
            $this->recom_amount = (float) $item->recom_amount;
            $this->recom_qty = (float) $item->recom_qty;
            $this->unit = $item->unit ?? 'Lot';
            $this->exp_desc = $item->exp_desc ?? '';
            $this->base = $item->base ?? '';
            $this->revision_remarks = $item->revision_remarks ?? '';
        }

        $this->showItemModal = true;
    }

    public function saveItem(): void
    {
        $this->validate([
            'ppa_code' => 'required|string|max:100',
            'ppa_desc' => 'required|string',
            'recom_amount' => 'required|numeric|min:0',
            'recom_qty' => 'required|numeric|min:0',
        ]);

        $data = [
            'version_id' => $this->version->id,
            'ppa_code' => $this->ppa_code,
            'ppa_desc' => $this->ppa_desc,
            'sub_ppa_desc' => $this->sub_ppa_desc,
            'full_particulars' => $this->full_particulars,
            'recom_amount' => $this->recom_amount,
            'recom_qty' => $this->recom_qty,
            'unit' => $this->unit,
            'exp_desc' => $this->exp_desc,
            'base' => $this->base,
            'revision_remarks' => $this->revision_remarks,
            'current_balance' => $this->recom_amount, // Initialize balance
        ];

        $isEdit = (bool) $this->editingItemId;
        
        if ($this->editingItemId) {
            \App\Models\CobItem::find($this->editingItemId)->update($data);
        } else {
            \App\Models\CobItem::create($data);
        }

        $this->showItemModal = false;
        $this->reset(['ppa_code', 'ppa_desc', 'full_particulars', 'recom_amount', 'recom_qty', 'unit', 'exp_desc', 'editingItemId', 'revision_remarks']);
        
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => $isEdit ? 'Item Updated' : 'Item Created',
            'message' => 'The budget line has been saved successfully.'
        ]);
    }

    public function deleteItem(string $itemId): void
    {
        \App\Models\CobItem::findOrFail($itemId)->delete();
        
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Item Deleted',
            'message' => 'The budget line has been removed.'
        ]);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterCategory', 'filterPpaDesc', 'filterSubPpaDesc']);
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
        $query = $this->version->cobItems();

        if ($this->search) {
            $query->where(function($q) {
                $q->where('full_particulars', 'ilike', '%' . $this->search . '%')
                  ->orWhere('ppa_desc', 'ilike', '%' . $this->search . '%')
                  ->orWhere('sub_ppa_desc', 'ilike', '%' . $this->search . '%')
                  ->orWhere('ppa_code', 'ilike', '%' . $this->search . '%');
            });
        }

        if ($this->filterCategory && count($this->filterCategory) > 0) {
            $query->whereIn('exp_desc', $this->filterCategory);
        }

        if ($this->filterPpaDesc && count($this->filterPpaDesc) > 0) {
            $query->whereIn('ppa_desc', $this->filterPpaDesc);
        }

        if ($this->filterSubPpaDesc && count($this->filterSubPpaDesc) > 0) {
            $query->whereIn('sub_ppa_desc', $this->filterSubPpaDesc);
        }

        $itemsQuery = $query->orderBy($this->sortField, $this->sortDirection);
        
        // Secondary sort to keep groups together if not sorting by account
        if ($this->sortField !== 'account') {
            $itemsQuery->orderBy('account', 'asc');
        }

        return [
            'items' => $itemsQuery->paginate(50),
            'categories' => \App\Models\CobItem::where('version_id', $this->version->id)
                ->whereNotNull('exp_desc')
                ->distinct()
                ->pluck('exp_desc')
                ->sort()
                ->toArray(),
            'ppa_descriptions' => \App\Models\CobItem::where('version_id', $this->version->id)
                ->whereNotNull('ppa_desc')
                ->distinct()
                ->pluck('ppa_desc')
                ->sort()
                ->toArray(),
            'sub_ppa_descriptions' => \App\Models\CobItem::where('version_id', $this->version->id)
                ->whereNotNull('sub_ppa_desc')
                ->when(count($this->filterPpaDesc) > 0, fn($q) => $q->whereIn('ppa_desc', $this->filterPpaDesc))
                ->distinct()
                ->pluck('sub_ppa_desc')
                ->sort()
                ->toArray(),
        ];
    }
}; ?>

<div class="p-container-padding bg-background flex flex-col gap-6">

    {{-- Back + Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('cob.registry') }}" wire:navigate
           class="p-2 border border-[#c3c6d1] rounded-lg hover:bg-white hover:border-[#001e40] text-[#43474f] hover:text-[#001e40] transition-all">
            <span class="material-symbols-outlined text-[22px]">arrow_back</span>
        </a>
        <div>
            <h2 class="text-xl font-bold text-[#001e40]">{{ $version->version_name }} — FY {{ $version->budgetYear->fiscal_year }}</h2>
            <p class="text-[12px] text-[#43474f] font-bold uppercase tracking-wider">
                {{ number_format($version->item_count) }} Budget Lines · ₱{{ number_format($version->total_allocation / 1000000, 2) }}M Total Allocation
            </p>
        </div>
        @php $badge = $version->statusBadge(); @endphp
        <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase {{ $badge['classes'] }}">{{ $badge['label'] }}</span>

        @if($version->status === 'DRAFT')
        <div class="ml-auto">
            <x-primary-button icon="add" wire:click="openItemModal()">Add Budget Line</x-primary-button>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-5 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[300px]">
            <x-form-input 
                label="Search Budget Lines" 
                icon="search" 
                placeholder="Search by description, particulars, or PPA code..."
                wire:model="search"
                x-on:keydown.enter="$wire.applyFilters()" />
        </div>
        <div class="w-64">
            <x-form-select 
                label="Filter Category" 
                icon="category"
                wire:model="filterCategory"
                :options="array_combine($categories, $categories)"
                placeholder="All Categories"
                multiple
                searchable />
        </div>
        <div class="w-64">
            <x-form-select 
                label="Filter PPA" 
                icon="description"
                wire:model.live="filterPpaDesc"
                :options="array_combine($ppa_descriptions, $ppa_descriptions)"
                placeholder="All PPA"
                multiple
                searchable />
        </div>
        <div class="w-64">
            <x-form-select 
                label="Filter Sub PPA" 
                icon="subdirectory_arrow_right"
                wire:model="filterSubPpaDesc"
                :options="array_combine($sub_ppa_descriptions, $sub_ppa_descriptions)"
                placeholder="All Sub PPA"
                multiple
                searchable />
        </div>
        <div class="pb-1.5 flex items-center gap-2">
            <x-primary-button icon="search" wire:click="applyFilters" class="!py-2.5">Search</x-primary-button>
            
            @if($search || count($filterCategory) > 0 || count($filterPpaDesc) > 0 || count($filterSubPpaDesc) > 0)
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
        {{-- Loading Overlay (Positioned below header) --}}
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
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('exp_desc')">
                            <div class="flex items-center gap-1">
                                Category
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'exp_desc' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'exp_desc' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider w-[250px] min-w-[200px] cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('ppa_desc')">
                            <div class="flex items-center gap-1">
                                PPA Description
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'ppa_desc' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'ppa_desc' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('full_particulars')">
                            <div class="flex items-center gap-1">
                                Item Description
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'full_particulars' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'full_particulars' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center w-[120px] cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('recom_qty')">
                            <div class="flex items-center justify-center gap-1">
                                Qty / Unit
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'recom_qty' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'recom_qty' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right cursor-pointer hover:bg-[#d5e3ff] transition-colors group" wire:click="sortBy('recom_amount')">
                            <div class="flex items-center justify-end gap-1">
                                Recommended Amount
                                <span class="material-symbols-outlined text-[16px] {{ $sortField === 'recom_amount' ? 'text-[#001e40]' : 'text-[#c3c6d1] opacity-0 group-hover:opacity-100' }}">
                                    {{ $sortField === 'recom_amount' ? ($sortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'sort' }}
                                </span>
                            </div>
                        </th>
                        @if($version->status === 'DRAFT')
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                    @php
                        use App\Models\BudgetTransaction;
                        $txns = BudgetTransaction::where('version_id', $version->id)->get();
                        $sourceIds = $txns->pluck('source_item_id')->filter()->unique()->flip()->toArray();
                        $targetIds = $txns->pluck('target_item_id')->filter()->unique()->flip()->toArray();
                    @endphp
                    @forelse($items as $item)
                    @php
                        $isSource = isset($sourceIds[$item->id]);
                        $isTarget = isset($targetIds[$item->id]);
                    @endphp
                    <tr class="transition-colors {{ $isTarget ? 'bg-blue-50 hover:bg-blue-100/60' : ($isSource ? 'bg-orange-50 hover:bg-orange-100/60' : 'hover:bg-[#f4f3f8]') }}">
                        <td class="p-table-cell-padding">
                            <span class="font-bold text-[#001e40] uppercase">{{ $item->exp_desc ?: 'N/A' }}</span>
                        </td>
                        <td class="p-table-cell-padding py-4">
                            <div class="flex flex-col gap-1">
                                <span class="font-bold text-[#001e40] leading-tight">{{ $item->ppa_desc }}</span>
                                @if($item->sub_ppa_desc)
                                    <span class="text-[12px] text-[#43474f] leading-snug">{{ $item->sub_ppa_desc }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="p-table-cell-padding">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-[#1a1c1f] leading-tight">{{ $item->full_particulars ?: '—' }}</span>
                                @if($item->version_number > 1)
                                    <span class="px-1.5 py-0.5 bg-[#e3e2e6] text-[#43474f] text-[9px] font-bold rounded shadow-sm">v{{ $item->version_number }}</span>
                                @endif
                                @if($isTarget)
                                    <span class="px-1.5 py-0.5 bg-blue-100 text-blue-800 text-[9px] font-bold rounded uppercase tracking-wide">▲ Target</span>
                                @elseif($isSource)
                                    <span class="px-1.5 py-0.5 bg-orange-100 text-orange-800 text-[9px] font-bold rounded uppercase tracking-wide">▼ Source</span>
                                @endif
                            </div>
                        </td>
                        <td class="p-table-cell-padding text-center text-[#43474f]">
                            <span class="font-bold text-[#001e40]">{{ $item->recom_qty ? number_format($item->recom_qty, 0) : '—' }}</span>
                            <span class="text-[11px] text-[#43474f] ml-1">{{ $item->unit ?? '' }}</span>
                        </td>
                        <td class="p-table-cell-padding text-right font-bold text-[#001e40]">
                            ₱{{ number_format($item->recom_amount, 2) }}
                        </td>
                        @if($version->status === 'DRAFT')
                        <td class="p-table-cell-padding">
                            <div class="flex items-center justify-center gap-2">
                                <x-icon-button wire:click="openItemModal('{{ $item->id }}')" icon="edit" title="Edit Item" />
                                <x-icon-button icon="delete" variant="error" title="Delete Item"
                                        @click="$dispatch('confirm', {
                                             title: 'Remove Budget Line?',
                                             message: 'Are you sure you want to remove this budget item? This cannot be undone.',
                                             type: 'danger',
                                             onConfirm: () => $wire.deleteItem('{{ $item->id }}')
                                         })" />
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $version->status === 'DRAFT' ? 6 : 5 }}" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">upload_file</span>
                                <p class="font-bold text-[#001e40] text-lg">No Budget Lines Loaded</p>
                                <p class="text-[13px] text-[#43474f] max-w-xs">Upload the WFP Excel file on the Annual Kick-off page to populate this version's budget lines.</p>
                                <a href="{{ route('cob.kickoff') }}" wire:navigate>
                                    <x-primary-button icon="arrow_back" class="mt-2">Back to Kick-off</x-primary-button>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('livewire.cob.partials.version-items-modal')
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
            .cob-pagination nav div:first-child { display: none; } /* Hide the 'Showing X to Y' text inside links() as we have our own */
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
