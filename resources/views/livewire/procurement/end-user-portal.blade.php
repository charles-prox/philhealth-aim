<?php

use App\Models\AppHeader;
use App\Models\AppLineItem;
use App\Models\Employee;
use App\Models\PrItem;
use App\Models\ProcurementFolder;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination;

    public int $currentStep = 1;
    public ?string $folderId = null;

    // Wizard Form Fields
    public string $trackingNumber = '';
    public string $purpose = '';
    public ?int $recommendedById = null;
    public ?int $approvedById = null;

    // Search and Input State
    public string $search = '';
    public string $searchQuery = '';
    public ?int $selectedAppLineId = null;
    public array $basket = [];

    public ?string $successMessage = null;

    public function mount(?string $folderId = null): void
    {
        // Enforce APP Gate Check
        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $appGateCleared = AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->exists();

        if (!$appGateCleared) {
            $this->redirectRoute('procurement', navigate: true);
            session()->flash('error', "PR Creation Suspended: The Annual Procurement Plan (APP) for fiscal year {$currentYear} has not been uploaded or approved by the Admin Head.");
            return;
        }

        $this->folderId = $folderId;
        $this->resetState();
    }

    public function generateTrackingNumber(): string
    {
        $sequence = ProcurementFolder::where('tracking_number', 'LIKE', 'TRK-' . now()->year . '-%')->count() + 1;
        return 'TRK-' . now()->year . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    public function resetState(): void
    {
        $this->basket = [];
        $this->selectedAppLineId = null;
        $this->purpose = '';
        $this->recommendedById = null;
        $this->approvedById = null;
        $this->currentStep = 1;
        $this->trackingNumber = $this->generateTrackingNumber();

        if ($this->folderId) {
            $folder = ProcurementFolder::findOrFail($this->folderId);
            $this->trackingNumber = $folder->tracking_number;
            $this->purpose = $folder->overall_purpose;
            $this->recommendedById = $folder->recommended_by_id;
            $this->approvedById = $folder->approved_by_id;

            // Restore basket from pr_items
            foreach ($folder->prItems as $item) {
                if ($item->app_line_item_id) {
                    $this->selectedAppLineId = $item->app_line_item_id;
                    $uniqueKey = 'item_' . uniqid() . '_' . $item->id;
                    $this->basket[$uniqueKey] = [
                        'app_line_item_id' => $item->app_line_item_id,
                        'project_title' => $item->appLineItem?->project_title ?? 'Ad-hoc Item',
                        'description' => $item->item_description_override ?? $item->appLineItem?->description ?? 'Unknown Particulars',
                        'qty' => $item->total_qty,
                        'unit' => $item->unit ?? 'pcs',
                        'unit_cost' => (float) $item->estimated_unit_cost,
                        'total_cost' => (float) $item->estimated_total_cost,
                    ];
                }
            }
        }
    }

    public function isInBasket(int $appLineItemId): bool
    {
        return $this->selectedAppLineId === $appLineItemId;
    }

    public function toggleSelection(int $itemId): void
    {
        $appLineItem = AppLineItem::findOrFail($itemId);

        if ($this->isInBasket($itemId)) {
            $this->selectedAppLineId = null;
            $this->basket = [];
            $this->dispatch('toast', [
                'type' => 'info',
                'message' => 'APP line item deselected.',
                'title' => 'Deselected'
            ]);
        } else {
            $this->selectedAppLineId = $itemId;
            $this->basket = [];
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'APP line item selected!',
                'title' => 'Selected'
            ]);
        }
    }

    public function removeFromBasket(int $itemId): void
    {
        $this->selectedAppLineId = null;
        $this->basket = [];
        $this->dispatch('toast', [
            'type' => 'info',
            'message' => 'Item removed from selection.',
            'title' => 'Removed'
        ]);
    }

    public function addItemRowToAppLine(int $appLineItemId): void
    {
        $appLineItem = AppLineItem::findOrFail($appLineItemId);
        $uniqueKey = 'item_' . uniqid();
        $this->basket[$uniqueKey] = [
            'app_line_item_id' => $appLineItemId,
            'project_title'    => $appLineItem->project_title,
            'description'      => '',
            'qty'              => 1,
            'unit'             => 'pcs',
            'unit_cost'        => 0.0,
            'total_cost'       => 0.0,
        ];
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'New item row added.',
            'title' => 'Row Added'
        ]);
    }

    public function removeItemRow(string $basketKey): void
    {
        if (isset($this->basket[$basketKey])) {
            unset($this->basket[$basketKey]);
            $this->dispatch('toast', [
                'type' => 'info',
                'message' => 'Item row removed.',
                'title' => 'Row Removed'
            ]);
        }
    }

    public function removeAppLineFromBasket(int $appLineItemId): void
    {
        $this->selectedAppLineId = null;
        $this->basket = [];
        $this->dispatch('toast', [
            'type' => 'info',
            'message' => 'APP line deselected.',
            'title' => 'Deselected'
        ]);
    }

    public function updatedBasket($value, $key): void
    {
        // Key format: "basketKey.field" (e.g., "item_uuid.qty")
        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $basketKey = $parts[0];
            $field = $parts[1];

            if ($field === 'qty' || $field === 'unit_cost') {
                $qty = (int) ($this->basket[$basketKey]['qty'] ?? 1);
                $unitCost = (float) ($this->basket[$basketKey]['unit_cost'] ?? 0.0);
                $this->basket[$basketKey]['total_cost'] = $qty * $unitCost;
            }
        }
    }

    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            if (!$this->selectedAppLineId) {
                $this->addError('basket', 'Your selection is empty. Please select an APP line item to fund your PR.');
                return;
            }
            $this->currentStep = 2;
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'trackingNumber'   => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
                'purpose'          => 'required|string|max:1000',
                'recommendedById'  => 'required|integer|exists:employees,id',
                'approvedById'     => 'required|integer|exists:employees,id',
            ]);

            if (empty($this->basket)) {
                $this->addError('basket', 'Please add at least one line item row to configure details.');
                return;
            }

            // Validate Basket items (Qty, Unit Cost, Budget Availability)
            $hasError = false;
            foreach ($this->basket as $basketKey => $itemData) {
                $qty = (int) ($itemData['qty'] ?? 0);
                $unitCost = (float) ($itemData['unit_cost'] ?? 0.0);
                $desc = $itemData['description'] ?? '';
                $unit = $itemData['unit'] ?? '';

                if (empty($desc)) {
                    $this->addError("basket.{$basketKey}.description", "Particulars/description is required.");
                    $hasError = true;
                }

                if (empty($unit)) {
                    $this->addError("basket.{$basketKey}.unit", "Unit is required.");
                    $hasError = true;
                }

                if ($qty <= 0) {
                    $this->addError("basket.{$basketKey}.qty", "Quantity must be at least 1.");
                    $hasError = true;
                }

                if ($unitCost <= 0.0) {
                    $this->addError("basket.{$basketKey}.unit_cost", "Estimated unit cost must be greater than 0.");
                    $hasError = true;
                }
            }

            // Sum up total cost grouped by app_line_item_id to validate budget availability
            $totalsByAppLine = collect($this->basket)
                ->groupBy('app_line_item_id')
                ->map(fn($items) => $items->sum(fn($i) => (int)$i['qty'] * (float)$i['unit_cost']));

            foreach ($totalsByAppLine as $appLineItemId => $totalCost) {
                $appLineItem = AppLineItem::find($appLineItemId);
                if ($appLineItem) {
                    $availableBudget = $appLineItem->approved_budget - $appLineItem->utilized_budget;
                    $alreadyUtilized = 0;
                    if ($this->folderId) {
                        $existingCost = PrItem::where('folder_id', $this->folderId)
                            ->where('app_line_item_id', $appLineItemId)
                            ->sum('estimated_total_cost');
                        $alreadyUtilized = $existingCost;
                    }
                    $availableBudget += $alreadyUtilized;

                    if ($totalCost > $availableBudget) {
                        $firstBasketKey = collect($this->basket)
                            ->where('app_line_item_id', $appLineItemId)
                            ->keys()
                            ->first();

                        $this->addError("basket.{$firstBasketKey}.unit_cost", "Combined items cost (₱" . number_format($totalCost, 2) . ") under " . $appLineItem->project_title . " exceeds available budget of ₱" . number_format($availableBudget, 2) . ".");
                        $hasError = true;
                    }
                }
            }

            if ($hasError) {
                return;
            }

            $this->currentStep = 3;
        }
    }

    public function prevStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function processPrGeneration(bool $submitToGsu = false): void
    {
        $this->validate([
            'trackingNumber'   => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
            'purpose'          => 'required|string|max:1000',
            'recommendedById'  => 'required|integer|exists:employees,id',
            'approvedById'     => 'required|integer|exists:employees,id',
        ]);

        if (empty($this->basket)) {
            $this->addError('trackingNumber', 'Your selection is empty. Please select at least one APP line item.');
            return;
        }

        // Validate Basket items (Qty, Unit Cost, Budget Availability)
        $hasError = false;
        foreach ($this->basket as $basketKey => $itemData) {
            $qty = (int) ($itemData['qty'] ?? 0);
            $unitCost = (float) ($itemData['unit_cost'] ?? 0.0);
            $desc = $itemData['description'] ?? '';
            $unit = $itemData['unit'] ?? '';

            if (empty($desc)) {
                $this->addError("basket.{$basketKey}.description", "Particulars/description is required.");
                $hasError = true;
            }

            if (empty($unit)) {
                $this->addError("basket.{$basketKey}.unit", "Unit is required.");
                $hasError = true;
            }

            if ($qty <= 0) {
                $this->addError("basket.{$basketKey}.qty", "Quantity must be at least 1.");
                $hasError = true;
            }

            if ($unitCost <= 0.0) {
                $this->addError("basket.{$basketKey}.unit_cost", "Estimated unit cost must be greater than 0.");
                $hasError = true;
            }
        }

        // Sum up total cost grouped by app_line_item_id to validate budget availability
        $totalsByAppLine = collect($this->basket)
            ->groupBy('app_line_item_id')
            ->map(fn($items) => $items->sum(fn($i) => (int)$i['qty'] * (float)$i['unit_cost']));

        foreach ($totalsByAppLine as $appLineItemId => $totalCost) {
            $appLineItem = AppLineItem::find($appLineItemId);
            if ($appLineItem) {
                $availableBudget = $appLineItem->approved_budget - $appLineItem->utilized_budget;
                $alreadyUtilized = 0;
                if ($this->folderId) {
                    $existingCost = PrItem::where('folder_id', $this->folderId)
                        ->where('app_line_item_id', $appLineItemId)
                        ->sum('estimated_total_cost');
                    $alreadyUtilized = $existingCost;
                }
                $availableBudget += $alreadyUtilized;

                if ($totalCost > $availableBudget) {
                    $firstBasketKey = collect($this->basket)
                        ->where('app_line_item_id', $appLineItemId)
                        ->keys()
                        ->first();

                    $this->addError("basket.{$firstBasketKey}.unit_cost", "Combined items cost (₱" . number_format($totalCost, 2) . ") under " . $appLineItem->project_title . " exceeds available budget of ₱" . number_format($availableBudget, 2) . ".");
                    $hasError = true;
                }
            }
        }

        if ($hasError) {
            return;
        }

        $requestedEmployee = auth()->user()->employee;
        $requestedById = $requestedEmployee?->id;
        $requestedByDesignation = $requestedEmployee?->designation ?? 'Requesting Officer';

        $recommendedEmployee = Employee::findOrFail((int) $this->recommendedById);
        $approvedEmployee    = Employee::findOrFail((int) $this->approvedById);

        $status = $submitToGsu ? 'SUBMITTED_TO_GSU' : 'DRAFT';

        $folder = DB::transaction(function () use ($status, $requestedEmployee, $requestedById, $requestedByDesignation, $recommendedEmployee, $approvedEmployee) {
            if ($this->folderId) {
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

                $folder->prItems()->delete();

                $folder->update([
                    'tracking_number'              => $this->trackingNumber,
                    'overall_purpose'              => $this->purpose,
                    'status'                       => $status,
                    'requesting_unit'              => $requestedEmployee?->office_division,
                    'requested_by_id'              => $requestedById,
                    'requested_by_designation'     => $requestedByDesignation,
                    'recommended_by_id'            => $this->recommendedById,
                    'recommended_by_designation'   => $recommendedEmployee->designation,
                    'approved_by_id'               => $this->approvedById,
                    'approved_by_designation'      => $approvedEmployee->designation,
                ]);
            } else {
                $folder = ProcurementFolder::create([
                    'tracking_number'              => $this->trackingNumber,
                    'project_title'                => 'Ad-hoc PR compiled from APP on ' . now()->format('Y-m-d H:i'),
                    'procurement_method'           => 'Shopping',
                    'overall_purpose'              => $this->purpose,
                    'status'                       => $status,
                    'requesting_unit'              => $requestedEmployee?->office_division,
                    'requested_by_id'              => $requestedById,
                    'requested_by_designation'     => $requestedByDesignation,
                    'recommended_by_id'            => $this->recommendedById,
                    'recommended_by_designation'   => $recommendedEmployee->designation,
                    'approved_by_id'               => $this->approvedById,
                    'approved_by_designation'      => $approvedEmployee->designation,
                ]);
            }

            foreach ($this->basket as $basketKey => $itemData) {
                $prItem = PrItem::create([
                    'folder_id'                 => $folder->id,
                    'cob_item_id'               => null,
                    'app_line_item_id'          => $itemData['app_line_item_id'],
                    'item_description_override' => $itemData['description'],
                    'total_qty'                 => $itemData['qty'],
                    'unit'                      => $itemData['unit'] ?? 'pcs',
                    'unit_cost'                 => $itemData['unit_cost'],
                    'estimated_unit_cost'       => $itemData['unit_cost'],
                ]);

                $appLineItem = AppLineItem::find($itemData['app_line_item_id']);
                if ($appLineItem) {
                    $appLineItem->increment('utilized_budget', $prItem->estimated_total_cost);
                }
            }

            // Create Log
            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => $status === 'SUBMITTED_TO_GSU' ? 'SUBMITTED' : 'CREATED',
                'actor_id' => $requestedById,
                'remarks' => $status === 'SUBMITTED_TO_GSU' ? 'Ad-hoc PR submitted to GSU Triage Box.' : 'Ad-hoc PR draft saved.',
            ]);

            return $folder;
        });

        \App\Jobs\GeneratePrPdfJob::dispatch($folder);

        $this->selectedIds = [];
        $this->basket = [];
        
        session()->flash('status', 'PR compiled successfully! Folder has been created/updated in the Procurement Tracker.');
        $this->returnToDashboard();
    }

    public function returnToDashboard(): void
    {
        $user = auth()->user();
        if ($user->hasRole('Office Head')) {
            $this->redirectRoute('procurement.office', navigate: true);
        } elseif ($user->hasAnyRole(['Admin', 'Procurement Officer'])) {
            $this->redirectRoute('procurement.admin', navigate: true);
        } else {
            $this->redirectRoute('procurement.portal', navigate: true);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function performSearch(): void
    {
        $this->search = $this->searchQuery;
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->searchQuery = '';
        $this->search = '';
        $this->resetPage();
    }

    #[Computed]
    public function appLineItems(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $currentYear = \App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year;
        $header = AppHeader::where('fiscal_year', $currentYear)
            ->where('is_approved', true)
            ->first();

        if (!$header) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        }

        $query = AppLineItem::where('app_header_id', $header->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where(DB::raw('LOWER(project_title)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(description)'), 'like', '%' . strtolower($this->search) . '%')
                  ->orWhere(DB::raw('LOWER(procurement_mode)'), 'like', '%' . strtolower($this->search) . '%');
            });
        }

        return $query->orderBy('project_title')->paginate(10);
    }

    #[Computed]
    public function selectedAppLine(): ?AppLineItem
    {
        return $this->selectedAppLineId ? AppLineItem::find($this->selectedAppLineId) : null;
    }

    #[Computed]
    public function employeeOptions(): array
    {
        return Employee::orderBy('fullname')
            ->get()
            ->mapWithKeys(fn($emp) => [$emp->id => "{$emp->fullname} — {$emp->designation}"])
            ->toArray();
    }

    #[Computed]
    public function totalBasketValue(): float
    {
        return collect($this->basket)->sum('total_cost');
    }
}; ?>

<div>
    <div class="space-y-5">
        <!-- Sticky Wizard Header Wrapper -->
        <div class="sticky top-0 z-30 bg-[#f1f3f6] -mt-6 pt-6 pb-3 space-y-4">
            <!-- Top Back Bar -->
            <div class="flex items-center">
                <button wire:click="returnToDashboard" 
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-[#c3c6d1] hover:border-[#001e40] text-[#43474f] hover:text-[#001e40] font-bold text-xs rounded-xl shadow-sm hover:shadow transition-all active:scale-95">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Cancel & Exit Portal
                </button>
            </div>

            <!-- Wizard Step Indicator -->
            <div class="bg-white border border-[#eeedf2] rounded-2xl p-5 shadow-2xs">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
                        {{ $currentStep === 1 ? 'bg-[#001e40]/5 border-[#001e40]/20' : ($currentStep > 1 ? 'bg-green-50/40 border-green-100/60 opacity-80' : 'bg-transparent border-transparent opacity-50') }}">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                            {{ $currentStep === 1 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : ($currentStep > 1 ? 'bg-green-600 text-white' : 'bg-[#eeedf2] text-[#43474f]') }}">
                            @if($currentStep > 1) <span class="material-symbols-outlined text-[16px] font-bold">check</span> @else 1 @endif
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep >= 1 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Browse APP Catalog</p>
                            <p class="text-[9px] text-[#43474f]/60 leading-tight">Select official line items</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
                        {{ $currentStep === 2 ? 'bg-[#001e40]/5 border-[#001e40]/20' : ($currentStep > 2 ? 'bg-green-50/40 border-green-100/60 opacity-80' : 'bg-transparent border-transparent opacity-50') }}">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                            {{ $currentStep === 2 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : ($currentStep > 2 ? 'bg-green-600 text-white' : 'bg-[#eeedf2] text-[#43474f]') }}">
                            @if($currentStep > 2) <span class="material-symbols-outlined text-[16px] font-bold">check</span> @else 2 @endif
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep >= 2 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Configure PR & Items</p>
                            <p class="text-[9px] text-[#43474f]/60 leading-tight">Set details, qty & purpose</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
                        {{ $currentStep === 3 ? 'bg-[#001e40]/5 border-[#001e40]/20' : 'bg-transparent border-transparent opacity-50' }}">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                            {{ $currentStep === 3 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : 'bg-[#eeedf2] text-[#43474f]' }}">
                            3
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep === 3 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Review & Submit</p>
                            <p class="text-[9px] text-[#43474f]/60 leading-tight">Verify ad-hoc PR bundle</p>
                        </div>
                    </div>
                </div>
            </div>

            @if($currentStep === 1)
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
            @endif
        </div>


        {{-- Step 1 UI --}}
        @if($currentStep === 1)



            @error('basket')
                <div class="text-sm font-bold text-[#ba1a1a] bg-red-50 px-4 py-2 rounded-xl border border-red-200">{{ $message }}</div>
            @enderror

            <!-- Catalog Layout -->
            <div class="space-y-4">
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
                    {{ $this->appLineItems->links() }}
                </div>
            </div>
        @endif

        {{-- Step 2 UI --}}
        @if($currentStep === 2)
            <div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm space-y-6"
                 x-data="{
                    basket: $wire.entangle('basket'),
                    formatPrice(val) {
                        return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);
                    },
                    get totalValue() {
                        return Object.values(this.basket).reduce((sum, item) => sum + (parseFloat(item.qty || 0) * parseFloat(item.unit_cost || 0)), 0);
                    }
                 }">
                <div class="border-b border-[#eeedf2] pb-4">
                    <h3 class="text-xl font-bold text-[#001e40]">Enter PR Metadata & Items Details</h3>
                    <p class="text-xs text-[#43474f] mt-1">Configure item descriptions, quantities, and cost alongside references.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
                    {{-- Form inputs --}}
                    <div class="lg:col-span-2 space-y-6">
                        
                        {{-- Metadata Fields --}}
                        <div class="bg-[#f9f9fe] border border-[#eeedf2] p-5 rounded-2xl space-y-4">
                            <h4 class="text-sm font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">info</span> PR General Info
                            </h4>
                            
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">System Tracking Number</label>
                                <div class="relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f]/60 text-[18px]">lock</span>
                                    <input type="text" wire:model="trackingNumber" readonly disabled
                                           class="w-full pl-9 pr-4 py-3 bg-[#eeedf2]/50 border border-[#c3c6d1] rounded-xl text-sm outline-none transition-all font-mono font-bold text-[#43474f]/70 cursor-not-allowed"/>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Recommended By <span class="text-[#ba1a1a]">*</span></label>
                                    <x-form-select label="" placeholder="Select Recommending Officer..." icon="recommend" searchable wire:model="recommendedById" :options="$this->employeeOptions" />
                                    @error('recommendedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Approved By <span class="text-[#ba1a1a]">*</span></label>
                                    <x-form-select label="" placeholder="Select Approving Officer..." icon="person_check" searchable wire:model="approvedById" :options="$this->employeeOptions" />
                                    @error('approvedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Purpose / Justification <span class="text-[#ba1a1a]">*</span></label>
                                <textarea wire:model="purpose" placeholder="Provide the operational justification..." class="w-full px-4 py-3 bg-white border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all resize-none min-h-[100px]"></textarea>
                                @error('purpose') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Item Specifications --}}
                        <div class="space-y-4">
                            <h4 class="text-sm font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span> PR Line Items Configuration
                            </h4>

                            <div class="space-y-4">
                                @php
                                    $appLineItem = \App\Models\AppLineItem::find($selectedAppLineId);
                                    $available = 0;
                                    if ($appLineItem) {
                                        $available = $appLineItem->approved_budget - $appLineItem->utilized_budget;
                                        if ($this->folderId) {
                                            $existingCost = \App\Models\PrItem::where('folder_id', $this->folderId)
                                                ->where('app_line_item_id', $selectedAppLineId)
                                                ->sum('estimated_total_cost');
                                            $available += $existingCost;
                                        }
                                    }
                                @endphp
                                @if($appLineItem)
                                    <div class="p-5 border border-[#c3c6d1] rounded-2xl bg-white space-y-4 shadow-2xs relative">
                                        <div class="flex justify-between items-start border-b border-[#eeedf2] pb-3">
                                            <div>
                                                <h5 class="font-bold text-sm text-[#001e40]">PR Items List</h5>
                                                <p class="text-[10px] text-[#43474f]/70 mt-0.5 uppercase tracking-wider font-semibold">
                                                    Funded by APP Line remaining budget: <span class="font-bold text-green-700">₱{{ number_format($available, 2) }} available</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="space-y-4 divide-y divide-[#eeedf2] -mt-2">
                                            @forelse($basket as $basketKey => $basketItem)
                                                <div class="pt-4 first:pt-2 space-y-3">
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-[10px] font-bold text-[#001e40] uppercase tracking-wider">Item #{{ $loop->iteration }} Details</span>
                                                        <button wire:click="removeItemRow('{{ $basketKey }}')" class="text-[11px] text-[#ba1a1a] hover:underline flex items-center gap-0.5">
                                                            <span class="material-symbols-outlined text-[12px]">remove_circle</span> Remove Row
                                                        </button>
                                                    </div>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                        <!-- Left Column: Particulars / Description (textarea) -->
                                                        <div>
                                                            <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Item Particulars <span class="text-[#ba1a1a]">*</span></label>
                                                            <textarea wire:model.blur="basket.{{ $basketKey }}.description" x-model="basket['{{ $basketKey }}']['description']" placeholder="Enter detailed item particulars/description..." rows="3" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40] resize-y"></textarea>
                                                            @error("basket.{$basketKey}.description")
                                                                <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                            @enderror
                                                        </div>

                                                        <!-- Right Column: Unit, Qty, Cost -->
                                                        <div class="space-y-3">
                                                            <div class="grid grid-cols-3 gap-2.5">
                                                                <div>
                                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Unit <span class="text-[#ba1a1a]">*</span></label>
                                                                    <input type="text" wire:model.blur="basket.{{ $basketKey }}.unit" x-model="basket['{{ $basketKey }}']['unit']" placeholder="pcs, box, ream" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" />
                                                                    @error("basket.{$basketKey}.unit")
                                                                        <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                                    @enderror
                                                                </div>

                                                                <div>
                                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Quantity <span class="text-[#ba1a1a]">*</span></label>
                                                                    <input type="number" min="1" wire:model.blur="basket.{{ $basketKey }}.qty" x-model.number="basket['{{ $basketKey }}']['qty']" placeholder="1" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" />
                                                                    @error("basket.{$basketKey}.qty")
                                                                        <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                                    @enderror
                                                                </div>

                                                                <div>
                                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Est. Unit Cost <span class="text-[#ba1a1a]">*</span></label>
                                                                    <div class="relative">
                                                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#43474f]">₱</span>
                                                                        <input type="number" step="0.01" min="0.01" wire:model.blur="basket.{{ $basketKey }}.unit_cost" x-model.number="basket['{{ $basketKey }}']['unit_cost']" placeholder="0.00" class="w-full pl-5 pr-2 py-2 bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" />
                                                                    </div>
                                                                    @error("basket.{$basketKey}.unit_cost")
                                                                        <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                                    @enderror
                                                                </div>
                                                            </div>

                                                            <div class="flex justify-between items-center text-[11px] font-bold text-[#43474f] pt-1">
                                                                <span>Subtotal</span>
                                                                <span class="text-[#001e40]" x-text="'₱' + formatPrice((basket['{{ $basketKey }}']['qty'] || 0) * (basket['{{ $basketKey }}']['unit_cost'] || 0))"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-center py-10 text-xs text-[#43474f]/50 italic">
                                                    No line items configured yet. Click "+ Add Item Row" below to add your first item particulars.
                                                </div>
                                            @endforelse
                                        </div>

                                        <div class="flex justify-start border-t border-[#eeedf2] pt-3">
                                            <button type="button" wire:click="addItemRowToAppLine({{ $selectedAppLineId }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#f9f9fe] hover:bg-[#eeedf2] text-[#001e40] border border-[#c3c6d1] hover:border-[#001e40] text-[11px] font-bold rounded-lg shadow-2xs transition-all">
                                                <span class="material-symbols-outlined text-[15px]">add</span> Add Item Row
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>

                    {{-- Summary Side card --}}
                    <div class="lg:col-span-1">
                        <div class="bg-[#f9f9fe] border border-[#eeedf2] rounded-2xl p-6 flex flex-col justify-between h-full sticky top-[176px]">
                            <div class="space-y-6">
                                <div class="flex items-center gap-2 border-b border-[#eeedf2] pb-3">
                                    <div class="w-8 h-8 rounded-lg bg-[#001e40]/10 flex items-center justify-center text-[#001e40]">
                                        <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider">Ad-hoc PR Summary</h4>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    @if($this->selectedAppLine)
                                        <div class="bg-white border border-[#eeedf2] rounded-xl p-4 shadow-sm space-y-2">
                                            <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Funding APP Line Source</span>
                                            <p class="text-xs font-bold text-[#001e40] leading-snug">{{ $this->selectedAppLine->description }}</p>
                                            <p class="text-[10px] text-[#43474f]/70 truncate" title="{{ $this->selectedAppLine->project_title }}">{{ $this->selectedAppLine->project_title }}</p>
                                            <div class="pt-2 border-t border-[#eeedf2] flex justify-between items-center text-[10px]">
                                                <span class="font-bold text-[#43474f]/60 uppercase">Available Budget:</span>
                                                <span class="font-bold text-green-700">₱{{ number_format($this->selectedAppLine->approved_budget - $this->selectedAppLine->utilized_budget, 2) }}</span>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="bg-white border border-[#eeedf2] rounded-xl p-4 shadow-sm">
                                        <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">PR Estimated Value</span>
                                        <span class="text-2xl font-bold text-green-700 block mt-1" x-text="'₱' + formatPrice(totalValue)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Buttons --}}
                <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
                    <button wire:click="prevStep" class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Catalog
                    </button>
                    <button wire:click="nextStep" class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md">
                        Next: Review & Submit <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Step 3 UI --}}
        @if($currentStep === 3)
            <div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm space-y-6">
                <div class="border-b border-[#eeedf2] pb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-bold text-[#001e40]">Review Ad-hoc Purchase Request</h3>
                        <p class="text-xs text-[#43474f] mt-1">Review the bundled items before final submission.</p>
                    </div>
                    <span class="px-3 py-1.5 bg-[#eeedf2] text-[#43474f] text-[10px] font-bold rounded-full uppercase tracking-wider">Unsubmitted Draft</span>
                </div>

                <div class="border-2 border-dashed border-[#c3c6d1] rounded-2xl p-6 bg-[#f9f9fe] space-y-5">
                    <div class="flex justify-between items-start">
                        <div class="space-y-1.5">
                            <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">PhilHealth AIM · Region X</p>
                            <h4 class="text-lg font-bold text-[#001e40]">Ad-hoc PR Proposal</h4>
                            @if($purpose)
                                <p class="text-[12px] text-[#43474f] leading-relaxed max-w-2xl"><strong class="text-[#001e40]">Purpose:</strong> {{ $purpose }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase font-bold tracking-wider text-[#43474f]/50">Tracking Number</p>
                            <p class="font-mono text-sm font-bold text-[#43474f]/70 bg-[#eeedf2]/50 px-2.5 py-1 rounded-lg inline-block border border-[#c3c6d1] mt-1">{{ $trackingNumber }}</p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="border border-[#c3c6d1] rounded-xl overflow-hidden bg-white">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#f4f3f8] border-b border-[#c3c6d1]">
                                    <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Project Header</th>
                                    <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Particulars / Description</th>
                                    <th class="px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Qty</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Unit Cost</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-[#43474f]">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#eeedf2]">
                                @foreach($basket as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-xs text-[#43474f] font-bold">{{ $item['project_title'] }}</td>
                                        <td class="px-4 py-3 text-xs text-[#1a1c1f]">{{ $item['description'] }}</td>
                                        <td class="px-4 py-3 text-xs text-center text-[#43474f]">{{ $item['unit'] }}</td>
                                        <td class="px-4 py-3 text-xs text-right font-bold text-[#001e40]">{{ $item['qty'] }}</td>
                                        <td class="px-4 py-3 text-xs text-right text-[#43474f]">₱{{ number_format($item['unit_cost'], 2) }}</td>
                                        <td class="px-4 py-3 text-xs text-right font-bold text-[#1a1c1f]">₱{{ number_format($item['total_cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Overall Total -->
                    <div class="flex justify-between items-center bg-[#001e40]/5 px-5 py-3.5 rounded-xl border border-[#001e40]/10">
                        <span class="text-xs font-bold text-[#001e40] uppercase tracking-wider">Estimated Total Value</span>
                        <span class="text-lg font-black text-[#001e40]">₱{{ number_format($this->totalBasketValue, 2) }}</span>
                    </div>

                    {{-- Signatories --}}
                    @if($recommendedById && $approvedById)
                        @php
                            $recEmp  = Employee::find($this->recommendedById);
                            $appEmp  = Employee::find($this->approvedById);
                        @endphp
                        @if($recEmp && $appEmp)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-t border-[#eeedf2] pt-5 mt-5">
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

                {{-- Actions --}}
                <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
                    <button wire:click="prevStep" class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Details
                    </button>
                    <div class="flex items-center gap-3">
                        <button wire:click="processPrGeneration(false)" wire:loading.attr="disabled" class="px-5 py-2.5 text-xs font-bold border border-[#c3c6d1] text-[#43474f] rounded-xl hover:bg-gray-100 transition-all flex items-center gap-1.5">
                            <span wire:loading wire:target="processPrGeneration(false)" class="w-3 h-3 border-2 border-gray-500 border-t-transparent rounded-full animate-spin"></span>
                            <span wire:loading.remove wire:target="processPrGeneration(false)" class="material-symbols-outlined text-[16px]">save</span> Save Draft
                        </button>
                        <button wire:click="processPrGeneration(true)" wire:loading.attr="disabled" class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md disabled:opacity-60">
                            <span wire:loading wire:target="processPrGeneration(true)" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span wire:loading.remove wire:target="processPrGeneration(true)" class="material-symbols-outlined text-[18px]">send</span> Submit to GSU Triage
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
