<?php

use App\Models\BudgetTransaction;
use App\Models\BudgetYear;
use App\Models\CobItem;
use App\Models\CobVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    // Active version context
    public string $activeVersionId = '';

    // Target
    public string $targetMode = 'existing'; // 'existing' | 'new'
    public string $targetItemId = '';

    // New item fields (when targetMode = 'new')
    public string $newPpaCode = '';
    public string $newPpaDesc = '';
    public string $newSubPpaCode = '';
    public string $newSubPpaDesc = '';
    public string $newFullParticulars = '';
    public string $newUnit = 'Lot';
    public string $newExpDesc = '';
    public string $newAccount = '';
    public string $newTier = '';
    public string $newClass = '';
    public string $newGass = '';
    public string $newSector = '';
    public string $newOfficeId = '';
    public string $newTransactionId = '';
    public string $newWorkAndFinancialPlanId = '';
    public bool   $newIsIct = false;

    // Sources: [['item_id' => '...', 'amount' => 0], ...]
    public array $sources = [];

    // Memo
    public string $referenceMemo = '';
    public string $remarks = '';
    public $memoAttachment = null;

    // UI
    public bool $showAddSource = false;
    public string $addSourceItemId = '';

    public function mount(): void
    {
        $activeVersion = CobVersion::whereHas('budgetYear', fn($q) => $q->where('status', 'OPEN'))
            ->where(fn($q) => $q->where('status', 'APPROVED')->orWhere('is_active', true))
            ->latest()
            ->first();

        if ($activeVersion) {
            $this->activeVersionId = $activeVersion->id;
        }
    }

    #[Computed]
    public function activeVersion(): ?CobVersion
    {
        if (!$this->activeVersionId) return null;
        return CobVersion::find($this->activeVersionId);
    }

    #[Computed]
    public function realignmentVersions(): \Illuminate\Support\Collection
    {
        return CobVersion::whereHas('budgetYear', fn($q) => $q->where('status', 'OPEN'))
            ->where('version_name', 'like', '%Realignment%')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function availableItems(): array
    {
        if (!$this->activeVersionId) return [];
        return CobItem::where('version_id', $this->activeVersionId)
            ->orderBy('ppa_code')
            ->get()
            ->mapWithKeys(fn($i) => [
                $i->id => '[' . $i->ppa_code . '] ' . \Illuminate\Support\Str::limit($i->full_particulars ?? $i->ppa_desc, 60)
            ])
            ->toArray();
    }

    #[Computed]
    public function availableSources(): \Illuminate\Support\Collection
    {
        if (!$this->activeVersionId) return collect();
        $selectedIds = array_column($this->sources, 'item_id');
        $targetId = $this->targetMode === 'existing' ? $this->targetItemId : null;

        return CobItem::where('version_id', $this->activeVersionId)
            ->where('current_balance', '>', 0)
            ->whereNotIn('id', array_filter(array_merge($selectedIds, [$targetId])))
            ->orderBy('ppa_code')
            ->get();
    }

    #[Computed]
    public function totalReductions(): float
    {
        return array_reduce($this->sources, fn($carry, $s) => $carry + (float)($s['amount'] ?? 0), 0.0);
    }

    #[Computed]
    public function targetItem(): ?CobItem
    {
        if ($this->targetMode !== 'existing' || !$this->targetItemId) return null;
        return CobItem::find($this->targetItemId);
    }

    public function addSource(): void
    {
        if (!$this->addSourceItemId) return;
        $this->sources[] = ['item_id' => $this->addSourceItemId, 'amount' => 0];
        $this->addSourceItemId = '';
        $this->showAddSource = false;
    }

    public function removeSource(int $index): void
    {
        array_splice($this->sources, $index, 1);
        $this->sources = array_values($this->sources);
    }

    public function processRealignment(): void
    {
        $this->validate([
            'activeVersionId'  => 'required|exists:cob_versions,id',
            'referenceMemo'    => 'required|string|max:255',
            'sources'          => 'required|array|min:1',
            'sources.*.item_id'=> 'required|exists:cob_items,id',
            'sources.*.amount' => 'required|numeric|min:0.01',
            'memoAttachment'   => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($this->targetMode === 'existing') {
            $this->validate(['targetItemId' => 'required|exists:cob_items,id']);
        } else {
            $this->validate([
                'newPpaCode'        => 'required|string|max:100',
                'newPpaDesc'        => 'required|string|max:500',
                'newFullParticulars'=> 'required|string|max:500',
                'newAccount'        => 'nullable|string|max:100',
            ]);
        }

        // Validate each source does not exceed available balance
        foreach ($this->sources as $s) {
            $item = CobItem::find($s['item_id']);
            if ((float)$s['amount'] > (float)$item->current_balance) {
                $this->addError('sources', "Reduction of ₱" . number_format($s['amount'], 2) . " for [{$item->ppa_code}] exceeds its available balance of ₱" . number_format($item->current_balance, 2) . ".");
                return;
            }
        }

        $totalReductions = $this->totalReductions;
        if ($totalReductions <= 0) {
            $this->addError('sources', 'Total reductions must be greater than zero.');
            return;
        }

        DB::transaction(function () use ($totalReductions) {
            $oldVersion = CobVersion::with('cobItems')->findOrFail($this->activeVersionId);

            // 1. Create a new DRAFT revision
            $newVersion = CobVersion::create([
                'budget_year_id' => $oldVersion->budget_year_id,
                'version_name'   => $oldVersion->version_name . ' - Realignment ' . now()->format('Y-m-d'),
                'is_active'      => false,
                'status'         => 'DRAFT',
                'remarks'        => "Realignment: {$this->referenceMemo}. {$this->remarks}",
                'created_by'     => auth()->id() ?? 1,
            ]);

            // 2. Clone all items — build a map old_id => new_item
            $idMap = [];
            foreach ($oldVersion->cobItems as $oldItem) {
                $newItem = CobItem::create(array_merge($oldItem->only([
                    'recom_amount','encumbered_amount','actual_spent','current_balance',
                    'ppa_code','ppa_desc','sub_ppa_code','sub_ppa_desc','exp_desc',
                    'is_ict','base','account','tier','class','gass',
                    'transaction_id','work_and_financial_plan_id','office_id','sector',
                    'full_particulars','particulars1','particulars2','unit','recom_qty',
                ]), [
                    'version_id'       => $newVersion->id,
                    'superseded_by_id' => null,
                    'status'           => 'DRAFT',
                    'is_active'        => false,
                ]));
                $idMap[$oldItem->id] = $newItem;
            }

            // 3. Handle memo upload
            $memoPath = null;
            if ($this->memoAttachment) {
                $safeMemo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->referenceMemo);
                $filename = "realignment_{$safeMemo}_" . now()->timestamp . ".pdf";
                $memoPath = $this->memoAttachment->storeAs('memos', $filename, 'public');
            }

            // 4. Determine target clone
            $targetClone = null;
            if ($this->targetMode === 'existing' && isset($idMap[$this->targetItemId])) {
                $targetClone = $idMap[$this->targetItemId];
            } else {
                // Create brand-new item in the draft version
                $targetClone = CobItem::create([
                    'version_id'               => $newVersion->id,
                    'recom_amount'             => $totalReductions,
                    'encumbered_amount'        => 0,
                    'actual_spent'             => 0,
                    'current_balance'          => $totalReductions,
                    'ppa_code'                 => $this->newPpaCode,
                    'ppa_desc'                 => $this->newPpaDesc,
                    'sub_ppa_code'             => $this->newSubPpaCode,
                    'sub_ppa_desc'             => $this->newSubPpaDesc,
                    'full_particulars'         => $this->newFullParticulars,
                    'exp_desc'                 => $this->newExpDesc,
                    'account'                  => $this->newAccount,
                    'tier'                     => $this->newTier,
                    'class'                    => $this->newClass,
                    'gass'                     => $this->newGass,
                    'sector'                   => $this->newSector,
                    'office_id'                => $this->newOfficeId,
                    'transaction_id'           => $this->newTransactionId ?: 'REALIGNMENT-' . strtoupper(substr($newVersion->id, 0, 8)),
                    'work_and_financial_plan_id' => $this->newWorkAndFinancialPlanId,
                    'is_ict'                   => $this->newIsIct,
                    'unit'                     => $this->newUnit,
                    'status'                   => 'DRAFT',
                    'is_active'                => false,
                ]);
            }

            // 5. Adjust source items and create transaction records
            foreach ($this->sources as $s) {
                $sourceClone = $idMap[$s['item_id']] ?? null;
                if (!$sourceClone) continue;

                $reduction = (float)$s['amount'];

                $sourceClone->update([
                    'recom_amount'    => max(0, $sourceClone->recom_amount - $reduction),
                    'current_balance' => max(0, $sourceClone->current_balance - $reduction),
                ]);

                BudgetTransaction::create([
                    'type'             => 'REALIGNMENT',
                    'version_id'       => $newVersion->id,
                    'source_item_id'   => $sourceClone->id,
                    'target_item_id'   => $targetClone->id,
                    'amount'           => $reduction,
                    'reference_memo'   => $this->referenceMemo,
                    'memo_attachment'  => $memoPath,
                    'remarks'          => $this->remarks,
                    'created_by'       => auth()->id() ?? 1,
                ]);
            }

            // 6. Update target if existing (add the total realignment)
            if ($this->targetMode === 'existing') {
                $targetClone->update([
                    'recom_amount'    => $targetClone->recom_amount + $totalReductions,
                    'current_balance' => $targetClone->current_balance + $totalReductions,
                ]);
            }

            session()->flash('cob_status', "Realignment draft created! Review the version below and approve it when ready.");
            $this->reset(['targetItemId', 'targetMode', 'sources', 'referenceMemo', 'remarks', 'memoAttachment',
                'newPpaCode', 'newPpaDesc', 'newSubPpaCode', 'newSubPpaDesc', 'newFullParticulars',
                'newExpDesc', 'newAccount', 'newTier', 'newClass', 'newGass', 'newSector',
                'newOfficeId', 'newTransactionId', 'newWorkAndFinancialPlanId', 'newIsIct']);
            $this->targetMode = 'existing';
        });
    }

    public function activateRealignment(string $versionId): void
    {
        $newVersion = CobVersion::findOrFail($versionId);

        if ($newVersion->status !== 'DRAFT') {
            session()->flash('cob_error', 'Only DRAFT versions can be approved.');
            return;
        }

        DB::transaction(function () use ($newVersion) {
            // Supersede the currently active version
            CobVersion::where('budget_year_id', $newVersion->budget_year_id)
                ->where(fn($q) => $q->where('status', 'APPROVED')->orWhere('is_active', true))
                ->update(['status' => 'SUPERSEDED', 'is_active' => false]);

            $newVersion->update(['status' => 'APPROVED', 'is_active' => true]);

            // Update active year allocation
            $allocation = $newVersion->cobItems()->sum('recom_amount');
            $newVersion->budgetYear()->update(['total_allocation' => $allocation]);
        });

        session()->flash('cob_status', "Realignment '{$newVersion->version_name}' is now APPROVED and active.");
    }

    public function deleteRealignment(string $versionId): void
    {
        $version = CobVersion::findOrFail($versionId);

        if ($version->status !== 'DRAFT') {
            session()->flash('cob_error', 'Only DRAFT versions can be deleted.');
            return;
        }

        DB::transaction(function () use ($version) {
            // Nullify lineage pointers before deletion
            CobItem::where('superseded_by_id', $version->cobItems()->pluck('id'))->update(['superseded_by_id' => null]);
            $version->cobItems()->delete();
            $version->delete();
        });

        session()->flash('cob_status', 'Draft realignment version deleted.');
    }
}; ?>

<div class="p-gutter max-w-7xl mx-auto">
    @include('livewire.cob.realignment-wizard-template')
</div>
