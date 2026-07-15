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
        } elseif ($name === 'newAllocation.employee_id') {
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

    private function getRelatedOfficeAcronyms(Office $office): array
    {
        $acronyms = [$office->acronym];

        // 1. Get ancestors
        $current = $office->parent;
        while ($current) {
            $acronyms[] = $current->acronym;
            $current = $current->parent;
        }

        // 2. Get descendants
        $queue = [$office];
        while (!empty($queue)) {
            $curr = array_shift($queue);
            foreach ($curr->children as $child) {
                $acronyms[] = $child->acronym;
                $queue[] = $child;
            }
        }

        return array_filter(array_unique($acronyms));
    }

    // -------------------------------------------------------------------------
    // Computed: Office dropdown
    // -------------------------------------------------------------------------
    #[Computed]
    public function offices(): array
    {
        return Office::orderBy('name')
            ->get()
            ->mapWithKeys(fn($o) => [$o->id => "{$o->name} ({$o->acronym})"])
            ->toArray();
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

        return Employee::where('office_division', $office->acronym)
            ->where('employment_status', 'PERMANENT')
            ->orderBy('fullname')
            ->get()
            ->mapWithKeys(fn($emp) => [$emp->id => "{$emp->fullname} — {$emp->designation}"])
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Computed: Sub-Employees dropdown (Casual/JO actual users)
    // -------------------------------------------------------------------------
    #[Computed]
    public function subEmployees(): array
    {
        $officerId = $this->newAllocation['employee_id'] ?? '';
        if (!$officerId) return [];
        $officer = Employee::find($officerId);
        if (!$officer) return [];

        return Employee::where('office_division', $officer->office_division)
            ->whereIn('employment_status', ['CASUAL', 'JO'])
            ->orderBy('fullname')
            ->get()
            ->mapWithKeys(fn($emp) => [$emp->id => "{$emp->fullname} — {$emp->designation}"])
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

        @include('livewire.cob.partials.distribution-header-card')

        <div class="flex gap-5 h-[calc(100vh-215px)]">

            @include('livewire.cob.partials.distribution-item-table')

            @include('livewire.cob.partials.distribution-allocation-pane')

        </div>
        @endif
    </div>
</div>
