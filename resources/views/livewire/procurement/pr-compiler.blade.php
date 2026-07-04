<?php

use App\Models\CobItemDistribution;
use App\Models\CobItem;
use App\Models\Office;
use App\Models\ProcurementFolder;
use App\Models\PrItem;
use App\Models\Employee;
use App\Models\AppHeader;
use App\Models\AppLineItem;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $currentStep = 1;
    public ?string $folderId = null;

    public function mount(?string $folderId = null): void
    {
        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $appGateCleared = \App\Models\AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->exists();

        if (!$appGateCleared) {
            $this->redirectRoute('procurement', navigate: true);
            session()->flash('error', "PR Creation Suspended: The Annual Procurement Plan (APP) for fiscal year {$currentYear} has not been uploaded or approved by the Admin Head.");
            return;
        }

        if ($folderId) {
            $folder = \App\Models\ProcurementFolder::findOrFail($folderId);
            if ($folder->status === 'CANCELLED' || $folder->status === 'CANCELLED_BY_USER') {
                abort(403, 'Access Denied: This Purchase Request has been permanently archived and cannot be modified.');
            }
        }

        $this->resetState($folderId);
    }

    public function generateNextPrNumber(): string
    {
        return \App\Models\ProcurementFolder::generateNextPrNumber();
    }

    #[On('open-pr-creation')]
    public function resetState(?string $folderId = null): void
    {
        $this->selectedIds = [];
        $this->filterOfficeId = '';
        $this->filterMethod = '';
        $this->filterCategory = '';
        $this->search = '';
        $this->showCompileModal = false;
        $this->folderId = $folderId;
        $this->compileTrackingNumber = $this->generateNextPrNumber();
        $this->compilePrNumber = $this->compileTrackingNumber;
        $this->compilePurpose = '';
        $this->requestedById    = null;
        $this->approvedById     = null;
        $this->recommendedById  = null;
        $this->successMessage = null;
        $this->currentStep = 1;

        if ($this->folderId) {
            $folder = \App\Models\ProcurementFolder::findOrFail($this->folderId);
            $this->compileTrackingNumber = $folder->tracking_number;
            $this->compilePrNumber = $folder->pr_number;
            $this->compilePurpose = $folder->overall_purpose;
            $this->recommendedById = $folder->recommended_by_id;
            $this->approvedById = $folder->approved_by_id;

            $this->selectedIds = \App\Models\CobItemDistribution::whereHas('prItem', function($q) {
                $q->where('folder_id', $this->folderId);
            })->pluck('id')->toArray();
        }
    }

    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            if (empty($this->selectedIds)) {
                $this->addError('selectedIds', 'Please select at least one allocation.');
                return;
            }
            $this->currentStep = 2;
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'compileTrackingNumber' => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
                'compilePrNumber'       => 'required|string|max:50|unique:procurement_folders,pr_number,' . ($this->folderId ?? 'NULL') . ',id',
                'compilePurpose'        => 'required|string|max:1000',
                'recommendedById'       => 'required|integer|exists:employees,id',
                'approvedById'          => 'required|integer|exists:employees,id',
            ]);
            $this->currentStep = 3;
        }
    }

    public function prevStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    // -------------------------------------------------------------------------
    // Filter State
    // -------------------------------------------------------------------------
    public string  $filterOfficeId = '';
    public string  $filterMethod   = '';  // Shopping, SVP, Public Bidding, Direct Contracting
    public string  $filterCategory = '';
    public string  $search         = '';

    // -------------------------------------------------------------------------
    // Selection Basket
    // -------------------------------------------------------------------------
    public array $selectedIds = [];

    // -------------------------------------------------------------------------
    // Compile Modal State
    // -------------------------------------------------------------------------
    public bool   $showCompileModal  = false;
    public string $compileTrackingNumber = '';
    public string $compilePrNumber   = '';
    public string $compilePurpose    = '';
    public ?string $requestedById    = null;
    public ?string $approvedById     = null;
    public ?string $recommendedById  = null;

    // -------------------------------------------------------------------------
    // Feedback
    // -------------------------------------------------------------------------
    public ?string $successMessage = null;

    // -------------------------------------------------------------------------
    // Filter Actions (Button Triggered)
    // -------------------------------------------------------------------------
    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterOfficeId = '';
        $this->filterCategory = '';
        $this->applyFilters();
    }

    // -------------------------------------------------------------------------
    // Computed: Categories dropdown
    // -------------------------------------------------------------------------
    #[Computed]
    public function categories(): \Illuminate\Support\Collection
    {
        return CobItem::whereNotNull('exp_desc')
            ->where('exp_desc', '<>', '')
            ->orderBy('exp_desc')
            ->pluck('exp_desc')
            ->unique();
    }

    // -------------------------------------------------------------------------
    // Computed: Offices dropdown
    // -------------------------------------------------------------------------
    #[Computed]
    public function offices(): \Illuminate\Support\Collection
    {
        return Office::orderBy('name')->get();
    }

    #[Computed]
    public function employeeOptions(): array
    {
        return Employee::orderBy('fullname')
            ->get()
            ->mapWithKeys(fn($emp) => [$emp->id => "{$emp->fullname} — {$emp->designation}"])
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Computed: Selection basket summary
    // -------------------------------------------------------------------------
    #[Computed]
    public function selectionCount(): int
    {
        return count($this->selectedIds);
    }

    #[Computed]
    public function selectionUniqueItemsCount(): int
    {
        if (empty($this->selectedIds)) return 0;

        return CobItemDistribution::whereIn('id', $this->selectedIds)
            ->distinct()
            ->count('cob_item_id');
    }

    #[Computed]
    public function selectionEstimatedValue(): float
    {
        if (empty($this->selectedIds)) return 0;

        return (float) CobItemDistribution::whereIn('cob_item_distributions.id', $this->selectedIds)
            ->join('cob_items', 'cob_items.id', '=', 'cob_item_distributions.cob_item_id')
            ->selectRaw('SUM(cob_item_distributions.allocated_quantity * (CASE WHEN cob_items.recom_qty > 0 THEN (cob_items.recom_amount / cob_items.recom_qty) ELSE 0 END)) as total')
            ->value('total');
    }

    #[Computed]
    public function reviewItems(): array
    {
        if (empty($this->selectedIds)) return [];

        $distributions = CobItemDistribution::whereIn('cob_item_distributions.id', $this->selectedIds)
            ->whereNull('pr_item_id')
            ->whereNull('deleted_at')
            ->with(['cobItem'])
            ->get();

        $groups = [];
        $grouped = $distributions->groupBy('cob_item_id');

        foreach ($grouped as $cobItemId => $distGroup) {
            $cobItem   = $distGroup->first()->cobItem;
            $totalQty  = $distGroup->sum('allocated_quantity');
            $recomQty  = $cobItem?->recom_qty ?? 0;
            $unitCost  = $recomQty > 0 ? ((float) ($cobItem?->recom_amount ?? 0) / $recomQty) : 0.0;
            $totalCost = $totalQty * $unitCost;
            $accountType = $unitCost >= 50000 ? 'PAR' : 'ICS';

            $groups[] = [
                'category' => $cobItem?->exp_desc ?? 'Uncategorized',
                'particulars' => $cobItem?->full_particulars ?? $cobItem?->exp_desc ?? 'Unknown Budget Item',
                'ppa_code' => $cobItem?->ppa_code ?? '—',
                'quantity' => $totalQty,
                'unit' => $cobItem?->unit ?? 'pcs',
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'accountability' => $accountType,
            ];
        }

        return $groups;
    }

    // -------------------------------------------------------------------------
    // Actions: Toggle selection
    // -------------------------------------------------------------------------
    public function toggleSelection(string $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_filter($this->selectedIds, fn($i) => $i !== $id));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function toggleCobItemSelection(string $cobItemId): void
    {
        // Get all filtered unprocured distributions for this COB item
        $distIds = CobItemDistribution::where('cob_item_id', $cobItemId)
            ->whereNull('pr_item_id')
            ->whereNull('deleted_at')
            ->when($this->filterOfficeId, fn($q) => $q->where('office_id', $this->filterOfficeId))
            ->pluck('id')
            ->toArray();

        $isFullySelected = !empty($distIds) && empty(array_diff($distIds, $this->selectedIds));

        if ($isFullySelected) {
            // Remove them
            $this->selectedIds = array_values(array_diff($this->selectedIds, $distIds));
        } else {
            // Add them (avoid duplicates)
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $distIds)));
        }
    }

    public function selectAll(): void
    {
        // Select all distributions of all filtered unprocured COB Items
        $query = CobItemDistribution::query()
            ->whereNull('pr_item_id')
            ->whereNull('deleted_at')
            ->join('cob_items', 'cob_items.id', '=', 'cob_item_distributions.cob_item_id')
            ->when($this->filterOfficeId, fn($q) => $q->where('office_id', $this->filterOfficeId))
            ->when($this->filterCategory,  fn($q) => $q->where('cob_items.exp_desc', $this->filterCategory))
            ->when($this->search, fn($q) => $q->where(function ($sq) {
                $sq->where('cob_items.full_particulars', 'like', '%' . $this->search . '%')
                   ->orWhere('cob_items.exp_desc', 'like', '%' . $this->search . '%');
            }));

        $this->selectedIds = $query->pluck('cob_item_distributions.id')->toArray();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    // -------------------------------------------------------------------------
    // Actions: Open / close compile modal
    // -------------------------------------------------------------------------
    public function openCompileModal(): void
    {
        if (empty($this->selectedIds)) return;
        $this->showCompileModal = true;
        $this->compileTrackingNumber = $this->generateNextPrNumber();
        $this->compilePrNumber  = $this->compileTrackingNumber;
        $this->compilePurpose   = '';
    }

    public function closeCompileModal(): void
    {
        $this->showCompileModal = false;
    }

    // -------------------------------------------------------------------------
    // Core: PR Generation Engine
    // -------------------------------------------------------------------------
    public function processPrGeneration(): void
    {
        $this->validate([
            'compileTrackingNumber' => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
            'compilePrNumber'       => 'required|string|max:50|unique:procurement_folders,pr_number,' . ($this->folderId ?? 'NULL') . ',id',
            'compilePurpose'        => 'required|string|max:1000',
            'recommendedById'       => 'required|integer|exists:employees,id',
            'approvedById'          => 'required|integer|exists:employees,id',
        ]);

        if (empty($this->selectedIds)) {
            $this->addError('compilePrNumber', 'No distributions selected.');
            return;
        }

        // Load selected distributions with their COB items
        // If editing, allow distributions that are currently locked into this folder
        $distributions = CobItemDistribution::whereIn('id', $this->selectedIds)
            ->where(function ($q) {
                $q->whereNull('pr_item_id')
                  ->orWhereHas('prItem', function ($sq) {
                      $sq->where('folder_id', $this->folderId);
                  });
            })
            ->whereNull('deleted_at')
            ->with(['cobItem'])
            ->get();

        if ($distributions->isEmpty()) {
            $this->addError('compilePrNumber', 'All selected allocations are already locked into a PR.');
            return;
        }

        // Resolve the requesting employee via the verified FK link on the users table
        $requestedEmployee = auth()->user()->employee;
        $requestedById = $requestedEmployee?->id;
        $requestedByDesignation = $requestedEmployee?->designation ?? 'Requesting Officer';

        // Fetch snapshot titles for other signatories
        $recommendedEmployee = Employee::findOrFail((int) $this->recommendedById);
        $approvedEmployee    = Employee::findOrFail((int) $this->approvedById);

        $folder = DB::transaction(function () use ($distributions, $requestedEmployee, $requestedById, $requestedByDesignation, $recommendedEmployee, $approvedEmployee) {
            if ($this->folderId) {
                // Fetch the existing folder
                $folder = ProcurementFolder::findOrFail($this->folderId);

                // Release old utilized budgets
                foreach ($folder->prItems as $oldItem) {
                    if ($oldItem->app_line_item_id) {
                        $appLineItem = AppLineItem::find($oldItem->app_line_item_id);
                        if ($appLineItem) {
                            $appLineItem->decrement('utilized_budget', $oldItem->estimated_total_cost);
                        }
                    }
                }

                // Release old allocations
                CobItemDistribution::whereHas('prItem', function($q) {
                    $q->where('folder_id', $this->folderId);
                })->update([
                    'pr_item_id' => null,
                    'procured_quantity' => 0,
                ]);

                // Delete old items
                $folder->prItems()->delete();

                // Update folder details
                $folder->update([
                    'tracking_number'              => $this->compileTrackingNumber,
                    'pr_number'                    => $this->compilePrNumber,
                    'overall_purpose'              => $this->compilePurpose,
                    'requesting_unit'              => $requestedEmployee?->office_division,
                    'requested_by_id'              => $requestedById,
                    'requested_by_designation'     => $requestedByDesignation,
                    'recommended_by_id'            => $this->recommendedById,
                    'recommended_by_designation'   => $recommendedEmployee->designation,
                    'approved_by_id'               => $this->approvedById,
                    'approved_by_designation'      => $approvedEmployee->designation,
                    'office_id'                    => auth()->user()->office_id,
                    'created_by_id'                => auth()->id(),
                ]);
            } else {
                // Create new folder
                $folder = ProcurementFolder::create([
                    'tracking_number'              => $this->compileTrackingNumber,
                    'pr_number'                    => $this->compilePrNumber,
                    'project_title'                => 'PR compiled from COB on ' . now()->format('Y-m-d H:i'),
                    'procurement_method'           => 'Shopping',
                    'overall_purpose'              => $this->compilePurpose,
                    'status'                       => 'DRAFT',
                    'requesting_unit'              => $requestedEmployee?->office_division,
                    'requested_by_id'              => $requestedById,
                    'requested_by_designation'     => $requestedByDesignation,
                    'recommended_by_id'            => $this->recommendedById,
                    'recommended_by_designation'   => $recommendedEmployee->designation,
                    'approved_by_id'               => $this->approvedById,
                    'approved_by_designation'      => $approvedEmployee->designation,
                    'office_id'                    => auth()->user()->office_id,
                    'created_by_id'                => auth()->id(),
                ]);
            }

            // Group selected distributions by cob_item_id and create one PrItem per group
            $grouped = $distributions->groupBy('cob_item_id');

            foreach ($grouped as $cobItemId => $distGroup) {
                $cobItem   = $distGroup->first()->cobItem;
                $totalQty  = $distGroup->sum('allocated_quantity');
                $recomQty  = $cobItem?->recom_qty ?? 0;
                $unitCost  = $recomQty > 0 ? ((float) ($cobItem?->recom_amount ?? 0) / $recomQty) : 0.0;

                $appLineItemId = null;
                $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
                $header = AppHeader::where('fiscal_year', $currentYear)
                    ->where('is_approved', true)
                    ->first();
                if ($header) {
                    $matchedLine = AppLineItem::where('app_header_id', $header->id)
                        ->where(function ($q) use ($cobItem) {
                            $q->where('description', 'like', '%' . $cobItem->full_particulars . '%')
                              ->orWhere('project_title', 'like', '%' . $cobItem->full_particulars . '%')
                              ->orWhere('description', 'like', '%' . $cobItem->exp_desc . '%');
                        })
                        ->first();
                    $appLineItemId = $matchedLine?->id;
                }

                // PrItem boot() will auto-set estimated_total_cost & accountability_type
                $prItem = PrItem::create([
                    'folder_id'           => $folder->id,
                    'cob_item_id'         => $cobItemId,
                    'app_line_item_id'    => $appLineItemId,
                    'total_qty'           => $totalQty,
                    'unit_cost'           => $unitCost,
                    'estimated_unit_cost' => $unitCost,
                ]);

                // Increment budget if matched
                if ($appLineItemId) {
                    $matchedLine->increment('utilized_budget', $prItem->estimated_total_cost);
                }

                // Lock the distributions — link them to the new PrItem
                CobItemDistribution::whereIn('id', $distGroup->pluck('id'))
                    ->update([
                        'pr_item_id'         => $prItem->id,
                        'procured_quantity'   => DB::raw('allocated_quantity'),
                    ]);
            }

            return $folder;
        });

        // Reset state and notify parent
        $this->selectedIds      = [];
        $this->showCompileModal = false;
        $this->folderId         = null;
        $this->compileTrackingNumber = $this->generateNextPrNumber();
        $this->compilePrNumber  = $this->compileTrackingNumber;
        $this->compilePurpose   = '';
        $this->requestedById    = null;
        $this->recommendedById  = null;
        $this->approvedById     = null;
        
        \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($folder);

        $this->dispatch('pr-created');
    }

    // -------------------------------------------------------------------------
    // with(): Paginated unique unprocured COB Items
    // -------------------------------------------------------------------------
    public function with(): array
    {
        $query = CobItem::query()
            ->whereHas('distributions', function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('pr_item_id')
                      ->when($this->folderId, function ($ssq) {
                          $ssq->orWhereHas('prItem', function ($sub) {
                              $sub->where('folder_id', $this->folderId);
                          });
                      });
                })
                  ->whereNull('deleted_at')
                  ->when($this->filterOfficeId, fn($sq) => $sq->where('office_id', $this->filterOfficeId));
            })
            ->with(['distributions' => function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('pr_item_id')
                      ->when($this->folderId, function ($ssq) {
                          $ssq->orWhereHas('prItem', function ($sub) {
                              $sub->where('folder_id', $this->folderId);
                          });
                      });
                })
                  ->whereNull('deleted_at')
                  ->when($this->filterOfficeId, fn($sq) => $sq->where('office_id', $this->filterOfficeId))
                  ->with(['office', 'employee']);
            }])
            ->when($this->filterCategory,  fn($q) => $q->where('exp_desc', $this->filterCategory))
            ->when($this->search, fn($q) => $q->where(function ($sq) {
                $sq->where('full_particulars', 'like', '%' . $this->search . '%')
                   ->orWhere('exp_desc', 'like', '%' . $this->search . '%');
            }))
            ->orderBy('exp_desc');

        return [
            'cobItems' => $query->paginate(20),
        ];
    }
}; ?>

<div>
    <div class="space-y-5">



        <!-- Elegant Wizard Step Indicator -->
        <div class="bg-white border border-[#eeedf2] rounded-2xl p-5 shadow-2xs">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Step 1: Selection Basket -->
                <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
                    {{ $currentStep === 1 ? 'bg-[#001e40]/5 border-[#001e40]/20' : ($currentStep > 1 ? 'bg-green-50/40 border-green-100/60 opacity-80' : 'bg-transparent border-transparent opacity-50') }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                        {{ $currentStep === 1 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : ($currentStep > 1 ? 'bg-green-600 text-white' : 'bg-[#eeedf2] text-[#43474f]') }}">
                        @if($currentStep > 1)
                            <span class="material-symbols-outlined text-[16px] font-bold">check</span>
                        @else
                            1
                        @endif
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep >= 1 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Selection Basket</p>
                        <p class="text-[9px] text-[#43474f]/60 leading-tight">Bundle allocations together</p>
                    </div>
                </div>

                <!-- Step 2: PR Details -->
                <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
                    {{ $currentStep === 2 ? 'bg-[#001e40]/5 border-[#001e40]/20' : ($currentStep > 2 ? 'bg-green-50/40 border-green-100/60 opacity-80' : 'bg-transparent border-transparent opacity-50') }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                        {{ $currentStep === 2 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : ($currentStep > 2 ? 'bg-green-600 text-white' : 'bg-[#eeedf2] text-[#43474f]') }}">
                        @if($currentStep > 2)
                            <span class="material-symbols-outlined text-[16px] font-bold">check</span>
                        @else
                            2
                        @endif
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep >= 2 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">PR Details</p>
                        <p class="text-[9px] text-[#43474f]/60 leading-tight">Set tracking & purpose</p>
                    </div>
                </div>

                <!-- Step 3: Review & Lock -->
                <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
                    {{ $currentStep === 3 ? 'bg-[#001e40]/5 border-[#001e40]/20' : 'bg-transparent border-transparent opacity-50' }}">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                        {{ $currentStep === 3 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : 'bg-[#eeedf2] text-[#43474f]' }}">
                        3
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep === 3 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Review & Lock</p>
                        <p class="text-[9px] text-[#43474f]/60 leading-tight">Verify & generate draft</p>
                    </div>
                </div>
            </div>

            <!-- Context Info Sub-banner -->
            <div class="border-t border-[#eeedf2] mt-4 pt-3 flex items-start gap-2.5 text-[11px] text-[#43474f]">
                <span class="material-symbols-outlined text-[16px] text-[#001e40] mt-0.5 flex-shrink-0">info</span>
                <p class="leading-relaxed">
                    <strong class="text-[#001e40] uppercase tracking-wider">
                        @if($currentStep === 1)
                            Step 1: Selection Basket —
                        @elseif($currentStep === 2)
                            Step 2: PR Details —
                        @elseif($currentStep === 3)
                            Step 3: Review & Lock —
                        @endif
                    </strong>
                    @if($currentStep === 1)
                        Select the pending budget allocations from the registry below to bundle into this purchase request.
                    @elseif($currentStep === 2)
                        Enter the official Purchase Request tracking details and operational purpose.
                    @elseif($currentStep === 3)
                        Verify the aggregated quantities, estimated costs, and asset accountability types before final database lock.
                    @endif
                </p>
            </div>
        </div>

        {{-- Success Banner --}}
        @if($successMessage)
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-5 py-3 rounded-xl shadow-sm">
                <span class="material-symbols-outlined text-green-600">check_circle</span>
                <p class="text-sm font-bold flex-1">{{ $successMessage }}</p>
                <a href="{{ route('procurement') }}" wire:navigate class="text-[12px] underline font-bold">View PR Tracker →</a>
                <button wire:click="$set('successMessage', null)" class="p-1 hover:bg-green-100 rounded-lg">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        @endif

        @if($currentStep === 1)
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
        @endif

        {{-- Step 2: PR Details --}}
        @if($currentStep === 2)
            <div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm space-y-6 mt-4 mb-6">
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
                                           placeholder="e.g. PR-2026-00042"
                                           class="w-full pl-9 pr-4 py-3 bg-[#f9f9fe] border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all font-mono font-bold text-[#001e40]"/>
                                </div>
                                @error('compilePrNumber') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                                <p class="text-[9px] text-[#43474f]/60 mt-1">Initially matches recommended tracking. You may customize it.</p>
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
                                               :options="$this->employeeOptions" />
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
                                               :options="$this->employeeOptions" />
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
                        <div class="space-y-6">
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
        @endif

        {{-- Step 3: Signature & Generation Review --}}
        @if($currentStep === 3)
            <div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm space-y-6 mt-4 mb-6">
                <div class="border-b border-[#eeedf2] pb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-bold text-[#001e40]">Review & Lock Document</h3>
                        <p class="text-xs text-[#43474f] mt-1">Review the bundled items and metadata before final database lock.</p>
                    </div>
                    <span class="px-3 py-1.5 bg-[#fff8e1] border border-[#ffe082] text-[#f9a825] text-[10px] font-bold rounded-full uppercase tracking-wider">Awaiting Generation</span>
                </div>

                {{-- PR Preview Box --}}
                <div class="border-2 border-dashed border-[#c3c6d1] rounded-2xl p-6 bg-[#f9f9fe] space-y-5">
                    <!-- PhilHealth Paperwork Header -->
                    <div class="flex justify-between items-start">
                        <div class="space-y-1.5">
                            <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">PhilHealth AIM · Region X</p>
                            <h4 class="text-lg font-bold text-[#001e40]">Purchase Request Bundle</h4>
                            @if($compilePurpose)
                                <p class="text-[12px] text-[#43474f] leading-relaxed max-w-2xl"><strong class="text-[#001e40]">Purpose:</strong> {{ $compilePurpose }}</p>
                            @endif
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="text-right">
                                <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/50">Tracking Number</p>
                                <p class="font-mono text-sm font-bold text-[#43474f]/70 bg-[#eeedf2]/50 px-2.5 py-1 rounded-lg inline-block border border-[#c3c6d1] mt-1">{{ $compileTrackingNumber }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/50">PR Number</p>
                                <p class="font-mono text-sm font-bold text-[#001e40] bg-[#001e40]/5 px-2.5 py-1 rounded-lg inline-block border border-[#001e40]/10 mt-1">{{ $compilePrNumber }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Grouped Items Table -->
                    <div class="border border-[#c3c6d1] rounded-xl overflow-hidden bg-white">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#f4f3f8] border-b border-[#c3c6d1]">
                                    <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Category</th>
                                    <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Item Particulars</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Qty</th>
                                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit Cost</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Total Est. Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#eeedf2]">
                                @foreach($this->reviewItems as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-xs text-[#43474f]">
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-[#43474f]">
                                                {{ $item['category'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-bold text-[#1a1c1f]">{{ $item['particulars'] }}</td>
                                        <td class="px-4 py-3 text-xs text-right font-bold text-[#001e40]">{{ number_format($item['quantity']) }}</td>
                                        <td class="px-4 py-3 text-xs text-center text-[#43474f] font-semibold">{{ $item['unit'] }}</td>
                                        <td class="px-4 py-3 text-xs text-right text-[#43474f]">₱{{ number_format($item['unit_cost'], 2) }}</td>
                                        <td class="px-4 py-3 text-xs text-right font-bold text-[#1a1c1f]">₱{{ number_format($item['total_cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Overall Totals Box -->
                    <div class="flex justify-between items-center bg-[#001e40]/5 px-5 py-3.5 rounded-xl border border-[#001e40]/10">
                        <span class="text-xs font-bold text-[#001e40] uppercase tracking-wider">Estimated Total Budget</span>
                        <span class="text-lg font-black text-[#001e40]">₱{{ number_format($this->selectionEstimatedValue, 2) }}</span>
                    </div>

                    {{-- Signatories Preview Grid --}}
                    @if($this->recommendedById && $this->approvedById)
                        @php
                            $reqEmp  = \App\Models\Employee::where('fullname', auth()->user()->name)->first();
                            $reqName = auth()->user()->name;
                            $reqDesig = $reqEmp ? $reqEmp->designation : 'Requesting Officer';

                            $recEmp  = \App\Models\Employee::find($this->recommendedById);
                            $appEmp  = \App\Models\Employee::find($this->approvedById);
                        @endphp
                        @if($recEmp && $appEmp)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 border-t border-[#eeedf2] pt-5 mt-5">
                                <div class="space-y-1">
                                    <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">Requested By</p>
                                    <p class="text-xs font-bold text-[#001e40]">{{ $reqName }}</p>
                                    <p class="text-[10px] text-[#43474f]/70 italic">{{ $reqDesig }}</p>
                                </div>
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

                {{-- Lock Notice --}}
                <div class="flex items-start gap-3 bg-[#fff8e1] border border-[#ffe082] rounded-xl px-4 py-3">
                    <span class="material-symbols-outlined text-[#f9a825] text-[20px] flex-shrink-0 mt-0.5">lock</span>
                    <div class="text-xs text-[#5d4037] leading-relaxed">
                        <strong>Lock Notice:</strong> All <strong>{{ $this->selectionCount }}</strong> selected allocation{{ $this->selectionCount > 1 ? 's' : '' }} will be locked on generate. They will be bound to the new PR Folder and cannot be realigned or reallocated.
                    </div>
                </div>

                {{-- Step 3 Buttons --}}
                <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
                    <button wire:click="prevStep"
                            class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Back to Details
                    </button>
                    <div class="flex items-center gap-3">
                        <button x-on:click="$dispatch('close-pr-creation')"
                                class="px-5 py-2.5 text-sm font-bold text-[#ba1a1a] hover:bg-[#ba1a1a]/5 rounded-xl transition-all">
                            Cancel PR
                        </button>
                        <button wire:click="processPrGeneration"
                                wire:loading.attr="disabled"
                                class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md disabled:opacity-60">
                            <span wire:loading wire:target="processPrGeneration" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span wire:loading.remove wire:target="processPrGeneration" class="material-symbols-outlined text-[18px]">auto_awesome</span>
                            Confirm & Generate PR
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
