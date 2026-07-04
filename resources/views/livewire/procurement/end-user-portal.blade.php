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

    // Wizard Form Fields
    public string $trackingNumber = '';
    public string $purpose = '';
    public ?int $recommendedById = null;
    public ?int $approvedById = null;
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
            // Narrow containment validation — prevents forged IDs from bypassing the dropdown
            $validRecommenderIds = $this->validRecommenders->keys()->implode(',');
            $validApproverIds    = $this->validApprovers->keys()->implode(',');

            $this->validate([
                'trackingNumber'   => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
                'purpose'          => 'required|string|max:1000',
                'recommendedById'  => 'required|integer|in:' . $validRecommenderIds,
                'approvedById'     => 'required|integer|in:' . $validApproverIds,
            ], [
                'recommendedById.in' => 'The selected recommending officer is not an authorized signatory for this PR.',
                'approvedById.in'    => 'The selected approving officer is not an authorized signatory for this PR.',
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

    public function processPrGeneration(bool $submitToGsu = false): void
    {
        // Re-validate with the narrow containment check at final submission too
        $validRecommenderIds = $this->validRecommenders->keys()->implode(',');
        $validApproverIds    = $this->validApprovers->keys()->implode(',');

        if (count($this->stagedFiles) > 5) {
            $this->addError('fileOthers', 'You can upload a maximum of 5 supporting files.');
            return;
        }

        $this->validate([
            'trackingNumber'      => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($this->folderId ?? 'NULL') . ',id',
            'purpose'             => 'required|string|max:1000',
            'recommendedById'     => 'required|integer|in:' . $validRecommenderIds,
            'approvedById'        => 'required|integer|in:' . $validApproverIds,
            'stagedFiles.*'       => 'nullable|file|mimes:pdf,docx,xlsx,png,jpg|max:10240',
            'stagedFileNames.*'   => 'required|string|min:3|max:150',
        ], [
            'recommendedById.in'  => 'The selected recommending officer is not an authorized signatory for this PR.',
            'approvedById.in'     => 'The selected approving officer is not an authorized signatory for this PR.',
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

        $requestedEmployee = auth()->user()->employee;
        $requestedById = $requestedEmployee?->id;
        $requestedByDesignation = $requestedEmployee?->designation ?? 'Requesting Officer';

        $recommendedEmployee = Employee::findOrFail((int) $this->recommendedById);
        $approvedEmployee    = Employee::findOrFail((int) $this->approvedById);

        $status = $submitToGsu ? 'SUBMITTED_TO_GSU' : 'DRAFT';
        if ($this->folderId) {
            $existingFolder = ProcurementFolder::find($this->folderId);
            if ($existingFolder && in_array($existingFolder->status, ['RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE'])) {
                $status = $submitToGsu ? 'ROUTING' : 'DRAFT';
            }
        }

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
                    'requested_signed_at'          => $submitToGsu ? now() : null,
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
                $folder = ProcurementFolder::create([
                    'tracking_number'              => $this->trackingNumber,
                    'project_title'                => 'PR compiled from APP on ' . now()->format('Y-m-d H:i'),
                    'procurement_method'           => 'Shopping',
                    'overall_purpose'              => $this->purpose,
                    'status'                       => $status,
                    'requested_signed_at'          => $submitToGsu ? now() : null,
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

            if (!empty($this->stagedFiles)) {
                $folderName = preg_replace('/[^A-Za-z0-9\-]/', '_', $folder->tracking_number);
                $employeeId = auth()->user()->employee_id ?? 1;

                \Illuminate\Support\Facades\Storage::disk('secure_procurement')->makeDirectory("{$folderName}/uploaded");

                foreach ($this->stagedFiles as $index => $extraFile) {
                    $fileName = "SUPPORTING_" . ($index + 1) . "_" . time() . "." . $extraFile->getClientOriginalExtension();
                    
                    // Stream the user data straight to the private uploaded directory channel
                    $storedPath = $extraFile->storeAs(
                        "{$folderName}/uploaded", 
                        $fileName, 
                        'secure_procurement'
                    );

                    $customName = trim($this->stagedFileNames[$index]);
                    $extension = $extraFile->getClientOriginalExtension();
                    if (!str_ends_with(strtolower($customName), '.' . strtolower($extension))) {
                        $customName .= '.' . $extension;
                    }

                    // Catalog file metadata
                    $folder->attachments()->create([
                        'attachment_type' => 'USER_OTHER',
                        'file_path' => $storedPath,
                        'original_name' => $customName,
                        'mime_type' => $extraFile->getMimeType(),
                        'file_size' => $extraFile->getSize(),
                        'uploaded_by_employee_id' => $employeeId
                    ]);
                }
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
            $logAction = $status === 'SUBMITTED_TO_GSU' ? 'SUBMITTED' : ($status === 'ROUTING' ? 'RESUBMITTED' : 'CREATED');
            $logRemarks = $status === 'SUBMITTED_TO_GSU' 
                ? 'PR submitted to GSU Triage Box. The physical copies of the documents mentioned in the Cover Letter are enroute to the GSU Procurement Officer for triage and verification.' 
                : ($status === 'ROUTING' ? 'PR resubmitted to GSU Triage Box with corrections. The corrected physical copies of the documents listed in the Cover Letter are enroute to the GSU Procurement Officer.' : 'PR draft compiled and successfully saved to the office registry.');

            \App\Models\ProcurementLog::create([
                'procurement_folder_id' => $folder->id,
                'action' => $logAction,
                'actor_id' => $requestedById,
                'remarks' => $logRemarks,
            ]);

            return $folder;
        });

        \App\Jobs\GenerateProcurementDocumentsJob::dispatchSync($folder);

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

    /**
     * Recommender pool: GSU Head + the Division Chief for the requesting user's office.
     * Returns an ordered id => "Name — Designation" map for the x-form-select component.
     *
     * Pulls ALL employees assigned to those slots (primary + both OICs) so the user
     * can always select the specific individual physically handling recommending duties,
     * regardless of which OIC position is currently set as active_holder.
     */
    /**
     * Dynamic Active Recommender Pool
     * Checks creator's office hierarchy tier and returns only active authorized recommenders.
     */
    #[Computed]
    public function validRecommenders(): \Illuminate\Support\Collection
    {
        $userOffice = auth()->user()->office;
        if (!$userOffice) {
            return collect();
        }

        $recommenderIds = [];

        // 1. Fetch the Active GSU Unit Head (central recommending authority)
        $gsuOffice = \App\Models\Office::where('acronym', 'GSU')->first();
        if ($gsuOffice) {
            $gsuSigner = \App\Models\SignatoryRegistry::getActiveSignatoryFor('UNIT_HEAD', $gsuOffice->id);
            if ($gsuSigner) {
                $recommenderIds[] = $gsuSigner;
            }
        }

        // 2. Fetch local boss depending on organizational type (Division, Section, or Unit)
        if ($userOffice->type === 'UNIT') {
            // Unit members report to parent Section head or Division Chief
            $parent = $userOffice->parent;
            if ($parent && $parent->type === 'SECTION') {
                $sectionSigner = \App\Models\SignatoryRegistry::getActiveSignatoryFor('SECTION_HEAD', $parent->id);
                if ($sectionSigner) {
                    $recommenderIds[] = $sectionSigner;
                } else {
                    // Fallback to parent Division Chief if Section Head is not configured
                    $division = $parent->parent;
                    if ($division && $division->type === 'DIVISION') {
                        $positionSlug = $division->acronym === 'ORVP' ? 'RVP' : $division->acronym . '_CHIEF';
                        if ($division->acronym === 'MSD') {
                            $positionSlug = 'MSD_HEAD';
                        }
                        $divisionSigner = \App\Models\SignatoryRegistry::getActiveSignatoryFor($positionSlug);
                        if ($divisionSigner) {
                            $recommenderIds[] = $divisionSigner;
                        }
                    }
                }
            } elseif ($parent && $parent->type === 'DIVISION') {
                $positionSlug = $parent->acronym === 'ORVP' ? 'RVP' : $parent->acronym . '_CHIEF';
                if ($parent->acronym === 'MSD') {
                    $positionSlug = 'MSD_HEAD';
                }
                $divisionSigner = \App\Models\SignatoryRegistry::getActiveSignatoryFor($positionSlug);
                if ($divisionSigner) {
                    $recommenderIds[] = $divisionSigner;
                }
            }
        } elseif ($userOffice->type === 'SECTION' || $userOffice->type === 'DIVISION') {
            // Section or Division members report to parent Division Chief
            $userDivision = $userOffice->type === 'DIVISION' ? $userOffice : $userOffice->division;
            if ($userDivision) {
                $positionSlug = $userDivision->acronym === 'ORVP' ? 'RVP' : $userDivision->acronym . '_CHIEF';
                if ($userDivision->acronym === 'MSD') {
                    $positionSlug = 'MSD_HEAD';
                }

                $divisionSigner = \App\Models\SignatoryRegistry::getActiveSignatoryFor($positionSlug);
                if ($divisionSigner) {
                    $recommenderIds[] = $divisionSigner;
                }
            }
        }

        $recommenderIds = array_filter(array_unique($recommenderIds));

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
        $approverIds = [];

        $msdSigner = \App\Models\SignatoryRegistry::getActiveSignatoryFor('MSD_HEAD');
        if ($msdSigner) {
            $approverIds[] = $msdSigner;
        }

        $rvpSigner = \App\Models\SignatoryRegistry::getActiveSignatoryFor('RVP');
        if ($rvpSigner) {
            $approverIds[] = $rvpSigner;
        }

        $approverIds = array_filter(array_unique($approverIds));

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
        if (!$this->selectedAppLineId) return collect();

        $items = DB::table('procurement_folders')
            ->join('pr_items', 'procurement_folders.id', '=', 'pr_items.folder_id')
            ->leftJoin('app_line_items', 'pr_items.app_line_item_id', '=', 'app_line_items.id')
            ->where('pr_items.app_line_item_id', $this->selectedAppLineId)
            ->where('procurement_folders.office_id', auth()->user()->office_id)
            ->whereNotIn('procurement_folders.status', ['CANCELLED', 'CANCELLED_BY_USER'])
            ->select(
                'procurement_folders.id as folder_id',
                'procurement_folders.tracking_number',
                'procurement_folders.pr_number',
                'procurement_folders.status',
                'procurement_folders.overall_purpose',
                \Illuminate\Support\Facades\DB::raw("COALESCE(pr_items.item_description_override, app_line_items.description, 'Unknown Item') as item_desc"),
                'pr_items.total_qty as quantity',
                \Illuminate\Support\Facades\DB::raw('COALESCE(pr_items.estimated_unit_cost, pr_items.unit_cost, 0) as unit_price'),
                'procurement_folders.created_at'
            )
            ->latest('procurement_folders.created_at')
            ->get();

        return $items->groupBy('tracking_number');
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

    @if($this->folder && in_array($this->folder->status, ['RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE']))
        @php
            $latestLog = $this->folder->logs->first();
        @endphp
        @if($latestLog)
            <div class="mb-6 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200 shadow-sm animate-in fade-in slide-in-from-top duration-300">
                <span class="font-bold flex items-center gap-2 text-sm text-red-700">
                    <span class="material-symbols-outlined">assignment_return</span> 
                    Action Required: Document Returned by {{ $latestLog->actor?->fullname ?? 'Officer' }}
                </span>
                <p class="text-xs mt-2 bg-white p-3 rounded-lg border border-gray-200 font-mono text-[#1a1c1f]">
                    <strong>REJECTION TYPE:</strong> {{ str_replace('_', ' ', str_replace('DOCUMENT_REJECTION_', '', $latestLog->action)) }}<br>
                    <strong>REMARKS:</strong> "{{ $latestLog->remarks }}"
                </p>
            </div>
        @endif
    @endif

    <!-- Sticky Wizard Header Wrapper -->
    <div class="sticky top-0 z-30 bg-[#f1f3f6] -mt-6 pt-6 pb-3 space-y-4">


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
                            <p class="text-[9px] text-[#43474f]/60 leading-tight">Verify PR bundle</p>
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
        @endif

        {{-- Step 2 UI --}}
        @if($currentStep === 2)
            <div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm mt-8 mb-6 space-y-4"
                 x-data="{
                    basket: $wire.entangle('basket'),
                    availableBudget: {{ $this->availableBudget }},
                    formatPrice(val) {
                        return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);
                    },
                    get totalValue() {
                        return Object.values(this.basket || {}).reduce((sum, item) => {
                            if (!item) return sum;
                            return sum + (parseFloat(item.qty || 0) * parseFloat(item.unit_cost || 0));
                        }, 0);
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
                                    <x-form-select label="" placeholder="Select Recommending Officer..." icon="recommend" searchable wire:model="recommendedById" :options="$this->validRecommenders->toArray()" :disabled="$inputsDisabled" />
                                    @if($this->validRecommenders->isEmpty())
                                        <p class="text-[10px] text-amber-600 mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">warning</span> No authorized recommenders configured in the Signatory Registry. Contact your system administrator.</p>
                                    @endif
                                    @error('recommendedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Approved By <span class="text-[#ba1a1a]">*</span></label>
                                    <x-form-select label="" placeholder="Select Approving Officer..." icon="person_check" searchable wire:model="approvedById" :options="$this->validApprovers->toArray()" :disabled="$inputsDisabled" />
                                    @if($this->validApprovers->isEmpty())
                                        <p class="text-[10px] text-amber-600 mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">warning</span> No authorized approving officers configured in the Signatory Registry. Contact your system administrator.</p>
                                    @endif
                                    @error('approvedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Purpose / Justification <span class="text-[#ba1a1a]">*</span></label>
                                <textarea wire:model="purpose" placeholder="Provide the operational justification..." class="w-full px-4 py-3 bg-white border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all resize-none min-h-[100px]" {{ $inputsDisabled ? 'disabled' : '' }}></textarea>
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
                                                <div class="pt-4 first:pt-2 space-y-3" x-init="if (!basket['{{ $basketKey }}']) basket['{{ $basketKey }}'] = { description: '', unit: 'pcs', qty: 1, unit_cost: 0.00 }">
                                                    <div class="flex justify-between items-center border-b border-[#eeedf2]/60 pb-2">
                                                        <span class="text-[10px] font-bold text-[#001e40] uppercase tracking-wider">Item #{{ $loop->iteration }} Details</span>
                                                        @if(!$inputsDisabled)
                                                            <button type="button" wire:click="removeItemRow('{{ $basketKey }}')" class="px-3 py-1 bg-[#ba1a1a]/10 hover:bg-[#ba1a1a]/20 text-[#ba1a1a] border border-[#ba1a1a]/20 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1 active:scale-95">
                                                                <span class="material-symbols-outlined text-[14px]">delete</span> Remove Row
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                        <!-- Left Column: Particulars / Description (textarea) -->
                                                        <div>
                                                            <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Item Particulars <span class="text-[#ba1a1a]">*</span></label>
                                                            <textarea wire:model.blur="basket.{{ $basketKey }}.description" x-model="basket['{{ $basketKey }}']['description']" placeholder="Enter detailed item particulars/description..." rows="3" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40] resize-y" {{ $inputsDisabled ? 'disabled' : '' }}></textarea>
                                                            @error("basket.{$basketKey}.description")
                                                                <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                            @enderror
                                                        </div>
 
                                                        <!-- Right Column: Unit, Qty, Cost -->
                                                        <div class="space-y-3">
                                                            <div class="grid grid-cols-3 gap-2.5">
                                                                <div>
                                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Unit <span class="text-[#ba1a1a]">*</span></label>
                                                                    <input type="text" wire:model.blur="basket.{{ $basketKey }}.unit" x-model="basket['{{ $basketKey }}']['unit']" placeholder="pcs, box, ream" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" {{ $inputsDisabled ? 'disabled' : '' }}  />
                                                                    @error("basket.{$basketKey}.unit")
                                                                        <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                                    @enderror
                                                                </div>
 
                                                                <div>
                                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Quantity <span class="text-[#ba1a1a]">*</span></label>
                                                                    <input type="number" min="1" wire:model.blur="basket.{{ $basketKey }}.qty" x-model.number="basket['{{ $basketKey }}']['qty']" placeholder="1" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" {{ $inputsDisabled ? 'disabled' : '' }} />
                                                                    @error("basket.{$basketKey}.qty")
                                                                        <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                                    @enderror
                                                                </div>
 
                                                                <div>
                                                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Est. Unit Cost <span class="text-[#ba1a1a]">*</span></label>
                                                                    <div class="relative">
                                                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#43474f]">₱</span>
                                                                        <input type="number" step="0.01" min="0" wire:model.blur="basket.{{ $basketKey }}.unit_cost" x-model.number="basket['{{ $basketKey }}']['unit_cost']" placeholder="0.00" class="w-full pl-5 pr-2 py-2 bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" {{ $inputsDisabled ? 'disabled' : '' }} />
                                                                    </div>
                                                                    @error("basket.{$basketKey}.unit_cost")
                                                                        <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                                    @enderror
                                                                </div>
                                                            </div>
 
                                                            <div class="flex justify-between items-center text-[11px] font-bold text-[#43474f] pt-1">
                                                                <span>Subtotal</span>
                                                                <span class="text-[#001e40]" x-text="'₱' + formatPrice((basket['{{ $basketKey }}'] ? basket['{{ $basketKey }}']['qty'] : 0) * (basket['{{ $basketKey }}'] ? basket['{{ $basketKey }}']['unit_cost'] : 0))"></span>
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

                                        @if(!$inputsDisabled)
                                            <div class="flex justify-start border-t border-[#eeedf2] pt-3">
                                                <button type="button" wire:click="addItemRowToAppLine({{ $selectedAppLineId }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#f9f9fe] hover:bg-[#eeedf2] text-[#001e40] border border-[#c3c6d1] hover:border-[#001e40] text-[11px] font-bold rounded-lg shadow-2xs transition-all">
                                                    <span class="material-symbols-outlined text-[15px]">add</span> Add Item Row
                                                </button>
                                            </div>
                                        @endif
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
                                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider">PR Summary</h4>
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
                                        <span class="text-2xl font-bold block mt-1 transition-colors duration-200"
                                              :class="totalValue > availableBudget ? 'text-red-600' : 'text-green-700'"
                                              x-text="'₱' + formatPrice(totalValue)"></span>
                                    </div>

                                    <template x-if="totalValue > availableBudget">
                                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex gap-3 text-red-800 transition-all">
                                            <span class="material-symbols-outlined text-[20px] text-red-600 shrink-0 mt-0.5">warning</span>
                                            <div class="space-y-1">
                                                <h5 class="text-xs font-bold uppercase tracking-wider text-red-900">Budget Limit Exceeded</h5>
                                                <p class="text-[11px] leading-relaxed font-semibold">
                                                    Combined items cost (<span x-text="'₱' + formatPrice(totalValue)"></span>) under {{ $this->selectedAppLine?->project_title ?? 'Selected APP Line' }} exceeds available budget of <span x-text="'₱' + formatPrice(availableBudget)"></span>.
                                                </p>
                                            </div>
                                        </div>
                                    </template>
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
            <div class="bg-white border border-[#c3c6d1] rounded-2xl space-y-4 p-8 shadow-sm mt-8 mb-6">
                <div class="border-b border-[#eeedf2] pb-4 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-bold text-[#001e40]">Review Purchase Request</h3>
                        <p class="text-xs text-[#43474f] mt-1">Review the bundled items before final submission.</p>
                    </div>
                    <span class="px-3 py-1.5 bg-[#eeedf2] text-[#43474f] text-[10px] font-bold rounded-full uppercase tracking-wider">Unsubmitted Draft</span>
                </div>

                <div class="border-2 border-dashed border-[#c3c6d1] rounded-2xl p-6 bg-[#f9f9fe] space-y-5">
                    <div class="flex justify-between items-start">
                        <div class="space-y-1.5">
                            <p class="text-[10px] uppercase font-bold tracking-widest text-[#43474f]/50">PhilHealth AIM · Region X</p>
                            <h4 class="text-lg font-bold text-[#001e40]">PR Proposal</h4>
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

                {{-- Supporting Attachments Card --}}
                <div class="border-t border-[#eeedf2] pt-6 space-y-3">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f]">
                        Supporting Procurement Attachments
                        @if($this->folder && $this->folder->status === 'RETURNED_FOR_COMPLIANCE')
                            <span class="text-red-500 ml-0.5">*</span>
                        @endif
                    </label>
                    
                    @if(!$this->folder || $this->folder->status !== 'REJECTED')
                        <div x-data="{ isDragging: false }" 
                             @dragover.prevent="isDragging = true" 
                             @dragleave.prevent="isDragging = false" 
                             @drop.prevent="isDragging = false; $wire.uploadMultiple('fileOthers', $event.dataTransfer.files)"
                             class="border-2 border-dashed rounded-2xl p-6 transition-all duration-200 text-center cursor-pointer relative flex flex-col items-center justify-center min-h-[140px]"
                             :class="isDragging ? 'border-[#001e40] bg-[#001e40]/5' : 'border-[#c3c6d1] hover:border-[#001e40] bg-white/50 hover:bg-white'">
                             
                            <!-- Live Uploading Overlay State -->
                            <div wire:loading wire:target="fileOthers" class="absolute inset-0 bg-white/85 rounded-2xl flex flex-col items-center justify-center gap-2 z-20">
                                <div class="w-8 h-8 border-4 border-[#001e40] border-t-transparent rounded-full animate-spin"></div>
                                <p class="text-xs font-bold text-[#001e40]">Uploading attachments, please wait...</p>
                            </div>

                            <input type="file" 
                                   x-ref="fileInput" 
                                   wire:model="fileOthers" 
                                   multiple 
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"/>
                                   
                            <div class="flex flex-col items-center justify-center gap-2 pointer-events-none">
                                <span class="material-symbols-outlined text-[32px] transition-colors" 
                                      :class="isDragging ? 'text-[#001e40]' : 'text-[#43474f]/60'">
                                    cloud_upload
                                </span>
                                <div class="text-xs">
                                    <span class="font-bold text-[#001e40] underline">Click to upload</span> or drag and drop files here
                                </div>
                                <p class="text-[10px] text-[#43474f]/60">Supporting spec sheets, justifications, or compliance revisions (Max 5 files, 10MB per file).</p>
                            </div>
                        </div>
                        @error('fileOthers') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                        @error('fileOthers.*') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                    @endif

                    {{-- Staged Attachments (Ready to Save) --}}
                    @if(!empty($this->stagedFiles))
                        <div class="space-y-2 mt-4">
                            <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Staged Attachments (Ready to Save)</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($this->stagedFiles as $index => $file)
                                    <div class="p-4 bg-white border border-[#eeedf2] rounded-xl space-y-3 shadow-2xs">
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 truncate text-xs font-semibold text-[#001e40]">
                                                <span class="material-symbols-outlined text-[18px] text-[#001e40]/60">draft</span>
                                                <span class="truncate" title="{{ $file->getClientOriginalName() }}">{{ $file->getClientOriginalName() }}</span>
                                            </span>
                                            <button type="button" wire:click="removeStagedFile({{ $index }})" class="text-xs font-bold text-[#ba1a1a] hover:underline shrink-0">Remove</button>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Document Name <span class="text-red-600">*</span></label>
                                            <input type="text" 
                                                   wire:model="stagedFileNames.{{ $index }}" 
                                                   placeholder="e.g. Technical Specification, Canvass Sheet" 
                                                   class="w-full px-3 py-2 bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg text-xs outline-none focus:ring-2 focus:ring-[#001e40] transition-all"/>
                                            @error('stagedFileNames.' . $index)
                                                <p class="text-[10px] font-bold text-[#ba1a1a] mt-0.5">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Existing Saved Attachments --}}
                    @if($this->folder && $this->folder->attachments->isNotEmpty())
                        <div class="space-y-2 mt-4">
                            <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Existing Saved Attachments</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($this->folder->attachments as $attach)
                                    <div class="p-3 bg-[#e8f5e9] text-[#2e7d32] border border-[#a5d6a7]/30 rounded-xl flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-2 truncate pr-2">
                                            <span class="material-symbols-outlined text-[18px]">
                                                {{ str_starts_with($attach->attachment_type, 'SYSTEM_') ? 'auto_stories' : 'description' }}
                                            </span>
                                            <span class="truncate font-semibold">{{ $attach->original_name }}</span>
                                            <span class="text-[9px] px-1.5 py-0.5 bg-[#c8e6c9] text-[#1b5e20] rounded font-bold uppercase">{{ str_replace('SYSTEM_', '', $attach->attachment_type) }}</span>
                                        </span>
                                        <a href="{{ route('admin.file-stream', $attach->id) }}" target="_blank" class="font-bold underline hover:text-[#001e40] shrink-0">View</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
                    <button wire:click="prevStep" class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Details
                    </button>
                    @if(!$entirelyLocked)
                        <div class="flex items-center gap-3">
                            @if(!$inputsDisabled)
                                <button wire:click="processPrGeneration(false)" wire:loading.attr="disabled" class="px-5 py-2.5 text-xs font-bold border border-[#c3c6d1] text-[#43474f] rounded-xl hover:bg-gray-100 transition-all flex items-center gap-1.5">
                                    <span wire:loading wire:target="processPrGeneration(false)" class="w-3 h-3 border-2 border-gray-500 border-t-transparent rounded-full animate-spin"></span>
                                    <span wire:loading.remove wire:target="processPrGeneration(false)" class="material-symbols-outlined text-[16px]">save</span> Save Draft
                                </button>
                            @endif
                            <button wire:click="processPrGeneration(true)" wire:loading.attr="disabled" class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md disabled:opacity-60">
                                <span wire:loading wire:target="processPrGeneration(true)" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span wire:loading.remove wire:target="processPrGeneration(true)" class="material-symbols-outlined text-[18px]">send</span> 
                                <span>{{ ($this->folder && in_array($this->folder->status, ['RETURNED_FOR_EDIT', 'RETURNED_FOR_COMPLIANCE'])) ? 'Resubmit PR to Signatories' : 'Submit to GSU Triage' }}</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
</div>
