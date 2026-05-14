<?php

use App\Models\BudgetYear;
use App\Models\CobVersion;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // Budget Year creation
    public string $newYear = '';
    public bool $showYearForm = false;

    // COB Version creation
    public string $selectedYearId = '';
    public string $versionName = 'Original';
    public bool $showVersionForm = false;

    // Upload
    public $wfpFile = null;
    public string $uploadingVersionId = '';
    public bool $uploading = false;
    public bool $showUploadModal = false;
    public bool $showResultModal = false;
    public bool $showRevisionModal = false;
    public string $revisionRemarks = '';
    public string $revisionVersionId = '';
    public int $importCount = 0;
    public string $importError = '';

    public function mount(): void
    {
        $active = BudgetYear::where('status', 'OPEN')->first();
        if ($active) {
            $this->selectedYearId = (string) $active->id;
        }
    }

    public function createBudgetYear(): void
    {
        $this->validate([
            'newYear' => 'required|digits:4|integer|min:2020|max:2099|unique:budget_years,fiscal_year',
        ], ['newYear.unique' => 'This budget year already exists.']);

        BudgetYear::create([
            'fiscal_year'      => (int) $this->newYear,
            'status'           => 'OPEN',
            'total_allocation' => 0,
        ]);

        $this->reset(['newYear', 'showYearForm']);
        session()->flash('cob_status', "Budget Year FY {$this->newYear} created successfully.");
    }

    public function activateYear(string $yearId): void
    {
        BudgetYear::query()->update(['status' => 'LOCKED']);
        BudgetYear::findOrFail($yearId)->update(['status' => 'OPEN']);
        session()->flash('cob_status', 'Budget year activated. GSU personnel can now create Purchase Requests.');
    }

    public function prepareVersion(string $yearId): void
    {
        $this->selectedYearId = $yearId;
        $this->showVersionForm = true;
    }

    public function openUploadModal(string $versionId): void
    {
        $this->uploadingVersionId = $versionId;
        $this->showUploadModal = true;
    }

    public function uploadWfp(\App\Services\CobImporterService $importer): void
    {
        $this->validate([
            'wfpFile' => 'required|mimes:xlsx,xls,csv,txt|max:20480', // 20MB Max
        ]);

        $this->uploading = true;

        try {
            $path = $this->wfpFile->store('cob_uploads');
            $this->importCount = $importer->import(storage_path('app/private/' . $path), $this->uploadingVersionId);
            
            $this->reset(['wfpFile', 'uploadingVersionId', 'uploading', 'showUploadModal']);
            $this->showResultModal = true;
            $this->importError = '';
            session()->flash('cob_status', "WFP Excel successfully processed. {$this->importCount} budget lines have been mapped.");
        } catch (\Exception $e) {
            $this->uploading = false;
            $this->importError = $e->getMessage();
            $this->importCount = 0;
            $this->showResultModal = true;
        }
    }

    public function createVersion(): void
    {
        $this->validate([
            'selectedYearId' => 'required|exists:budget_years,id',
            'versionName'    => 'required|string|max:100',
        ]);

        CobVersion::create([
            'budget_year_id' => $this->selectedYearId,
            'version_name'   => $this->versionName,
            'is_active'      => false,
            'created_by'     => auth()->id() ?? 1, // fallback if not logged in
        ]);

        $this->reset(['versionName', 'showVersionForm']);
        session()->flash('cob_status', "COB Version '{$this->versionName}' created and ready for upload.");
    }

    public function activateVersion(string $versionId): void
    {
        $version = CobVersion::findOrFail($versionId);

        // 1. Find the currently active version(s)
        $activeVersions = CobVersion::where('budget_year_id', $version->budget_year_id)
            ->where('is_active', true)
            ->get();
            
        foreach ($activeVersions as $activeVer) {
            // Mark old version as superseded
            $activeVer->update(['is_active' => false, 'status' => 'SUPERSEDED']);
            
            // Mark items as superseded
            \App\Models\CobItem::where('version_id', $activeVer->id)
                ->update(['is_active' => false, 'status' => 'SUPERSEDED']);

            // "Best Effort" Lineage Matching: 
            // If the user uploaded a new Excel instead of cloning, we try to match items
            // so auditors can still see the trail.
            $oldItems = \App\Models\CobItem::where('version_id', $activeVer->id)
                ->whereNull('superseded_by_id')
                ->get();

            // Track which new items are already matched to avoid duplicate linking
            $matchedNewItemIds = [];

            foreach ($oldItems as $oldItem) {
                // Compound Fingerprint Matching (Strongest)
                // We combine TransactionID with PPA and Particulars to ensure we hit the right row
                // even if TransactionIDs are repeated for a group of items.
                $match = \App\Models\CobItem::where('version_id', $version->id)
                    ->whereNotIn('id', $matchedNewItemIds)
                    ->where(function($q) use ($oldItem) {
                        if (!empty($oldItem->transaction_id)) {
                            $q->where('transaction_id', $oldItem->transaction_id);
                        }
                        $q->where('ppa_code', $oldItem->ppa_code)
                          ->where('ppa_desc', $oldItem->ppa_desc)
                          ->where('full_particulars', $oldItem->full_particulars);
                    })
                    ->first();

                // Fallback: If no perfect compound match, try TransactionID + Particulars1/2
                if (!$match && !empty($oldItem->transaction_id)) {
                    $match = \App\Models\CobItem::where('version_id', $version->id)
                        ->whereNotIn('id', $matchedNewItemIds)
                        ->where('transaction_id', $oldItem->transaction_id)
                        ->where('particulars1', $oldItem->particulars1)
                        ->where('particulars2', $oldItem->particulars2)
                        ->first();
                }

                // Last Resort: TransactionID only (only if we still haven't found it)
                if (!$match && !empty($oldItem->transaction_id)) {
                    $match = \App\Models\CobItem::where('version_id', $version->id)
                        ->whereNotIn('id', $matchedNewItemIds)
                        ->where('transaction_id', $oldItem->transaction_id)
                        ->first();
                }

                if ($match) {
                    $oldItem->update(['superseded_by_id' => $match->id]);
                    $matchedNewItemIds[] = $match->id;
                }
            }
        }

        // 2. Activate the new version
        $version->update([
            'is_active' => true,
            'status' => 'APPROVED',
        ]);
        
        \App\Models\CobItem::where('version_id', $version->id)
            ->update(['is_active' => true, 'status' => 'APPROVED']);

        session()->flash('cob_status', "Version '{$version->version_name}' is now APPROVED. Budget lines are unlocked for GSU.");
    }

    public function createRevision(string $versionId): void
    {
        $this->revisionVersionId = $versionId;
        $this->revisionRemarks = '';
        $this->showRevisionModal = true;
    }

    public function confirmRevision(): void
    {
        $oldVersion = CobVersion::with('cobItems')->findOrFail($this->revisionVersionId);
        
        if ($oldVersion->status !== 'APPROVED') {
            session()->flash('cob_error', 'Only APPROVED versions can be revised.');
            return;
        }

        // We no longer archive the old version here. 
        // It stays active until the new DRAFT is approved.
        
        // 1. Create new Version container
        $revisionCount = CobVersion::where('budget_year_id', $oldVersion->budget_year_id)
            ->where('version_name', 'like', $oldVersion->version_name . ' - Revision %')
            ->count() + 1;
            
        $newName = $oldVersion->version_name . (str_contains($oldVersion->version_name, ' - Revision') ? '' : " - Revision {$revisionCount}");

        $newVersion = CobVersion::create([
            'budget_year_id' => $oldVersion->budget_year_id,
            'version_name'   => $newName,
            'is_active'      => false,
            'status'         => 'DRAFT',
            'remarks'        => $this->revisionRemarks,
            'created_by'     => auth()->id() ?? 1,
        ]);

        // 2. Clone items
        foreach ($oldVersion->cobItems as $oldItem) {
            // Create new clone as DRAFT
            // Note: We don't mark old item as superseded yet.
            $newItem = \App\Models\CobItem::create([
                'version_id' => $newVersion->id,
                'recom_amount' => $oldItem->recom_amount,
                'encumbered_amount' => $oldItem->encumbered_amount,
                'actual_spent' => $oldItem->actual_spent,
                'current_balance' => $oldItem->current_balance,
                'ppa_code' => $oldItem->ppa_code,
                'ppa_desc' => $oldItem->ppa_desc,
                'sub_ppa_code' => $oldItem->sub_ppa_code,
                'sub_ppa_desc' => $oldItem->sub_ppa_desc,
                'exp_desc' => $oldItem->exp_desc,
                'is_ict' => $oldItem->is_ict,
                'account' => $oldItem->account,
                'tier' => $oldItem->tier,
                'class' => $oldItem->class,
                'gass' => $oldItem->gass,
                'transaction_id' => $oldItem->transaction_id,
                'work_and_financial_plan_id' => $oldItem->work_and_financial_plan_id,
                'office_id' => $oldItem->office_id,
                'sector' => $oldItem->sector,
                'full_particulars' => $oldItem->full_particulars,
                'particulars1' => $oldItem->particulars1,
                'particulars2' => $oldItem->particulars2,
                'unit' => $oldItem->unit,
                'recom_qty' => $oldItem->recom_qty,
                'version_number' => $oldItem->version_number + 1,
                'is_active' => false,
                'status' => 'DRAFT',
            ]);
            
            // We set the pointer immediately so we know the lineage, 
            // but the status change only happens on approval.
            $oldItem->update(['superseded_by_id' => $newItem->id]);
        }

        $this->showRevisionModal = false;
        session()->flash('cob_status', "Revision '{$newName}' created as DRAFT. The previous version remains ACTIVE until this draft is approved.");
    }

    public function deleteVersion(string $versionId): void
    {
        $version = CobVersion::findOrFail($versionId);
        
        if ($version->is_active) {
            session()->flash('cob_error', 'Cannot delete an active COB version. Deactivate it first.');
            return;
        }

        // Fix FK violation: Nullify any pointers from other versions to items in this version
        $itemIds = \App\Models\CobItem::where('version_id', $version->id)->pluck('id');
        \App\Models\CobItem::whereIn('superseded_by_id', $itemIds)->update(['superseded_by_id' => null]);

        $version->delete();
        session()->flash('cob_status', 'COB Version deleted successfully.');
    }

    public function with(): array
    {
        return [
            'budgetYears' => BudgetYear::with(['versions' => fn($q) => $q->orderByDesc('created_at')])->orderByDesc('fiscal_year')->get(),
            'activeYear'  => BudgetYear::where('status', 'OPEN')->first(),
        ];
    }
}; ?>

<div class="p-container-padding bg-background space-y-6">

    {{-- Flash Status --}}
    @if(session('cob_status'))
    <div class="flex items-center gap-3 p-4 bg-[#d5e3ff] border border-[#001e40]/20 text-[#001e40] rounded-xl text-sm font-bold mb-4">
        <span class="material-symbols-outlined text-[20px]">check_circle</span>
        {{ session('cob_status') }}
    </div>
    @endif

    @if(session('cob_error'))
    <div class="flex items-center gap-3 p-4 bg-[#ffdbca] border border-[#723610]/20 text-[#723610] rounded-xl text-sm font-bold mb-4">
        <span class="material-symbols-outlined text-[20px]">error</span>
        {{ session('cob_error') }}
    </div>
    @endif

    {{-- Page Header Banner --}}
    <div class="bg-[#001e40] rounded-xl p-6 flex items-center justify-between overflow-hidden relative shadow-lg">
        <div class="relative z-10">
            <p class="text-[#a7c8ff] font-bold text-[11px] uppercase tracking-widest mb-1">COB Management · Annual Kick-off</p>
            <h2 class="text-2xl font-bold text-white">Corporate Operating Budget</h2>
            <p class="text-white/60 text-sm mt-1">Define budget years, initialize COB versions, and upload the WFP Excel to seed the system's Master DNA.</p>
        </div>
        <div class="hidden md:flex items-center gap-3 relative z-10">
            <button wire:click="$set('showYearForm', true)"
                    class="flex items-center gap-2 px-4 py-2.5 bg-white text-[#001e40] font-bold text-sm rounded-lg hover:bg-[#eeedf2] active:scale-95 transition-all shadow-md">
                <span class="material-symbols-outlined text-[20px]">add</span>New Budget Year
            </button>
            <button wire:click="$set('showVersionForm', true)"
                    class="flex items-center gap-2 px-4 py-2.5 bg-[#a7c8ff] text-[#001b3c] font-bold text-sm rounded-lg hover:opacity-90 active:scale-95 transition-all shadow-md">
                <span class="material-symbols-outlined text-[20px]">folder_open</span>New COB Version
            </button>
        </div>
        <div class="absolute -right-8 -top-8 w-48 h-48 rounded-full bg-white/5 pointer-events-none"></div>
        <div class="absolute -right-2 -bottom-10 w-36 h-36 rounded-full bg-white/5 pointer-events-none"></div>
    </div>

    {{-- KPI Strip --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
        @php
            $totalYears    = $budgetYears->count();
            $totalVersions = $budgetYears->sum(fn($y) => $y->versions->count());
            $activeVersion = $activeYear?->versions()->where('is_active', true)->first();
            $totalItems    = $activeVersion ? $activeVersion->cobItems()->count() : 0;
            $totalAlloc    = $activeYear?->total_allocation ?? 0;
        @endphp
        @foreach([
            ['label' => 'Budget Years',       'value' => $totalYears ?: null,  'icon' => 'calendar_month',    'bg' => 'bg-[#001e40]/8',   'ic' => 'text-[#001e40]', 'sub' => 'Fiscal periods defined'],
            ['label' => 'COB Versions',       'value' => $totalVersions ?: null,'icon' => 'layers',           'bg' => 'bg-[#d5e3ff]/60',  'ic' => 'text-[#1f477b]', 'sub' => 'Total across all years'],
            ['label' => 'Active Budget Lines','value' => $totalItems ?: null,  'icon' => 'list_alt',          'bg' => 'bg-green-50',       'ic' => 'text-green-700', 'sub' => 'COB items loaded'],
            ['label' => 'Total Allocation',   'value' => $totalAlloc > 0 ? '₱'.number_format($totalAlloc/1000000,2).'M' : null, 'icon' => 'account_balance', 'bg' => 'bg-[#d8e1ea]/60','ic' => 'text-[#3a5f94]','sub' => 'Active version budget'],
        ] as $kpi)
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between h-28">
            <div class="flex justify-between items-start">
                <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">{{ $kpi['label'] }}</span>
                <div class="w-9 h-9 {{ $kpi['bg'] }} rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined {{ $kpi['ic'] }} text-[20px]">{{ $kpi['icon'] }}</span>
                </div>
            </div>
            @if($kpi['value'] !== null)
                <p class="text-2xl font-bold text-[#001e40]">{{ $kpi['value'] }}</p>
                <p class="text-[11px] text-[#43474f] font-bold uppercase tracking-wider">{{ $kpi['sub'] }}</p>
            @else
                <p class="text-2xl font-bold text-[#c3c6d1]">—</p>
                <p class="text-[11px] text-[#c3c6d1] font-bold uppercase tracking-wider">Not configured</p>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Budget Year Cards --}}
    @forelse($budgetYears as $year)
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden">

        {{-- Year Header --}}
        <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl {{ $year->status === 'OPEN' ? 'bg-[#001e40]' : 'bg-[#eeedf2]' }} flex items-center justify-center">
                    <span class="material-symbols-outlined {{ $year->status === 'OPEN' ? 'text-[#a7c8ff]' : 'text-[#43474f]' }} text-[24px]">calendar_month</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-[#001e40] text-lg">FY {{ $year->fiscal_year }}</h3>
                        @if($year->status === 'OPEN')
                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded-full uppercase border border-green-200">Active Year</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($year->status !== 'OPEN')
                <button wire:click="activateYear('{{ $year->id }}')"
                        class="flex items-center gap-2 px-3 py-2 border border-[#c3c6d1] text-[#43474f] text-sm font-bold rounded-lg hover:border-[#001e40] hover:text-[#001e40] transition-all">
                    <span class="material-symbols-outlined text-[18px]">power_settings_new</span>Set Active
                </button>
                @endif
                <button wire:click="prepareVersion('{{ $year->id }}')"
                        class="flex items-center gap-2 px-3 py-2 bg-[#001e40] text-white text-sm font-bold rounded-lg hover:bg-[#003366] active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-[18px]">add</span>New Version
                </button>
            </div>
        </div>

        {{-- Versions List --}}
        @if($year->versions->isEmpty())
        <div class="py-12 text-center flex flex-col items-center gap-3">
            <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">folder_open</span>
            <p class="font-bold text-[#001e40]">No COB Versions Yet</p>
            <p class="text-[13px] text-[#43474f]">Create the first version (e.g., "Original") to begin the upload process.</p>
        </div>
        @else
        <div class="divide-y divide-[#c3c6d1]">
            @foreach($year->versions as $ver)
            <div class="p-gutter flex flex-wrap items-center gap-6 hover:bg-[#f9f9fe] transition-colors">
                {{-- Version Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="font-bold text-[#001e40]">{{ $ver->version_name }}</span>
                        @if($ver->status === 'APPROVED' || $ver->is_active)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-[#d5e3ff] text-[#001b3c]">Approved</span>
                        @elseif($ver->status === 'SUPERSEDED')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-[#ffdbca] text-[#723610]">Superseded</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-[#eeedf2] text-[#43474f]">Draft</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 text-[11px] text-[#43474f] font-bold uppercase tracking-wider">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">list_alt</span>{{ number_format($ver->cobItems()->count()) }} items</span>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">account_balance</span>₱{{ number_format($ver->cobItems()->sum('recom_amount')/1000000, 2) }}M</span>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span>Created {{ $ver->created_at->diffForHumans() }}</span>
                    </div>
                    @if($ver->remarks)
                        <div class="mt-2 flex gap-2 items-start text-[12px] text-[#43474f] bg-[#f4f3f8] p-2 rounded-lg border border-[#c3c6d1]/50">
                            <span class="material-symbols-outlined text-[16px] text-[#001e40] mt-0.5">comment</span>
                            <p class="leading-relaxed"><span class="font-bold text-[#001e40] uppercase text-[10px] mr-1">Justification:</span>{{ $ver->remarks }}</p>
                        </div>
                    @endif
                </div>

                {{-- Upload Action --}}
                <div class="flex items-center gap-2">
                    @if($ver->status === 'DRAFT' && !str_contains($ver->version_name, 'Revision'))
                        <button wire:click="openUploadModal('{{ $ver->id }}')"
                               class="flex items-center gap-2 px-4 py-2.5 border-2 border-dashed border-[#001e40]/30 text-[#001e40] font-bold text-sm rounded-lg hover:border-[#001e40] hover:bg-[#f4f3f8] transition-all relative">
                            <span class="material-symbols-outlined text-[20px]">upload_file</span>
                            <span>Upload WFP Excel</span>
                        </button>
                    @endif

                    @if($ver->cobItems()->count() > 0 && $ver->status === 'DRAFT')
                    <button @click="$dispatch('confirm', {
                                title: 'Approve Version?',
                                message: 'Approve \'{{ $ver->version_name }}\'? This will unlock budget lines for GSU and supersede any previously approved version.',
                                type: 'success',
                                onConfirm: () => $wire.activateVersion('{{ $ver->id }}')
                            })"
                            class="flex items-center gap-2 px-4 py-2.5 bg-green-700 text-white font-bold text-sm rounded-lg hover:bg-green-800 active:scale-95 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[20px]">check_circle</span>Approve
                    </button>
                    @endif

                    @if(($ver->status === 'APPROVED' || $ver->is_active) && !$year->versions->contains('status', 'DRAFT'))
                    <button wire:click="createRevision('{{ $ver->id }}')"
                            class="flex items-center gap-2 px-4 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#003366] active:scale-95 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[20px]">content_copy</span>Create Revision
                    </button>
                    @elseif($ver->status === 'APPROVED' || $ver->is_active)
                    <button disabled
                            class="flex items-center gap-2 px-4 py-2.5 bg-[#c3c6d1] text-[#43474f] font-bold text-sm rounded-lg cursor-not-allowed opacity-60"
                            title="A draft revision already exists for this year. Approve or delete it first.">
                        <span class="material-symbols-outlined text-[20px]">block</span>Create Revision
                    </button>
                    @endif

                    @if($ver->status === 'SUPERSEDED')
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-orange-50 border border-orange-200 text-orange-800 font-bold text-sm rounded-lg">
                        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings:'FILL' 1;">archive</span>Archived
                    </div>
                    @endif

                    <x-icon-button href="{{ route('cob.items', $ver->id) }}" wire:navigate icon="open_in_new" title="View Budget Lines" />

                    @if($ver->status === 'DRAFT')
                    <x-icon-button icon="delete" variant="error" title="Delete Draft"
                        @click="$dispatch('confirm', {
                                title: 'Delete COB Version?',
                                message: 'Are you sure you want to delete this draft and all its budget items? This cannot be undone.',
                                type: 'danger',
                                onConfirm: () => $wire.deleteVersion('{{ $ver->id }}')
                            })" />
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @empty
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm py-20 text-center flex flex-col items-center gap-4">
        <span class="material-symbols-outlined text-[64px] text-[#c3c6d1]">account_balance</span>
        <p class="font-bold text-[#001e40] text-xl">No Budget Years Configured</p>
        <p class="text-sm text-[#43474f] max-w-sm">Create a Budget Year to begin the COB initialization process. This is the foundation for all procurement and inventory tracking.</p>
        <button wire:click="$set('showYearForm', true)"
                class="flex items-center gap-2 px-5 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#003366] active:scale-95 transition-all shadow-md mt-2">
            <span class="material-symbols-outlined text-[20px]">add</span>Create First Budget Year
        </button>
    </div>
    @endforelse

    {{-- Modal: New Budget Year --}}
    @if($showYearForm)
    @teleport('body')
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showYearForm', false)"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 z-10">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-xl font-bold text-[#001e40]">Create Budget Year</h3>
                    <p class="text-[13px] text-[#43474f] mt-1">Define a new fiscal period for the COB system.</p>
                </div>
                <button wire:click="$set('showYearForm', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="space-y-4">
                <x-form-input label="Fiscal Year" icon="calendar_month" placeholder="e.g. 2026" wire:model="newYear" type="number" />
            </div>
            @error('newYear')<p class="text-[12px] text-[#ba1a1a] font-bold mt-2">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-3 mt-6">
                <x-primary-button variant="secondary" wire:click="$set('showYearForm', false)">Cancel</x-primary-button>
                <x-primary-button icon="add" wire:click="createBudgetYear">Create Budget Year</x-primary-button>
            </div>
        </div>
    </div>
    @endteleport
    @endif

    {{-- Modal: New COB Version --}}
    @if($showVersionForm)
    @teleport('body')
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showVersionForm', false)"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 z-10">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-xl font-bold text-[#001e40]">Initialize COB Version</h3>
                    <p class="text-[13px] text-[#43474f] mt-1">Create a version container before uploading the WFP Excel file.</p>
                </div>
                <button wire:click="$set('showVersionForm', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="space-y-4">
                <x-form-select label="Budget Year" icon="calendar_month" wire:model="selectedYearId"
                    :options="$budgetYears->pluck('fiscal_year', 'id')->map(fn($year) => 'FY ' . $year)->toArray()" placeholder="Select a year" />
                <x-form-input label="Version Name" icon="label" placeholder="e.g. Original" wire:model="versionName" />
            </div>
            @error('selectedYearId')<p class="text-[12px] text-[#ba1a1a] font-bold mt-2">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-3 mt-6">
                <x-primary-button variant="secondary" wire:click="$set('showVersionForm', false)">Cancel</x-primary-button>
                <x-primary-button icon="folder_open" wire:click="createVersion">Initialize Version</x-primary-button>
            </div>
        </div>
    </div>
    @endteleport
    @endif

    {{-- Modal: Upload WFP Excel --}}
    @if($showUploadModal)
    @teleport('body')
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showUploadModal', false)"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl p-8 z-10" 
             x-data="{ isDragging: false }"
             @dragover.prevent="isDragging = true"
             @dragleave.prevent="isDragging = false"
             @drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))">
            
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-xl font-bold text-[#001e40]">Upload WFP Document</h3>
                    <p class="text-[13px] text-[#43474f] mt-1">Upload the Work and Financial Plan (Excel/CSV) for processing.</p>
                </div>
                <button wire:click="$set('showUploadModal', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="space-y-6">
                {{-- Drop Zone --}}
                <div class="relative border-2 border-dashed rounded-2xl p-10 text-center transition-all duration-200"
                     :class="isDragging ? 'border-[#001e40] bg-[#f4f3f8]' : 'border-[#c3c6d1] bg-white'">
                    
                    <input type="file" class="hidden" x-ref="fileInput" wire:model="wfpFile" accept=".xlsx,.xls,.csv" />

                    @if(!$wfpFile)
                        <div class="flex flex-col items-center gap-4">
                            <div class="w-16 h-16 rounded-full bg-[#f4f3f8] flex items-center justify-center">
                                <span class="material-symbols-outlined text-[32px] text-[#001e40]">cloud_upload</span>
                            </div>
                            <div>
                                <p class="font-bold text-[#001e40]">Click to upload or drag and drop</p>
                                <p class="text-[12px] text-[#43474f] mt-1">Excel or CSV files up to 20MB</p>
                            </div>
                            <button type="button" @click="$refs.fileInput.click()" 
                                    class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#003366] transition-all shadow-md">
                                Select File
                            </button>
                        </div>
                    @else
                        <div class="flex flex-col items-center gap-4">
                            <div class="w-16 h-16 rounded-full bg-green-50 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[32px] text-green-700">description</span>
                            </div>
                            <div>
                                <p class="font-bold text-green-800">File Selected</p>
                                <p class="text-[14px] text-[#001e40] font-mono mt-1">{{ $wfpFile->getClientOriginalName() }}</p>
                            </div>
                            <button type="button" wire:click="$set('wfpFile', null)" class="text-[12px] text-red-600 font-bold hover:underline">Remove File</button>
                        </div>
                    @endif

                    <div wire:loading wire:target="wfpFile" class="absolute inset-0 bg-white/80 backdrop-blur-[2px] flex items-center justify-center rounded-2xl">
                        <div class="flex flex-col items-center gap-3">
                            <span class="material-symbols-outlined animate-spin text-[32px] text-[#001e40]">sync</span>
                            <span class="text-sm font-bold text-[#001e40]">Uploading to server...</span>
                        </div>
                    </div>
                </div>

                @if($wfpFile && !$uploading)
                    <button wire:click="uploadWfp" 
                            class="w-full bg-[#001e40] text-white font-bold py-4 rounded-xl hover:bg-[#003366] transition-all shadow-lg flex items-center justify-center gap-3">
                        <span class="material-symbols-outlined">play_circle</span>
                        Process & Map Budget DNA
                    </button>
                @endif

                @if($uploading)
                    <div class="w-full bg-[#f4f3f8] rounded-xl p-6 flex flex-col items-center gap-4">
                        <span class="material-symbols-outlined animate-spin text-[40px] text-[#001e40]">database</span>
                        <div class="text-center">
                            <p class="font-bold text-[#001e40]">Processing Budget Items...</p>
                            <p class="text-[12px] text-[#43474f] mt-1">Please do not close this window. We are seeding the database using optimized chunking.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endteleport
    @endif

    {{-- Modal: Create Revision --}}
    @if($showRevisionModal)
    @teleport('body')
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#001e40]/40 backdrop-blur-sm" wire:click="$set('showRevisionModal', false)"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 z-10">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-xl font-bold text-[#001e40]">Create Revision</h3>
                    <p class="text-[13px] text-[#43474f] mt-1">Initialize a new draft version. The current version will remain active until the revision is approved.</p>
                </div>
                <button wire:click="$set('showRevisionModal', false)" class="p-1.5 rounded-lg hover:bg-[#eeedf2] text-[#43474f] transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <div class="space-y-4">
                <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl flex gap-3">
                    <span class="material-symbols-outlined text-blue-600 text-[20px]">info</span>
                    <p class="text-[12px] text-blue-800 leading-snug">
                        All budget lines and current encumbrances will be cloned into a new <b>DRAFT</b> version.
                    </p>
                </div>
                
                <div class="space-y-1">
                    <label class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider ml-1">Revision Remarks / Justification</label>
                    <textarea wire:model="revisionRemarks" 
                              class="w-full bg-[#f4f3f8] border border-[#c3c6d1] rounded-xl p-4 text-sm focus:ring-[#001e40] focus:border-[#001e40] min-h-[120px]"
                              placeholder="Describe why this revision is being created (e.g., Mid-Year Adjustment, Supplemental Budget)..."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8">
                <x-primary-button variant="secondary" wire:click="$set('showRevisionModal', false)" wire:loading.attr="disabled">Cancel</x-primary-button>
                <x-primary-button wire:click="confirmRevision" wire:loading.attr="disabled" wire:target="confirmRevision" class="min-w-[180px]">
                    <span class="flex items-center justify-center gap-2 whitespace-nowrap">
                        <span wire:loading.remove wire:target="confirmRevision" class="material-symbols-outlined text-[20px]">content_copy</span>
                        <svg wire:loading wire:target="confirmRevision" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        
                        <span wire:loading.remove wire:target="confirmRevision">Initialize Revision</span>
                        <span wire:loading wire:target="confirmRevision">Initializing...</span>
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>
    @endteleport
    @endif

    {{-- Modal: Import Result --}}
    @if($showResultModal)
    @teleport('body')
    <div class="fixed inset-0 z-[110] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#001e40]/60 backdrop-blur-md" wire:click="$set('showResultModal', false)"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden z-10">
            <div class="p-8 text-center">
                @if($importError)
                    <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="material-symbols-outlined text-[40px] text-red-600">error</span>
                    </div>
                    <h3 class="text-xl font-bold text-[#001e40]">Import Failed</h3>
                    <p class="text-sm text-[#43474f] mt-2 leading-relaxed">{{ $importError }}</p>
                @else
                    <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="material-symbols-outlined text-[40px] text-green-600">check_circle</span>
                    </div>
                    <h3 class="text-xl font-bold text-[#001e40]">Import Successful</h3>
                    <div class="mt-4 p-4 bg-[#f4f3f8] rounded-2xl inline-block">
                        <p class="text-3xl font-black text-[#001e40]">{{ number_format($importCount) }}</p>
                        <p class="text-[10px] font-bold text-[#43474f] uppercase tracking-widest mt-1">Budget Lines Mapped</p>
                    </div>
                    @if($importCount === 0)
                        <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-[12px] text-amber-800 leading-tight">
                            <b>Zero items imported.</b> This usually means the Excel column headers don't match our required mapping.
                        </div>
                    @else
                        <p class="text-sm text-[#43474f] mt-4 leading-relaxed">The Master DNA has been successfully seeded. You can now proceed to review the budget items.</p>
                    @endif
                @endif
            </div>
            <div class="p-6 bg-[#f9f9fe] border-t border-[#eeedf2] flex justify-center">
                <button wire:click="$set('showResultModal', false)" 
                        class="px-8 py-3 bg-[#001e40] text-white font-bold rounded-xl hover:bg-[#003366] transition-all shadow-lg active:scale-95">
                    Continue
                </button>
            </div>
        </div>
    </div>
    @endteleport
    @endif

</div>
