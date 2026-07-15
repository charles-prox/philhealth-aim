<?php

use App\Models\Employee;
use App\Models\Office;
use App\Models\SignatoryRegistry;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts.app')] class extends Component
{
    // ─── Modal state ──────────────────────────────────────────────────────────
    public bool $showEditModal     = false;
    public ?int $editingRegistryId = null;

    // ─── Edit form fields ─────────────────────────────────────────────────────
    public string $positionTitle       = '';
    public string $activeHolder        = 'PRIMARY';
    public ?int   $primaryEmployeeId   = null;
    public ?int   $oicPrimaryId        = null;
    public ?int   $oicSecondaryId      = null;

    // ─── Employee search within the modal ─────────────────────────────────────
    public string $primarySearch   = '';
    public string $oic1Search      = '';
    public string $oic2Search      = '';

    // ─── Success/error feedback ───────────────────────────────────────────────
    public ?string $successMessage = null;
    public ?string $errorMessage   = null;

    public function mount(): void
    {
        if (!auth()->user()->hasRole('Admin')) {
            abort(403, 'Unauthorized: Signatory Switchboard is restricted to Administrators.');
        }
    }

    // ─── Modal: open ──────────────────────────────────────────────────────────

    public function openEdit(int $registryId): void
    {
        $this->resetErrorBag();
        $this->successMessage = null;
        $this->errorMessage   = null;

        $row = SignatoryRegistry::findOrFail($registryId);
        $this->editingRegistryId  = $row->id;
        $this->positionTitle      = $row->position_title;
        $this->activeHolder       = $row->active_holder;
        $this->primaryEmployeeId  = $row->primary_employee_id;
        $this->oicPrimaryId       = $row->oic_primary_employee_id;
        $this->oicSecondaryId     = $row->oic_secondary_employee_id;
        $this->primarySearch      = '';
        $this->oic1Search         = '';
        $this->oic2Search         = '';
        $this->showEditModal      = true;
    }

    public function closeEdit(): void
    {
        $this->showEditModal      = false;
        $this->editingRegistryId  = null;
        $this->primarySearch      = '';
        $this->oic1Search         = '';
        $this->oic2Search         = '';
    }

    // ─── Live employee search helpers ─────────────────────────────────────────

    #[Computed]
    public function primaryMatches(): \Illuminate\Support\Collection
    {
        if (strlen($this->primarySearch) < 2) return collect();
        return Employee::where(DB::raw('LOWER(fullname)'), 'like', '%' . strtolower($this->primarySearch) . '%')
            ->orderBy('fullname')->limit(8)->get(['id', 'fullname', 'designation', 'office_division']);
    }

    #[Computed]
    public function oic1Matches(): \Illuminate\Support\Collection
    {
        if (strlen($this->oic1Search) < 2) return collect();
        return Employee::where(DB::raw('LOWER(fullname)'), 'like', '%' . strtolower($this->oic1Search) . '%')
            ->orderBy('fullname')->limit(8)->get(['id', 'fullname', 'designation', 'office_division']);
    }

    #[Computed]
    public function oic2Matches(): \Illuminate\Support\Collection
    {
        if (strlen($this->oic2Search) < 2) return collect();
        return Employee::where(DB::raw('LOWER(fullname)'), 'like', '%' . strtolower($this->oic2Search) . '%')
            ->orderBy('fullname')->limit(8)->get(['id', 'fullname', 'designation', 'office_division']);
    }

    // ─── Selection actions (called from dropdown results) ────────────────────

    public function selectPrimary(int $empId): void
    {
        $this->primaryEmployeeId = $empId;
        $this->primarySearch     = Employee::find($empId)?->fullname ?? '';
    }

    public function selectOic1(int $empId): void
    {
        $this->oicPrimaryId = $empId;
        $this->oic1Search   = Employee::find($empId)?->fullname ?? '';
    }

    public function selectOic2(int $empId): void
    {
        $this->oicSecondaryId = $empId;
        $this->oic2Search     = Employee::find($empId)?->fullname ?? '';
    }

    public function clearPrimary(): void
    {
        $this->primaryEmployeeId = null;
        $this->primarySearch     = '';  // Reset so the search box appears empty and ready
    }
    public function clearOic1(): void { $this->oicPrimaryId = null; $this->oic1Search = ''; }
    public function clearOic2(): void { $this->oicSecondaryId = null; $this->oic2Search = ''; }

    // ─── Fast active-holder toggle (no modal needed) ─────────────────────────

    public function setActiveHolder(int $registryId, string $holder, \App\Services\SignatoryService $service): void
    {
        try {
            $service->updateActiveHolder($registryId, $holder);
            $row = SignatoryRegistry::findOrFail($registryId);
            $this->successMessage = "Active holder for \"{$row->position_title}\" updated to {$holder}.";
            $this->errorMessage   = null;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->successMessage = null;
        }
    }

    // ─── Save slot configuration ──────────────────────────────────────────────

    public function saveSlot(\App\Services\SignatoryService $service): void
    {
        if (!$this->editingRegistryId) {
            return;
        }

        $this->validate([
            'positionTitle'     => 'required|string|max:255',
            'primaryEmployeeId' => 'required|integer|exists:employees,id',
            'oicPrimaryId'      => 'nullable|integer|exists:employees,id',
            'oicSecondaryId'    => 'nullable|integer|exists:employees,id',
            'activeHolder'      => 'required|in:PRIMARY,OIC_1,OIC_2',
        ], [
            'primaryEmployeeId.required' => 'A Primary Holder must be assigned before saving this slot.',
        ]);

        try {
            $service->saveSlot($this->editingRegistryId, [
                'position_title' => $this->positionTitle,
                'primary_employee_id' => $this->primaryEmployeeId,
                'oic_primary_employee_id' => $this->oicPrimaryId,
                'oic_secondary_employee_id' => $this->oicSecondaryId,
                'active_holder' => $this->activeHolder,
            ]);

            $row = SignatoryRegistry::findOrFail($this->editingRegistryId);
            $this->successMessage = "Slot \"{$row->position_title}\" updated successfully.";
            $this->errorMessage   = null;
            $this->closeEdit();
        } catch (\Exception $e) {
            $this->addError('activeHolder', $e->getMessage());
        }
    }

    // ─── Data ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        // Group by: regional (office_id null) first, then per-office slots grouped by Division
        $regional = SignatoryRegistry::with(['primaryEmployee', 'oicPrimary', 'oicSecondary', 'office'])
            ->whereNull('office_id')
            ->orderByRaw("CASE position_code WHEN 'RVP' THEN 1 WHEN 'MSD_HEAD' THEN 2 WHEN 'HCDMD_CHIEF' THEN 3 WHEN 'FOD_CHIEF' THEN 4 WHEN 'BUDGET_OFFICER' THEN 5 ELSE 6 END")
            ->get();

        $divisional = SignatoryRegistry::with(['primaryEmployee', 'oicPrimary', 'oicSecondary', 'office.parent'])
            ->whereNotNull('office_id')
            ->get()
            ->groupBy(function ($row) {
                return $row->office?->division?->name ?? 'Other Local Slots';
            })
            ->sortBy(function ($group, $key) {
                $order = [
                    'Office of the Regional Vice President' => 1,
                    'Management Services Division' => 2,
                    'Health Care Delivery Management Division' => 3,
                    'Field Operations Division' => 4,
                ];
                return $order[$key] ?? 99;
            });

        $totalSlots    = SignatoryRegistry::count();
        $activeOics    = SignatoryRegistry::where('active_holder', '!=', 'PRIMARY')->count();
        $unassigned    = SignatoryRegistry::where('primary_employee_id', SignatoryRegistry::first()?->primary_employee_id ?? 0)->count();

        return compact('regional', 'divisional', 'totalSlots', 'activeOics');
    }
}; ?>

<div class="p-container-padding bg-background flex flex-col gap-6">

    {{-- Flash Messages --}}
    @if($successMessage)
        <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl text-sm font-bold shadow-sm animate-in fade-in duration-200">
            <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            {{ $successMessage }}
            <button wire:click="$set('successMessage', null)" class="ml-auto text-green-600 hover:text-green-900"><span class="material-symbols-outlined text-[18px]">close</span></button>
        </div>
    @endif
    @if($errorMessage)
        <div class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm font-bold shadow-sm animate-in fade-in duration-200">
            <span class="material-symbols-outlined text-[20px]">error</span>
            {{ $errorMessage }}
            <button wire:click="$set('errorMessage', null)" class="ml-auto text-red-600 hover:text-red-900"><span class="material-symbols-outlined text-[18px]">close</span></button>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-[#001e40] to-[#1f477b] rounded-xl flex items-center justify-center shadow-md">
                    <span class="material-symbols-outlined text-white text-[22px]" style="font-variation-settings: 'FILL' 1;">stylus_note</span>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-[#001e40] tracking-tight">Signatory Switchboard</h1>
                    <p class="text-xs text-[#43474f]">Manage signing authorities and real-time OIC designations for all position slots</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs text-[#43474f] font-bold bg-white border border-[#eeedf2] px-3 py-2 rounded-xl shadow-sm">
            <span class="material-symbols-outlined text-[16px] text-[#001e40]">admin_panel_settings</span>
            Admin Only · Region X
        </div>
    </div>

    {{-- KPI Strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-gutter">
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-[#001e40]/10 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-[#001e40] text-[26px]" style="font-variation-settings: 'FILL' 1;">stylus_note</span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider">Total Slots</p>
                <p class="text-2xl font-bold text-[#001e40]">{{ $totalSlots }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-amber-600 text-[26px]" style="font-variation-settings: 'FILL' 1;">manage_accounts</span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Active OIC Overrides</p>
                <p class="text-2xl font-bold text-amber-700">{{ $activeOics }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-green-700 text-[26px]" style="font-variation-settings: 'FILL' 1;">verified</span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider">On Primary Authority</p>
                <p class="text-2xl font-bold text-green-700">{{ $totalSlots - $activeOics }}</p>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="bg-[#f9f9fe] border border-[#eeedf2] rounded-xl px-5 py-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-[11px] font-bold text-[#43474f]">
        <span class="uppercase tracking-wider text-[10px] text-[#43474f]/60">Active Holder Legend:</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#001e40]"></span> PRIMARY — Permanent post-holder</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> OIC 1 — First OIC designation override</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> OIC 2 — Second OIC fallback override</span>
        <span class="ml-auto flex items-center gap-1 text-[#001e40]/60 italic font-normal"><span class="material-symbols-outlined text-[13px]">info</span>Flip the toggle to rotate signing authority instantly</span>
    </div>

    {{-- Regional Slots Table --}}
    <div class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-[#001e40] to-[#1f477b] flex items-center gap-3">
            <span class="material-symbols-outlined text-white/80 text-[20px]">public</span>
            <h2 class="font-bold text-white text-sm uppercase tracking-wider">Regional Authority Slots</h2>
            <span class="ml-auto text-xs text-white/60 font-semibold">{{ $regional->count() }} slots · Office-Independent</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Position</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Primary Holder</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">OIC 1</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">OIC 2</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center">Active Holder</th>
                        <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Configure</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eeedf2] text-[13px]">
                    @forelse($regional as $row)
                        @include('livewire.admin.partials.signatory-row', ['row' => $row])
                    @empty
                        <tr>
                            <td colspan="6" class="px-gutter py-12 text-center text-sm text-[#43474f]/60 italic">
                                No regional slots found. Run the SignatoryRegistrySeeder.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Divisional Slots (grouped by parent Division) --}}
    @forelse($divisional as $divisionName => $officeRows)
        <div x-data="{ open: true }" class="bg-white border border-[#c3c6d1] rounded-2xl shadow-sm overflow-hidden transition-all duration-200">
            <div @click="open = !open" class="px-6 py-4 bg-[#f4f3f8] border-b border-[#c3c6d1] flex items-center gap-3 cursor-pointer select-none hover:bg-[#eeedf2] transition-colors">
                <span class="material-symbols-outlined text-[#001e40]/70 text-[20px] transition-transform duration-200" :class="open ? 'rotate-90' : ''">chevron_right</span>
                <span class="material-symbols-outlined text-[#001e40]/70 text-[20px]">corporate_fare</span>
                <h2 class="font-bold text-[#001e40] text-sm uppercase tracking-wider">{{ $divisionName }} Slots</h2>
                <span class="ml-auto text-xs text-[#43474f]/60 font-semibold">{{ $officeRows->count() }} {{ Str::plural('slot', $officeRows->count()) }}</span>
            </div>
            <div x-show="open" x-transition class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Position</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Primary Holder</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">OIC 1</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">OIC 2</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center">Active Holder</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Configure</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eeedf2] text-[13px]">
                        @foreach($officeRows->sortBy('position_title') as $row)
                            @include('livewire.admin.partials.signatory-row', ['row' => $row])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-[#f9f9fe] border border-dashed border-[#c3c6d1] rounded-2xl p-12 text-center">
            <span class="material-symbols-outlined text-[48px] text-[#c3c6d1] block mb-2">corporate_fare</span>
            <p class="font-bold text-[#001e40]">No divisional slots found</p>
            <p class="text-xs text-[#43474f]/60 mt-1">Run the SignatoryRegistrySeeder to seed Section and Unit level signatory slots.</p>
        </div>
    @endforelse
    @include('livewire.admin.partials.signatory-edit-modal')

</div>
