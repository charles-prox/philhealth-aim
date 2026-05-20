<?php

use App\Models\CobItem;
use App\Models\CobItemDistribution;
use App\Models\CobVersion;
use App\Models\Employee;
use App\Models\Office;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Illuminate\Validation\ValidationException;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // -------------------------------------------------------------------------
    // Filter & Search State
    // -------------------------------------------------------------------------
    public string $search         = '';
    public string $filterCategory = '';   // e.g. MOOE, CO, PS
    public int    $perPage        = 15;

    // -------------------------------------------------------------------------
    // Allocation Pane State
    // -------------------------------------------------------------------------
    public ?string $selectedCobItemId = null;

    // Add-allocation form fields
    public array   $newAllocation  = [
        'office_id'       => '',
        'employee_id'     => '',
        'sub_employee_id' => '',
        'quantity'        => '1'
    ];

    public bool    $showPane       = false;

    // -------------------------------------------------------------------------
    // Lifecycle & Filter Triggers
    // -------------------------------------------------------------------------
    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterCategory']);
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updated($name): void
    {
        if ($name === 'newAllocation.office_id') {
            $this->newAllocation['employee_id'] = '';
            $this->newAllocation['sub_employee_id'] = '';
        }
    }


    // -------------------------------------------------------------------------
    // Computed: Active COB version
    // -------------------------------------------------------------------------
    #[Computed]
    public function activeVersion(): ?CobVersion
    {
        return CobVersion::where('is_active', true)->with('budgetYear')->first();
    }

    // -------------------------------------------------------------------------
    // Computed: Selected COB item with its allocation summary
    // -------------------------------------------------------------------------
    #[Computed]
    public function selectedCobItem(): ?CobItem
    {
        if (!$this->selectedCobItemId) return null;
        return CobItem::with(['distributions.office', 'distributions.employee', 'distributions.subEmployee'])->find($this->selectedCobItemId);
    }

    #[Computed]
    public function selectedItemTotalAllocated(): int
    {
        return $this->selectedCobItem
            ? (int) $this->selectedCobItem->distributions()->whereNull('deleted_at')->sum('allocated_quantity')
            : 0;
    }

    #[Computed]
    public function selectedItemRemainingToAllocate(): int
    {
        $item = $this->selectedCobItem;
        if (!$item) return 0;
        return max(0, (int) $item->recom_qty - $this->selectedItemTotalAllocated);
    }

    // -------------------------------------------------------------------------
    // Computed: Categories dropdown
    // -------------------------------------------------------------------------
    #[Computed]
    public function categories(): array
    {
        $version = $this->activeVersion;
        if (!$version) return [];
        return CobItem::where('is_active', true)
            ->where('version_id', $version->id)
            ->whereNotNull('exp_desc')
            ->distinct()
            ->pluck('exp_desc')
            ->sort()
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Computed: KPI Stats for header
    // -------------------------------------------------------------------------
    #[Computed]
    public function totalCobBudget(): float
    {
        $version = $this->activeVersion;
        if (!$version) return 0.0;
        return (float) CobItem::where('is_active', true)->where('version_id', $version->id)->sum('recom_amount');
    }

    #[Computed]
    public function totalAllocatedUnits(): int
    {
        $version = $this->activeVersion;
        if (!$version) return 0;
        return (int) CobItemDistribution::whereNull('deleted_at')
            ->whereHas('cobItem', fn($q) => $q->where('is_active', true)->where('version_id', $version->id))
            ->sum('allocated_quantity');
    }

    // -------------------------------------------------------------------------
    // Computed: Office dropdown
    // -------------------------------------------------------------------------
    #[Computed]
    public function offices(): array
    {
        return Office::orderBy('name')->pluck('name', 'id')->toArray();
    }

    // -------------------------------------------------------------------------
    // Computed: Regular Employees dropdown (Accountable Officers)
    // -------------------------------------------------------------------------
    #[Computed]
    public function regularEmployees(): array
    {
        $officeId = $this->newAllocation['office_id'] ?? '';
        if (!$officeId) return [];
        $office = Office::find($officeId);
        if (!$office) return [];

        return Employee::where('office_division', $office->name)
            ->where('employment_status', 'PERMANENT')
            ->orderBy('fullname')
            ->pluck('fullname', 'id')
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Computed: Sub-Employees dropdown (Casual/JO actual users)
    // -------------------------------------------------------------------------
    #[Computed]
    public function subEmployees(): array
    {
        $officeId = $this->newAllocation['office_id'] ?? '';
        if (!$officeId) return [];
        $office = Office::find($officeId);
        if (!$office) return [];

        return Employee::where('office_division', $office->name)
            ->whereIn('employment_status', ['CASUAL', 'JO'])
            ->orderBy('fullname')
            ->pluck('fullname', 'id')
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Actions: Open / close pane
    // -------------------------------------------------------------------------
    public function selectItem(string $cobItemId): void
    {
        $this->selectedCobItemId = $cobItemId;
        $this->showPane          = true;
        $this->resetAllocationForm();
    }

    public function closePane(): void
    {
        $this->showPane          = false;
        $this->selectedCobItemId = null;
        $this->resetAllocationForm();
    }

    private function resetAllocationForm(): void
    {
        $this->newAllocation = [
            'office_id'       => '',
            'employee_id'     => '',
            'sub_employee_id' => '',
            'quantity'        => '1'
        ];
    }

    // -------------------------------------------------------------------------
    // Actions: Add allocation
    // -------------------------------------------------------------------------
    public function addAllocation(): void
    {
        $this->validate([
            'newAllocation.office_id'       => 'required|exists:offices,id',
            'newAllocation.employee_id'     => 'required|exists:employees,id',
            'newAllocation.sub_employee_id' => 'nullable|exists:employees,id',
            'newAllocation.quantity'        => 'required|integer|min:1',
        ], [
            'newAllocation.office_id.required'   => 'The office field is required.',
            'newAllocation.employee_id.required' => 'The accountable officer field is required.',
            'newAllocation.quantity.required'    => 'The quantity is required.',
            'newAllocation.quantity.integer'     => 'The quantity must be a whole number.',
            'newAllocation.quantity.min'         => 'The quantity must be at least 1.',
        ]);

        $item          = CobItem::findOrFail($this->selectedCobItemId);
        $recomQty      = (int) $item->recom_qty;
        $totalAllocated = (int) CobItemDistribution::where('cob_item_id', $this->selectedCobItemId)
            ->whereNull('deleted_at')
            ->sum('allocated_quantity');

        $qtyToAdd = (int) $this->newAllocation['quantity'];

        if (($totalAllocated + $qtyToAdd) > $recomQty) {
            throw ValidationException::withMessages([
                'newAllocation.quantity' => "Allocation exceeds the available COB line quantity. Maximum you can allocate: " . max(0, $recomQty - $totalAllocated) . '.',
            ]);
        }

        CobItemDistribution::create([
            'cob_item_id'        => $this->selectedCobItemId,
            'office_id'          => $this->newAllocation['office_id'],
            'employee_id'        => $this->newAllocation['employee_id'],
            'sub_employee_id'    => $this->newAllocation['sub_employee_id'] ?: null,
            'allocated_quantity' => $qtyToAdd,
            'procured_quantity'  => 0,
        ]);

        $this->resetAllocationForm();
        $this->dispatch('allocation-added');
    }

    // -------------------------------------------------------------------------
    // Actions: Remove allocation (only unlocked rows)
    // -------------------------------------------------------------------------
    public function removeAllocation(string $distributionId): void
    {
        $dist = CobItemDistribution::findOrFail($distributionId);

        if ($dist->is_locked) {
            $this->addError('general', 'This allocation is locked inside an active PR and cannot be removed.');
            return;
        }

        $dist->delete();
    }

    // -------------------------------------------------------------------------
    // with(): COB items list
    // -------------------------------------------------------------------------
    public function with(): array
    {
        $version = $this->activeVersion;

        $query = CobItem::query()
            ->where('is_active', true)
            ->when($version, fn($q) => $q->where('version_id', $version->id))
            ->withSum('distributions as allocated_qty_sum', 'allocated_quantity')
            ->when($this->search, fn($q) => $q->where('full_particulars', 'like', '%' . $this->search . '%'))
            ->when($this->filterCategory, fn($q) => $q->where('exp_desc', $this->filterCategory))
            ->orderBy('ppa_code');

        return [
            'cobItems'                         => $query->paginate($this->perPage),
            'activeVersion'                    => $version,
            'selectedCobItem'                  => $this->selectedCobItem,
            'selectedItemTotalAllocated'       => $this->selectedItemTotalAllocated,
            'selectedItemRemainingToAllocate'  => $this->selectedItemRemainingToAllocate,
        ];
    }
}; ?>

<div>
    @section('header_title', 'Distribution Matrix')

    <div class="p-container-padding bg-background">

        @if(!$activeVersion)
            {{-- Empty state: no active version --}}
            <div class="bg-white border border-[#c3c6d1] rounded-2xl py-24 flex flex-col items-center gap-4 shadow-sm">
                <span class="material-symbols-outlined text-[64px] text-[#c3c6d1]">account_balance</span>
                <p class="font-bold text-[#001e40] text-xl">No Active COB Version</p>
                <p class="text-[13px] text-[#43474f] max-w-xs text-center">Activate a COB version from the Budget Registry before distributing allocations.</p>
                <a href="{{ route('cob.registry') }}" wire:navigate class="mt-2 inline-flex items-center gap-2 bg-[#001e40] text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-[#1f3f66] transition-colors">
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>Go to COB Registry
                </a>
            </div>
        @else

        {{-- Beautiful Header Summary Card --}}
        <div class="mb-5 bg-white border border-[#c3c6d1] rounded-2xl p-5 shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-[#d5e3ff] text-[#001b3c] flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px] text-[#001b3c]">account_balance</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-extrabold text-[#001e40] tracking-tight">Corporate Operating Budget</h1>
                        <span class="px-2.5 py-0.5 bg-[#d5e3ff] text-[#001b3c] text-[10px] font-extrabold rounded-full uppercase tracking-wider">
                            Active: {{ $activeVersion->version_name }}
                        </span>
                        <span class="px-2.5 py-0.5 bg-[#f1f3f9] text-[#43474f] text-[10px] font-bold rounded-full uppercase tracking-wider">
                            FY{{ $activeVersion->budgetYear->fiscal_year ?? '—' }}
                        </span>
                    </div>
                    <p class="text-[12px] text-[#43474f] mt-1">
                        Map and distribute approved budget line allocations to designated office divisions, regular accountable officers, and floor users.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                {{-- KPI 1: Total Budget --}}
                <div class="bg-[#f1f3f9]/50 border border-[#eeedf2] px-4 py-2 rounded-xl flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#d5e3ff] text-[#001b3c] flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[18px]">payments</span>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider leading-none">Total Budget</p>
                        <p class="text-sm font-extrabold text-[#001e40] mt-1 leading-none">₱{{ number_format($this->totalCobBudget, 2) }}</p>
                    </div>
                </div>

                {{-- KPI 2: Total Allocated --}}
                <div class="bg-[#f1f3f9]/50 border border-[#eeedf2] px-4 py-2 rounded-xl flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-[#d5e3ff] text-[#001b3c] flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider leading-none">Distributed</p>
                        <p class="text-sm font-extrabold text-[#001e40] mt-1 leading-none">{{ number_format($this->totalAllocatedUnits) }} Units</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-5 h-[calc(100vh-215px)]">

            {{-- ================================================================
                 LEFT PANEL — COB Item List
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

            {{-- ================================================================
                 RIGHT PANEL — Allocation Pane (slide-in)
            ================================================================ --}}
            <div class="flex-shrink-0 flex flex-col bg-white rounded-2xl shadow-sm overflow-hidden transition-all duration-300 {{ $showPane ? 'w-[550px] border border-[#c3c6d1] opacity-100 translate-x-0' : 'w-0 border-none opacity-0 pointer-events-none translate-x-4' }}">

                @if($showPane && $selectedCobItem)
                    {{-- Active Allocation Ledger Header --}}
                    <div class="px-5 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe] space-y-4">
                        <div class="flex justify-between items-start gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-[#43474f]">Active Allocation Ledger</p>
                                <h3 class="font-bold text-[#001e40] text-base leading-snug mt-1 line-clamp-2">
                                    {{ $selectedCobItem->full_particulars ?? $selectedCobItem->exp_desc ?? '—' }}
                                </h3>
                                <p class="text-[11px] text-[#43474f] mt-1 font-mono">{{ $selectedCobItem->ppa_code }}</p>
                            </div>
                            <button wire:click="closePane" class="flex-shrink-0 p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] transition-all">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        {{-- Real-Time Allocation Header / Capacity Bar --}}
                        @php
                            $recomQty  = (int) $selectedCobItem->recom_qty;
                            $allocated = $selectedItemTotalAllocated;
                            $remaining = $selectedItemRemainingToAllocate;
                            $pct       = $recomQty > 0 ? min(100, round(($allocated / $recomQty) * 100)) : 0;
                        @endphp
                        <div class="p-3 bg-[#f4f3f8] border border-[#eeedf2] rounded-xl space-y-3">
                            <div class="grid grid-cols-3 gap-2 text-center divide-x divide-[#c3c6d1]/40">
                                <div>
                                    <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider">Total Quantity</p>
                                    <p class="text-sm font-extrabold text-[#001e40] mt-0.5">{{ number_format($recomQty) }} <span class="text-[9px] font-normal text-[#43474f]">{{ $selectedCobItem->unit }}</span></p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider">Allocated</p>
                                    <p class="text-sm font-extrabold text-blue-700 mt-0.5">{{ number_format($allocated) }} <span class="text-[9px] font-normal text-[#43474f]">{{ $selectedCobItem->unit }}</span></p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider">Remaining</p>
                                    <p class="text-sm font-extrabold {{ $remaining > 0 ? 'text-[#001e40]' : 'text-green-700' }} mt-0.5">
                                        {{ number_format($remaining) }} <span class="text-[9px] font-normal text-[#43474f]">{{ $selectedCobItem->unit }}</span>
                                    </p>
                                </div>
                            </div>
                            
                            {{-- Visual Progress Bar --}}
                            <div class="space-y-1">
                                <div class="flex justify-between text-[9px] font-bold text-[#43474f] uppercase tracking-wider px-0.5">
                                    <span>Allocation Progress</span>
                                    <span class="{{ $pct >= 100 ? 'text-green-700' : 'text-[#001e40]' }} font-extrabold">{{ $pct }}%</span>
                                </div>
                                <div class="w-full h-2.5 bg-white border border-[#eeedf2] rounded-full overflow-hidden p-[1px]">
                                    <div class="h-full rounded-full transition-all duration-500 {{ $pct >= 100 ? 'bg-green-500' : 'bg-[#001e40]' }}" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Single-Line Insertion Form --}}
                    <div class="px-5 py-3 border-b border-[#c3c6d1] bg-[#f9f9fe] space-y-2">
                        @error('newAllocation.office_id')
                            <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
                        @enderror
                        @error('newAllocation.employee_id')
                            <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
                        @enderror
                        @error('newAllocation.sub_employee_id')
                            <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
                        @enderror
                        @error('newAllocation.quantity')
                            <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
                        @enderror

                        @if($remaining > 0)
                            <div class="flex flex-col gap-3 p-3 rounded-xl border border-[#eeedf2] bg-[#f1f3f9]/50 shadow-inner" x-data="{
                                focusOffice() {
                                    let btn = $el.querySelector('.office-select-trigger');
                                    if (btn) btn.focus();
                                }
                             }" @allocation-added.window="focusOffice()">
                                
                                {{-- Row 1: Office & Accountable Officer (Regular) --}}
                                <div class="flex items-end gap-2.5">
                                    {{-- Dropdown: Office --}}
                                    <div class="flex-1 min-w-0">
                                        <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Office</label>
                                        <x-form-select 
                                            label="" 
                                            class="office-select-trigger"
                                            wire:model.live="newAllocation.office_id"
                                            :options="$this->offices"
                                            placeholder="Select Office…"
                                            searchable />
                                    </div>

                                    {{-- Dropdown: Accountable Officer (Regular) --}}
                                    <div class="flex-1 min-w-0">
                                        <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Accountable Officer (Regular)</label>
                                        <x-form-select 
                                            label="" 
                                            wire:model="newAllocation.employee_id"
                                            :options="$this->regularEmployees"
                                            placeholder="Select Accountable..."
                                            :disabled="!$newAllocation['office_id']"
                                            searchable />
                                    </div>
                                </div>

                                {{-- Row 2: Sub-User, Qty, & Add Button --}}
                                <div class="flex items-end gap-2.5">
                                    {{-- Dropdown: Sub-End-User (Casual/JO) --}}
                                    <div class="flex-1 min-w-0">
                                        <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Sub-User (JO/Casual - Optional)</label>
                                        <x-form-select 
                                            label="" 
                                            wire:model="newAllocation.sub_employee_id"
                                            :options="$this->subEmployees"
                                            placeholder="Optional (Casual/JO)"
                                            :disabled="!$newAllocation['office_id']"
                                            searchable />
                                    </div>

                                    {{-- Qty Input --}}
                                    <div class="w-16 flex-shrink-0">
                                        <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Qty</label>
                                        <input type="number" 
                                               wire:model="newAllocation.quantity" 
                                               min="1" 
                                               max="{{ $remaining }}"
                                               placeholder="Qty"
                                               x-on:keydown.enter="$wire.addAllocation()"
                                               class="w-full px-3 py-2 bg-white border border-[#c3c6d1] rounded-lg text-sm font-bold focus:ring-2 focus:ring-[#001e40] outline-none text-[#1a1c1f] text-center h-[38px]"/>
                                    </div>

                                    {{-- Action Button --}}
                                    <div class="flex-shrink-0">
                                        <button wire:click="addAllocation"
                                                class="px-4 py-2 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center justify-center gap-1 h-[38px] whitespace-nowrap">
                                            <span class="material-symbols-outlined text-[18px]">add</span>Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-2 flex items-center gap-2.5">
                                <span class="material-symbols-outlined text-green-700 text-[18px]">check_circle</span>
                                <p class="text-[11px] font-bold text-green-800 uppercase tracking-wider">All item quantities have been fully distributed.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Immediate View Grid / Ledger List --}}
                    <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-[#eeedf2]">
                        @forelse($selectedCobItem->distributions()->whereNull('deleted_at')->with(['office','employee','subEmployee'])->get() as $dist)
                            <div class="px-5 py-3 flex items-center gap-3 {{ $dist->is_locked ? 'bg-[#f9f9fe]' : 'hover:bg-[#fcfcff]' }} transition-colors">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-[#1a1c1f] text-sm">{{ $dist->office->name ?? '—' }}</p>
                                        @if($dist->is_locked)
                                            <span class="px-2 py-0.5 bg-[#d5e3ff] text-[#001b3c] text-[9px] font-bold rounded-full uppercase tracking-wider flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[11px]">lock</span>Locked
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-1 mt-1">
                                        @if($dist->employee)
                                            <div class="flex items-center gap-1.5 text-[11px] text-[#43474f]">
                                                <span class="material-symbols-outlined text-[14px] text-[#001e40]/75">assignment_ind</span>
                                                <span class="font-semibold text-[#001b3c]">{{ $dist->employee->fullname }}</span>
                                                <span class="text-[9px] bg-[#d5e3ff] text-[#001b3c] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">Regular</span>
                                            </div>
                                        @else
                                            <p class="text-[11px] text-[#43474f]/50 italic">General Office Stock (Pool)</p>
                                        @endif

                                        @if($dist->subEmployee)
                                            <div class="flex items-center gap-1.5 text-[11px] text-[#43474f]">
                                                <span class="material-symbols-outlined text-[14px] text-[#43474f]/70 font-light">subdirectory_arrow_right</span>
                                                <span>User: <span class="font-medium text-[#1a1c1f]">{{ $dist->subEmployee->fullname }}</span></span>
                                                <span class="text-[9px] bg-[#f1f3f9] text-[#43474f] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">{{ $dist->subEmployee->employment_status }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="font-extrabold text-[#001e40] text-sm">{{ number_format($dist->allocated_quantity) }}</p>
                                    <p class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider">{{ $selectedCobItem->unit }}</p>
                                </div>
                                @if(!$dist->is_locked)
                                    <button wire:click="removeAllocation('{{ $dist->id }}')"
                                            wire:confirm="Remove this allocation?"
                                            class="p-1.5 hover:bg-[#ffdad6] rounded-lg text-[#43474f] hover:text-[#ba1a1a] transition-all flex-shrink-0">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                @else
                                    <div class="w-8 flex-shrink-0"></div>
                                @endif
                            </div>
                        @empty
                            <div class="py-20 text-center text-[#43474f]">
                                <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">inventory_2</span>
                                <p class="text-[12px] font-bold text-[#001e40] mt-3">Ledger is Empty</p>
                                <p class="text-[11px] text-[#43474f] max-w-[240px] mx-auto mt-1">Select an office above to record the initial allocation.</p>
                            </div>
                        @endforelse
                    </div>

                @else
                    {{-- Pane placeholder (nothing selected yet) --}}
                    <div class="flex-1 flex flex-col items-center justify-center gap-3 text-[#43474f] px-8 text-center bg-[#f9f9fe]">
                        <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">arrow_back</span>
                        <p class="font-bold text-[#001e40]">Active Allocation Ledger</p>
                        <p class="text-[13px] max-w-[280px]">Select any budget item on the left to review its dynamic capacity, view existing distributions, or rapidly record a new allocation.</p>
                    </div>
                @endif
            </div>

        </div>
        @endif
    </div>
</div>
