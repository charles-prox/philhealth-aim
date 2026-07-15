<?php

use App\Models\AppHeader;
use App\Models\AppLineItem;
use App\Models\Employee;
use App\Models\PrItem;
use App\Models\ProcurementFolder;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination, WithFileUploads;

    public int $currentStep = 1;
    public ?string $folderId = null;

    public \App\Livewire\Forms\ProcurementForm $form;

    public $fileOthers = [];
    public array $stagedFiles = [];
    public array $stagedFileNames = [];

    // Search and Input State
    public string $search = '';
    public string $searchQuery = '';
    public ?int $selectedAppLineId = null;
    public array $basket = [];

    public ?string $successMessage = null;

    #[Computed]
    public function availableBudget(): float
    {
        if (!$this->selectedAppLineId) {
            return 0.0;
        }
        $appLineItem = AppLineItem::find($this->selectedAppLineId);
        if (!$appLineItem) {
            return 0.0;
        }
        $available = $appLineItem->approved_budget - $appLineItem->utilized_budget;
        if ($this->folderId) {
            $existingCost = PrItem::where('folder_id', $this->folderId)
                ->where('app_line_item_id', $this->selectedAppLineId)
                ->sum('estimated_total_cost');
            $available += $existingCost;
        }
        return (float) $available;
    }

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

        if ($folderId) {
            $folder = ProcurementFolder::findOrFail($folderId);
            if ($folder->status === 'CANCELLED' || $folder->status === 'CANCELLED_BY_USER') {
                abort(403, 'Access Denied: This Purchase Request has been permanently archived and cannot be modified.');
            }
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
        $this->stagedFiles = [];
        $this->stagedFileNames = [];
        $this->selectedAppLineId = null;
        $this->form->purpose = '';
        $this->form->recommendedById = null;
        $this->form->approvedById = null;
        $this->currentStep = 1;
        $this->form->trackingNumber = $this->generateTrackingNumber();
        $this->form->procurementCategory = '';
        $this->form->isTiedToEvent = false;
        $this->form->eventDate = null;

        if ($this->folderId) {
            $folder = ProcurementFolder::findOrFail($this->folderId);
            $this->form->trackingNumber = $folder->tracking_number;
            $this->form->purpose = $folder->overall_purpose;
            $this->form->recommendedById = $folder->recommended_by_id;
            $this->form->approvedById = $folder->approved_by_id;
            $this->form->procurementCategory = $folder->procurement_category ?? '';
            $this->form->isTiedToEvent = $folder->event_date !== null;
            $this->form->eventDate = $folder->event_date ? $folder->event_date->format('Y-m-d') : null;

            // Restore basket from pr_items
            foreach ($folder->prItems as $item) {
                if ($item->app_line_item_id) {
                    $this->selectedAppLineId = $item->app_line_item_id;
                    $uniqueKey = 'item_' . uniqid() . '_' . $item->id;
                    $this->basket[$uniqueKey] = [
                        'app_line_item_id' => $item->app_line_item_id,
                        'project_title' => $item->appLineItem?->project_title ?? 'Item',
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

    public function updatedFormIsTiedToEvent($value): void
    {
        if (!$value) {
            $this->form->eventDate = null;
        }
    }

    public function updatedFileOthers(): void
    {
        if (empty($this->fileOthers)) {
            return;
        }

        $newFiles = is_array($this->fileOthers) ? $this->fileOthers : [$this->fileOthers];

        foreach ($newFiles as $file) {
            if (count($this->stagedFiles) >= 5) {
                $this->addError('fileOthers', 'You can upload a maximum of 5 supporting files.');
                break;
            }
            $this->stagedFiles[] = $file;
            $this->stagedFileNames[] = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        }

        // Clear the upload slot so it is ready for the next drop/click
        $this->fileOthers = [];
    }

    public function removeStagedFile(int $index): void
    {
        if (isset($this->stagedFiles[$index])) {
            array_splice($this->stagedFiles, $index, 1);
            array_splice($this->stagedFileNames, $index, 1);
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

    public function updatedBasket($value, $key = null): void
    {
        if (is_null($key)) {
            return;
        }
        // Key format: "basketKey.field" (e.g., "item_uuid.qty")
        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $basketKey = $parts[0];
            $field = $parts[1];

            if ($field === 'qty' || $field === 'unit_cost') {
                $qty = (int) ($this->basket[$basketKey]['qty'] ?? 1);
                $unitCost = (float) ($this->basket[$basketKey]['unit_cost'] ?? 0.0);
                $this->basket[$basketKey]['total_cost'] = $qty * $unitCost;
                
                $this->autoSelectSignatories();
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
            $this->autoSelectSignatories();
            $this->currentStep = 2;
        } elseif ($this->currentStep === 2) {
            // Narrow containment validation — prevents forged IDs from bypassing the dropdown
            $validRecommenderIds = $this->validRecommenders->keys()->implode(',');
            $validApproverIds    = $this->validApprovers->keys()->implode(',');

            $this->form->validateStepTwo($validRecommenderIds, $validApproverIds, $this->folderId);

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

                if ($unitCost < 0.0) {
                    $this->addError("basket.{$basketKey}.unit_cost", "Estimated unit cost cannot be negative.");
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

    public function processPrGeneration(\App\Services\ProcurementService $service): void
    {
        // Re-validate with the narrow containment check at final submission too
        $validRecommenderIds = $this->validRecommenders->keys()->implode(',');
        $validApproverIds    = $this->validApprovers->keys()->implode(',');

        if (count($this->stagedFiles) > 5) {
            $this->addError('fileOthers', 'You can upload a maximum of 5 supporting files.');
            return;
        }

        $this->form->validateFinal($validRecommenderIds, $validApproverIds, $this->folderId);

        $this->validate([
            'stagedFiles.*'     => 'nullable|file|mimes:pdf,docx,xlsx,png,jpg|max:10240',
            'stagedFileNames.*' => 'required|string|min:3|max:150',
        ], [
            'stagedFiles.*.mimes' => 'The supporting attachments must be valid documents (PDF, DOCX, XLSX, PNG, JPG).',
            'stagedFiles.*.max'   => 'Supporting attachments must not exceed 10MB in size.',
            'stagedFileNames.*.required' => 'Each uploaded document must have a descriptive name.',
            'stagedFileNames.*.min'      => 'Document name must be at least 3 characters.',
        ]);

        if ($this->folder && $this->folder->status === 'RETURNED_FOR_COMPLIANCE' && !$this->folder->attachments()->where('attachment_type', 'USER_OTHER')->exists() && empty($this->stagedFiles)) {
            $this->addError('fileOthers', 'Operational Rule: You must upload at least one corrected PDF attachment to achieve compliance.');
            return;
        }

        if (empty($this->basket)) {
            $this->addError('form.trackingNumber', 'Your selection is empty. Please select at least one APP line item.');
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

            if ($unitCost < 0.0) {
                $this->addError("basket.{$basketKey}.unit_cost", "Estimated unit cost cannot be negative.");
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

        // Compile and save the PR using the Service
        $folder = $service->compileAndSavePr(
            $this->basket,
            $this->stagedFiles,
            $this->stagedFileNames,
            $this->form->all(),
            $this->folderId
        );

        $this->basket = [];
        
        session()->flash('status', 'PR compiled successfully! Please review and sign the documents before submitting.');
        $this->redirectRoute('procurement.review', ['folderId' => $folder->id], navigate: true);
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

    public function autoSelectSignatories(): void
    {
        $totalCost = $this->totalBasketValue();

        $this->form->recommendedById = \App\Services\SignatoryService::getRecommendedSignatory($totalCost, auth()->user()->office);
        $this->form->approvedById = \App\Services\SignatoryService::getApprovedSignatory($totalCost);
    }

    /**
     * Dynamic Active Recommender Pool
     * Checks creator's office hierarchy tier and returns only active authorized recommenders.
     */
    #[Computed]
    public function validRecommenders(): \Illuminate\Support\Collection
    {
        $recommenderIds = \App\Services\SignatoryService::getValidRecommenderIds($this->totalBasketValue(), auth()->user()->office);

        if (empty($recommenderIds)) {
            return collect();
        }

        return Employee::whereIn('id', $recommenderIds)
            ->orderBy('fullname')
            ->get()
            ->mapWithKeys(fn($emp) => [$emp->id => "{$emp->fullname} — {$emp->designation}"]);
    }

    /**
     * Dynamic Active Approver Pool
     * Returns only the active MSD Head and RVP who are authorized to approve.
     */
    #[Computed]
    public function validApprovers(): \Illuminate\Support\Collection
    {
        $approverIds = \App\Services\SignatoryService::getValidApproverIds($this->totalBasketValue());

        if (empty($approverIds)) {
            return collect();
        }

        return Employee::whereIn('id', $approverIds)
            ->orderBy('fullname')
            ->get()
            ->mapWithKeys(fn($emp) => [$emp->id => "{$emp->fullname} — {$emp->designation}"]);
    }

    #[Computed]
    public function recentItemHistory(): \Illuminate\Support\Collection
    {
        if (!$this->selectedAppLineId) {
            return collect();
        }

        return ProcurementFolder::recentHistoryForAppLine($this->selectedAppLineId, auth()->user()->office_id)
            ->get()
            ->groupBy('tracking_number');
    }

    #[Computed]
    public function folder(): ?ProcurementFolder
    {
        return $this->folderId ? ProcurementFolder::with('logs.actor')->find($this->folderId) : null;
    }

    #[Computed]
    public function totalBasketValue(): float
    {
        return collect($this->basket)->sum('total_cost');
    }
}; ?>

<div>
    @php
        $inputsDisabled = $this->folder && in_array($this->folder->status, ['RETURNED_FOR_COMPLIANCE', 'REJECTED']);
        $entirelyLocked = $this->folder && $this->folder->status === 'REJECTED';
    @endphp

    @include('livewire.procurement.partials.returned-alert')

    <!-- Sticky Wizard Header Wrapper -->
    <div class="sticky top-0 z-30 bg-[#f1f3f6] -mt-6 pt-6 pb-3 space-y-4">
        @include('livewire.procurement.partials.wizard-step-indicator')

        @if($currentStep === 1)
            @include('livewire.procurement.partials.wizard-step-one-header')
        @endif
    </div>

    @if($currentStep === 1)
        @include('livewire.procurement.partials.wizard-step-one')
    @elseif($currentStep === 2)
        @include('livewire.procurement.partials.wizard-step-two')
    @elseif($currentStep === 3)
        @include('livewire.procurement.partials.wizard-step-three')
    @endif
</div>
