<?php

use App\Models\BudgetYear;
use App\Models\CobVersion;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    // APP Modal
    public bool $showAppModal = false;
    public ?int $appModalYear = null;

    #[On('app-status-updated')]
    public function refreshRegistry(): void
    {
        // Triggers parent component re-render
    }

    #[On('open-app-modal')]
    public function openAppModal(int $year): void
    {
        $this->appModalYear = $year;
        $this->showAppModal = true;
    }

    public function mount(): void
    {
        $active = BudgetYear::where('status', 'OPEN')->first();
        if ($active) {
            $this->selectedYearId = (string) $active->id;
        }
    }

    public function createBudgetYear(\App\Services\CobService $service): void
    {
        $this->validate([
            'newYear' => 'required|digits:4|integer|min:2020|max:2099|unique:budget_years,fiscal_year',
        ], ['newYear.unique' => 'This budget year already exists.']);

        $service->createBudgetYear($this->newYear);

        $this->reset(['newYear', 'showYearForm']);
        session()->flash('cob_status', "Budget Year FY {$this->newYear} created successfully.");
    }

    public function activateYear(string $yearId, \App\Services\CobService $service): void
    {
        $service->activateYear($yearId);
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

    public function createVersion(\App\Services\CobService $service): void
    {
        $this->validate([
            'selectedYearId' => 'required|exists:budget_years,id',
            'versionName'    => 'required|string|max:100',
        ]);

        $service->createVersion($this->selectedYearId, $this->versionName, auth()->id() ?? 1);

        $this->reset(['versionName', 'showVersionForm']);
        session()->flash('cob_status', "COB Version '{$this->versionName}' created and ready for upload.");
    }

    public function activateVersion(string $versionId, \App\Services\CobService $service): void
    {
        try {
            $service->activateVersion($versionId);
            $version = CobVersion::findOrFail($versionId);
            session()->flash('cob_status', "Version '{$version->version_name}' is now APPROVED. Budget lines are unlocked for GSU.");
        } catch (\Exception $e) {
            session()->flash('cob_error', $e->getMessage());
        }
    }

    public function createRevision(string $versionId): void
    {
        $this->revisionVersionId = $versionId;
        $this->revisionRemarks = '';
        $this->showRevisionModal = true;
    }

    public function confirmRevision(\App\Services\CobService $service): void
    {
        try {
            $newName = $service->createRevision($this->revisionVersionId, $this->revisionRemarks, auth()->id() ?? 1);
            $this->showRevisionModal = false;
            session()->flash('cob_status', "Revision '{$newName}' created as DRAFT. The previous version remains ACTIVE until this draft is approved.");
        } catch (\Exception $e) {
            session()->flash('cob_error', $e->getMessage());
        }
    }

    public function deleteVersion(string $versionId, \App\Services\CobService $service): void
    {
        try {
            $service->deleteVersion($versionId);
            session()->flash('cob_status', 'COB Version deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('cob_error', $e->getMessage());
        }
    }

    public function with(): array
    {
        return [
            'budgetYears' => BudgetYear::with(['versions' => fn($q) => $q->orderByDesc('created_at')])->orderByDesc('fiscal_year')->get(),
            'activeYear'  => BudgetYear::where('status', 'OPEN')->first(),
        ];
    }
}; ?>

<div class="p-container-padding bg-background flex flex-col gap-6">

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
            <p class="text-[#a7c8ff] font-bold text-[11px] uppercase tracking-widest mb-1">Budget Management · COB Registry</p>
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

        {{-- Versions List (excludes DRAFT Realignment versions — managed in Realignment page, but shows Approved/Superseded ones) --}}
        @php 
            $planVersions = $year->versions->filter(function($v) {
                if (str_contains($v->version_name, 'Realignment') && $v->status === 'DRAFT') {
                    return false;
                }
                return true;
            });
        @endphp
        @if($planVersions->isEmpty())
        <div class="py-12 text-center flex flex-col items-center gap-3">
            <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">folder_open</span>
            <p class="font-bold text-[#001e40]">No COB Versions Yet</p>
            <p class="text-[13px] text-[#43474f]">Create the first version (e.g., "Original") to begin the upload process.</p>
        </div>
        @else
        <div class="divide-y divide-[#c3c6d1]">
            @foreach($planVersions as $ver)
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

                    @if($ver->status === 'APPROVED' || $ver->is_active)
                        @php
                            $appHeader = \App\Models\AppHeader::where('fiscal_year', $year->fiscal_year)->first();
                            $appLineItemsCount = $appHeader ? $appHeader->lineItems()->count() : 0;
                            $appTotalBudget = $appHeader ? $appHeader->lineItems()->sum('approved_budget') : 0;
                        @endphp
                        <div class="mt-3 flex flex-wrap items-center gap-3 bg-[#f9f9fe] border border-[#c3c6d1]/50 p-3 rounded-xl max-w-3xl">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[#001e40] text-[18px]">gavel</span>
                                <span class="text-xs font-bold text-[#001e40]">APP Status:</span>
                            </div>
                            
                            @if($appHeader && $appHeader->is_approved)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 border border-green-200 text-[10px] font-bold uppercase rounded-full shadow-3xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse"></span>
                                    Active ({{ number_format($appLineItemsCount) }} lines · ₱{{ number_format($appTotalBudget, 2) }})
                                </span>
                            @elseif($appHeader)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#fff8e1] text-[#f57f17] border border-[#ffe082] text-[10px] font-bold uppercase rounded-full shadow-3xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#f57f17]"></span>
                                    Ingested (Phase 1, Unapproved)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-[#ba1a1a] border border-red-200 text-[10px] font-bold uppercase rounded-full shadow-3xs">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#ba1a1a]"></span>
                                    Not Uploaded
                                </span>
                            @endif

                            <div class="ml-auto flex items-center gap-2">
                                @if($appHeader)
                                    <a href="{{ route('procurement.app.items', $appHeader->id) }}" wire:navigate class="border border-[#c3c6d1] text-[#001e40] hover:bg-[#001e40]/5 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition-all">
                                        <span class="material-symbols-outlined text-[16px]">list_alt</span>
                                        View APP Items
                                    </a>
                                @endif

                                @hasanyrole('Admin Head|Admin')
                                    <button type="button" @click="$dispatch('open-app-modal', { year: {{ $year->fiscal_year }} })" class="bg-[#001e40] text-white hover:bg-[#003366] px-3.5 py-1.5 rounded-lg text-xs font-bold shadow-sm flex items-center gap-1.5 transition-all">
                                        <span class="material-symbols-outlined text-[16px]">publish</span>
                                        APP Upload & Activation
                                    </button>
                                @endhasanyrole
                            </div>
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

        <!-- ========================================== -->
        <!--   APP UPLOAD & ACTIVATION GATEWAY  -->
        <!-- ========================================== -->
        @hasanyrole('Admin Head|Admin')
            @php
                $approvedCob = $year->versions->where('status', 'APPROVED')->first();
            @endphp
            @if(!$approvedCob)
                <section class="mt-6 border-t border-dashed border-[#c3c6d1] p-6 w-full">
                    <!-- Locked State Placeholder: Informational Banner matching MD3 Design Tokens -->
                    <div class="bg-[#eeedf2]/40 p-5 rounded-2xl border border-dashed border-[#c3c6d1] flex items-center gap-4 text-[#43474f]">
                        <div class="w-12 h-12 rounded-full bg-[#f4f3f8] flex items-center justify-center text-[#001e40] flex-shrink-0">
                            <span class="material-symbols-outlined text-2xl">lock_clock</span>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-[#001e40]">APP Upload & Activation Locked</h4>
                            <p class="text-xs mt-0.5 text-[#43474f]/80 leading-relaxed">
                                The APP Upload & Activation gateway for fiscal year <span class="font-bold text-[#001e40]">{{ $year->fiscal_year }}</span> will unlock automatically once the corresponding Corporate Operating Budget (COB) setup is formally finalized and marked as <span class="text-green-700 font-bold">APPROVED</span> by management.
                            </p>
                        </div>
                    </div>
                </section>
            @endif
        @endhasanyrole
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

    @include('livewire.cob.partials.cob-modals')

</div>
