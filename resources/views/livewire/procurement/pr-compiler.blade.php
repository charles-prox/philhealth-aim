<?php

use App\Models\CobItemDistribution;
use App\Models\CobItem;
use App\Models\Office;
use App\Models\ProcurementFolder;
use App\Models\PrItem;
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
            // Parent already checks APP gate before mounting us; just return silently.
            return;
        }

        if ($folderId) {
            $folder = \App\Models\ProcurementFolder::findOrFail($folderId);
            if ($folder->status === 'CANCELLED' || $folder->status === 'CANCELLED_BY_USER') {
                // Silently return — parent should not have opened us for a cancelled folder.
                return;
            }
        }

        $this->resetState($folderId);
    }

    public function generateNextPrNumber(): string
    {
        return \App\Models\ProcurementFolder::generateNextPrNumber();
    }

    public function generateTrackingNumber(): string
    {
        $sequence = \App\Models\ProcurementFolder::where('tracking_number', 'LIKE', 'TRK-' . now()->year . '-%')->count() + 1;
        return 'TRK-' . now()->year . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
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
        $this->compileTrackingNumber = $this->generateTrackingNumber();
        $this->compilePrNumber = $this->generateNextPrNumber();
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
            $this->autoSelectSignatories();
            $this->currentStep = 2;
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'compileTrackingNumber' => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
                'compilePrNumber'       => 'required|string|max:50|unique:procurement_folders,pr_number,' . ($this->folderId ?? 'NULL') . ',id',
                'compilePurpose'        => 'required|string|max:1000',
                'recommendedById'       => 'required|integer|in:' . implode(',', array_keys($this->validRecommenders)),
                'approvedById'          => 'required|integer|in:' . implode(',', array_keys($this->validApprovers)),
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
    public function applyFilters(): void { $this->resetPage(); $this->selectedIds = []; }
    public function clearFilters(): void { $this->search = ''; $this->filterOfficeId = ''; $this->filterCategory = ''; $this->applyFilters(); }

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

    private function getOfficeForSelection(): ?\App\Models\Office
    {
        if (empty($this->selectedIds)) {
            return null;
        }

        $firstDist = \App\Models\CobItemDistribution::whereIn('id', $this->selectedIds)->first();
        return $firstDist ? $firstDist->office : null;
    }

    public function autoSelectSignatories(): void
    {
        $totalCost = $this->selectionEstimatedValue;
        $office = $this->getOfficeForSelection();

        $this->recommendedById = \App\Services\SignatoryService::getRecommendedSignatory($totalCost, $office);
        $this->approvedById = \App\Services\SignatoryService::getApprovedSignatory($totalCost);
    }

    #[Computed]
    public function validRecommenders(): array
    {
        $office = $this->getOfficeForSelection();
        return \App\Services\SignatoryService::getValidRecommendersOptions($this->selectionEstimatedValue, $office)->toArray();
    }

    #[Computed]
    public function validApprovers(): array
    {
        return \App\Services\SignatoryService::getValidApproversOptions($this->selectionEstimatedValue)->toArray();
    }

    // -------------------------------------------------------------------------
    // Computed: Selection basket summary
    // -------------------------------------------------------------------------
    public function selectionCount(): int { return count($this->selectedIds); }
    public function selectionUniqueItemsCount(): int { return empty($this->selectedIds) ? 0 : CobItemDistribution::whereIn('id', $this->selectedIds)->distinct()->count('cob_item_id'); }
    public function selectionEstimatedValue(): float { return empty($this->selectedIds) ? 0.0 : (float) CobItemDistribution::whereIn('cob_item_distributions.id', $this->selectedIds)->join('cob_items', 'cob_items.id', '=', 'cob_item_distributions.cob_item_id')->selectRaw('SUM(cob_item_distributions.allocated_quantity * (CASE WHEN cob_items.recom_qty > 0 THEN (cob_items.recom_amount / cob_items.recom_qty) ELSE 0 END)) as total')->value('total'); }

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

    public function clearSelection(): void { $this->selectedIds = []; }
    public function openCompileModal(): void { if (empty($this->selectedIds)) return; $this->showCompileModal = true; $this->compileTrackingNumber = $this->generateTrackingNumber(); $this->compilePrNumber = $this->generateNextPrNumber(); $this->compilePurpose = ''; $this->autoSelectSignatories(); }
    public function closeCompileModal(): void { $this->showCompileModal = false; }

    // -------------------------------------------------------------------------
    // Core: PR Generation Engine
    // -------------------------------------------------------------------------
    public function processPrGeneration(\App\Services\ProcurementService $service): void
    {
        $this->validate([
            'compileTrackingNumber' => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
            'compilePrNumber'       => 'required|string|max:50|unique:procurement_folders,pr_number,' . ($this->folderId ?? 'NULL') . ',id',
            'compilePurpose'        => 'required|string|max:1000',
            'recommendedById'       => 'required|integer|in:' . implode(',', array_keys($this->validRecommenders)),
            'approvedById'          => 'required|integer|in:' . implode(',', array_keys($this->validApprovers)),
        ]);

        if (empty($this->selectedIds)) {
            $this->addError('compilePrNumber', 'No distributions selected.');
            return;
        }

        try {
            $folder = $service->compilePrFromCob($this->selectedIds, [
                'trackingNumber' => $this->compileTrackingNumber,
                'prNumber'       => $this->compilePrNumber,
                'purpose'        => $this->compilePurpose,
                'recommendedById'=> $this->recommendedById,
                'approvedById'   => $this->approvedById,
            ], $this->folderId);
        } catch (\RuntimeException $e) {
            $this->addError('compilePrNumber', $e->getMessage());
            return;
        }

        // Reset state and notify parent
        $this->selectedIds      = [];
        $this->showCompileModal = false;
        $this->folderId         = null;
        $this->compileTrackingNumber = $this->generateTrackingNumber();
        $this->compilePrNumber  = $this->generateNextPrNumber();
        $this->compilePurpose   = '';
        $this->requestedById    = null;
        $this->recommendedById  = null;
        $this->approvedById     = null;

        session()->flash('status', 'PR compiled successfully! Please review and sign the documents before submitting.');
        $this->redirectRoute('procurement.review', ['folderId' => $folder->id], navigate: true);
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
            @include('livewire.procurement.partials.compiler-step-one')
        @endif

        @if($currentStep === 2)
            @include('livewire.procurement.partials.compiler-step-two')
        @endif

        @if($currentStep === 3)
            @include('livewire.procurement.partials.compiler-step-three')
        @endif

    </div>
</div>

