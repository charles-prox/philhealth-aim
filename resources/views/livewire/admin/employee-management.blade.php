<?php

use App\Models\Employee;
use App\Models\Office;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;

    // Single Employee Modal State
    public bool $showEmployeeModal = false;
    public ?int $editingEmployeeId = null; // null = creating, int = editing
    public string $empIdNumber = '';
    public string $empFullname = '';
    public string $empDesignation = '';
    public int $empSalaryGrade = 1;
    public string $empOfficeDivision = '';
    public string $empSubOffice = '';
    public string $empStatus = 'PERMANENT';

    // Bulk Modal State
    public bool $showBulkModal = false;
    public string $bulkText = '';
    public array $bulkResults = [];

    // Delete Confirmation State
    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        if (!auth()->user()->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->editingEmployeeId = null;
        $this->empIdNumber = '';
        $this->empFullname = '';
        $this->empDesignation = '';
        $this->empSalaryGrade = 1;
        $this->empOfficeDivision = '';
        $this->empSubOffice = '';
        $this->empStatus = 'PERMANENT';
        $this->showEmployeeModal = true;
    }

    public function openEditModal(int $empId): void
    {
        $this->resetErrorBag();
        $emp = Employee::findOrFail($empId);
        $this->editingEmployeeId = $emp->id;
        $this->empIdNumber = $emp->id_number ?? '';
        $this->empFullname = $emp->fullname;
        $this->empDesignation = $emp->designation;
        $this->empSalaryGrade = (int) $emp->salary_grade;
        $this->empOfficeDivision = $emp->office_division;
        $this->empSubOffice = $emp->sub_office ?? '';
        $this->empStatus = $emp->employment_status;
        $this->showEmployeeModal = true;
    }

    public function saveEmployee(): void
    {
        $rules = [
            'empIdNumber'      => [
                'required', 
                'string', 
                'max:50', 
                $this->editingEmployeeId ? 'unique:employees,id_number,' . $this->editingEmployeeId : 'unique:employees,id_number'
            ],
            'empFullname'      => ['required', 'string', 'max:255'],
            'empDesignation'   => ['required', 'string', 'max:255'],
            'empSalaryGrade'   => ['required', 'integer', 'min:1', 'max:33'],
            'empOfficeDivision'=> ['required', 'string', 'exists:offices,acronym'],
            'empSubOffice'     => ['nullable', 'string', 'max:255'],
            'empStatus'        => ['required', 'string', 'in:PERMANENT,CASUAL,JO'],
        ];

        $this->validate($rules, [
            'empOfficeDivision.exists' => 'The selected office acronym does not exist.',
            'empStatus.in' => 'Employment status must be PERMANENT, CASUAL, or JO.',
        ]);

        Employee::updateOrCreate(
            ['id' => $this->editingEmployeeId],
            [
                'id_number'         => $this->empIdNumber,
                'fullname'          => $this->empFullname,
                'designation'       => $this->empDesignation,
                'salary_grade'      => $this->empSalaryGrade,
                'office_division'   => $this->empOfficeDivision,
                'sub_office'        => $this->empSubOffice ?: null,
                'employment_status' => $this->empStatus,
            ]
        );

        session()->flash('status', $this->editingEmployeeId ? "Employee details updated successfully." : "Employee record created successfully.");
        $this->showEmployeeModal = false;
    }

    public function confirmDelete(int $empId): void
    {
        $this->confirmingDeleteId = $empId;
    }

    public function deleteEmployee(): void
    {
        if (!$this->confirmingDeleteId) return;

        $emp = Employee::findOrFail($this->confirmingDeleteId);
        
        // Safety checks to prevent orphan references
        $isSignatory = \App\Models\SignatoryRegistry::where('primary_employee_id', $emp->id)
            ->orWhere('oic_primary_employee_id', $emp->id)
            ->orWhere('oic_secondary_employee_id', $emp->id)
            ->exists();

        if ($isSignatory) {
            session()->flash('error', "Cannot delete employee '{$emp->fullname}' because they are assigned to a slot in the Signatory Matrix.");
            $this->confirmingDeleteId = null;
            return;
        }

        $emp->delete();
        session()->flash('status', "Employee record deleted successfully.");
        $this->confirmingDeleteId = null;
    }

    public function openBulkModal(): void
    {
        $this->resetErrorBag();
        $this->bulkText = '';
        $this->bulkResults = [];
        $this->showBulkModal = true;
    }

    public function importBulk(): void
    {
        $this->validate([
            'bulkText' => 'required|string'
        ]);

        $lines = explode("\n", $this->bulkText);
        $inserted = 0;
        $skipped = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Split by Tab (excel copy-paste) or Comma
            $cols = str_contains($line, "\t") ? explode("\t", $line) : explode(",", $line);
            $cols = array_map('trim', $cols);

            if (count($cols) < 5) {
                $skipped[] = "Row " . ($index + 1) . ": Invalid column count. Must have at least 5 columns (ID Number, Fullname, Designation, Salary Grade, Office Acronym).";
                continue;
            }

            $idNumber      = $cols[0];
            $fullname      = $cols[1];
            $designation   = $cols[2];
            $salaryGrade   = is_numeric($cols[3]) ? intval($cols[3]) : 1;
            $officeAcronym = $cols[4];
            $subOffice     = $cols[5] ?? '';
            $rawStatus     = strtoupper($cols[6] ?? 'PERMANENT');

            // Map status
            $status = 'PERMANENT';
            if (str_contains($rawStatus, 'CASUAL')) {
                $status = 'CASUAL';
            } elseif (str_contains($rawStatus, 'JO') || str_contains($rawStatus, 'JOB') || str_contains($rawStatus, 'CONTRACT')) {
                $status = 'JO';
            }

            // Verify office
            $office = Office::where('acronym', $officeAcronym)->first();
            if (!$office) {
                $skipped[] = "Row " . ($index + 1) . ": Office acronym '{$officeAcronym}' does not exist.";
                continue;
            }

            if (empty($idNumber) || empty($fullname) || empty($designation)) {
                $skipped[] = "Row " . ($index + 1) . ": ID, Name, or Designation cannot be empty.";
                continue;
            }

            try {
                Employee::updateOrCreate(
                    ['id_number' => $idNumber],
                    [
                        'fullname'          => $fullname,
                        'designation'       => $designation,
                        'salary_grade'      => $salaryGrade,
                        'office_division'   => $officeAcronym,
                        'sub_office'        => $subOffice ?: null,
                        'employment_status' => $status,
                    ]
                );
                $inserted++;
            } catch (\Exception $e) {
                $skipped[] = "Row " . ($index + 1) . ": Error: " . $e->getMessage();
            }
        }

        $this->bulkResults = [
            'inserted' => $inserted,
            'skipped'  => $skipped
        ];

        session()->flash('status', "Bulk import complete. Imported/updated {$inserted} employee record(s).");
    }

    #[Computed]
    public function offices(): array
    {
        return Office::orderBy('name')
            ->get()
            ->mapWithKeys(fn($o) => [$o->acronym => "{$o->name} ({$o->acronym})"])
            ->toArray();
    }

    public function with(): array
    {
        $employees = Employee::query()
            ->when($this->search, function($q) {
                $q->where(function($sub) {
                    $sub->where(DB::raw('LOWER(fullname)'), 'like', '%' . strtolower($this->search) . '%')
                        ->orWhere('id_number', 'like', "%{$this->search}%")
                        ->orWhere('designation', 'like', "%{$this->search}%")
                        ->orWhere('office_division', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('fullname')
            ->paginate($this->perPage);

        return [
            'employees' => $employees,
            'totalCount' => Employee::count(),
            'permanentCount' => Employee::where('employment_status', 'PERMANENT')->count(),
            'casualCount' => Employee::where('employment_status', 'CASUAL')->count(),
            'joCount' => Employee::where('employment_status', 'JO')->count(),
        ];
    }
}; ?>

<div class="p-container-padding bg-background space-y-6">

    {{-- Flash Status --}}
    @if (session('status'))
        <div class="flex items-center gap-3 p-4 bg-[#d5e3ff] border border-[#001e40]/20 text-[#001e40] rounded-xl text-sm font-bold shadow-sm">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="flex items-center gap-3 p-4 bg-[#ffdad6] border border-[#ba1a1a]/20 text-[#ba1a1a] rounded-xl text-sm font-bold shadow-sm">
            <span class="material-symbols-outlined text-[20px]">error</span>
            {{ session('error') }}
        </div>
    @endif

    {{-- KPI Strip --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-gutter">
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-[#eeedf2] rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-[#001e40] text-[26px]">badge</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Total Records</p>
                <p class="text-2xl font-bold text-[#001e40]">{{ $totalCount }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-green-700 text-[26px]">assignment_turned_in</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Permanent</p>
                <p class="text-2xl font-bold text-green-700">{{ $permanentCount }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-indigo-700 text-[26px]">assignment_ind</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Casual</p>
                <p class="text-2xl font-bold text-indigo-700">{{ $casualCount }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-amber-700 text-[26px]">engineering</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Job Orders</p>
                <p class="text-2xl font-bold text-amber-700">{{ $joCount }}</p>
            </div>
        </div>
    </div>

    {{-- Employee Table Card --}}
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden flex flex-col relative">

        <div wire:loading wire:target="search, perPage" class="absolute inset-x-0 bottom-0 top-[57px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
            <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
            </div>
        </div>

        {{-- Table Header --}}
        <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap justify-between items-center gap-4">
            <h3 class="font-h2 text-h2 text-[#001e40]">Employee Matrix Registry</h3>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name, ID, office..."
                           class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] outline-none transition-all w-64 placeholder-[#43474f]/40"/>
                </div>
                
                <button wire:click="openBulkModal" class="flex items-center gap-2 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 text-indigo-700 px-4 py-2.5 rounded-lg text-sm font-bold transition-all">
                    <span class="material-symbols-outlined text-[20px]">group_add</span>
                    Bulk Import
                </button>

                <button wire:click="openCreateModal" class="flex items-center gap-2 bg-[#001e40] hover:bg-[#003272] text-white px-4 py-2.5 rounded-lg text-sm font-bold shadow-sm transition-all">
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                    New Employee
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">ID Number</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Employee Name</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Designation</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Office Division</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Sub-Office</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                    @forelse ($employees as $emp)
                        @php
                            $statusColors = [
                                'PERMANENT' => 'bg-green-50 text-green-700 border border-green-200/50',
                                'CASUAL'    => 'bg-indigo-50 text-indigo-700 border border-indigo-200/50',
                                'JO'        => 'bg-amber-50 text-amber-700 border border-amber-200/50',
                            ];
                            $statusClass = $statusColors[$emp->employment_status] ?? 'bg-gray-50 text-gray-700 border border-gray-200/50';
                        @endphp
                        <tr class="hover:bg-[#f4f3f8] transition-colors group">
                            {{-- ID Number --}}
                            <td class="p-table-cell-padding font-mono font-bold text-[#1a1c1f] tracking-wide">
                                {{ $emp->id_number ?: '—' }}
                            </td>
                            {{-- Name --}}
                            <td class="p-table-cell-padding font-bold text-[#001e40]">
                                {{ $emp->fullname }}
                            </td>
                            {{-- Designation --}}
                            <td class="p-table-cell-padding text-[#43474f]">
                                {{ $emp->designation }} <span class="text-[10px] bg-[#eeedf2] px-1.5 py-0.5 rounded text-[#1a1c1f]">SG {{ $emp->salary_grade }}</span>
                            </td>
                            {{-- Office --}}
                            <td class="p-table-cell-padding font-bold text-[#001b3c]">
                                {{ $emp->office_division }}
                            </td>
                            {{-- Sub-Office --}}
                            <td class="p-table-cell-padding text-[#43474f] italic">
                                {{ $emp->sub_office ?: '—' }}
                            </td>
                            {{-- Status --}}
                            <td class="p-table-cell-padding">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $statusClass }}">
                                    {{ $emp->employment_status }}
                                </span>
                            </td>
                            {{-- Actions --}}
                            <td class="p-table-cell-padding text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="openEditModal({{ $emp->id }})"
                                             title="Edit details"
                                             class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800 transition-all">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button wire:click="confirmDelete({{ $emp->id }})"
                                             title="Delete employee"
                                             class="p-1.5 rounded-lg text-[#ba1a1a] hover:bg-red-50 hover:text-[#93000a] transition-all">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-gutter py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                    <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">person_search</span>
                                    <p class="font-bold text-[#001e40]">No employees found</p>
                                    <p class="text-[13px]">Try adjusting your search queries.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer/Pagination --}}
        @if($employees->total() > 0)
            <div class="px-5 py-3 border-t border-[#c3c6d1] bg-[#f9f9fe] flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2.5 text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                        <span>Show</span>
                        <div class="w-24">
                            <x-form-select 
                                label="" 
                                wire:model.live="perPage"
                                :options="[10 => '10', 15 => '15', 25 => '25', 50 => '50', 100 => '100']"
                                placeholder="15"
                                placement="top" />
                        </div>
                    </div>
                    <div class="h-4 w-[1px] bg-[#c3c6d1]"></div>
                    <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                        Showing {{ $employees->firstItem() }}–{{ $employees->lastItem() }} of {{ number_format($employees->total()) }} employees
                    </p>
                </div>
                <div class="employee-pagination">
                    {{ $employees->links() }}
                </div>
            </div>
            
            <style>
                .employee-pagination nav div:first-child { display: none; }
                .employee-pagination nav div:last-child { display: flex; gap: 0.5rem; }
                .employee-pagination nav span[aria-current="page"] span {
                    background-color: #001e40 !important;
                    color: white !important;
                    border-color: #001e40 !important;
                    border-radius: 0.5rem;
                    padding: 0.5rem 0.85rem;
                    font-weight: 700;
                    font-size: 0.875rem;
                }
                .employee-pagination nav a, .employee-pagination nav span[aria-disabled="true"] span {
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
                .employee-pagination nav a:hover {
                    background-color: #f4f3f8;
                    border-color: #001e40;
                    color: #001e40;
                }
                .employee-pagination svg { width: 1.25rem; height: 1.25rem; }
            </style>
        @endif
    </div>

    {{-- ───────────────────────────────────────────────────────────
         Single Employee Modal (Create & Edit)
    ─────────────────────────────────────────────────────────────── --}}
    @if($showEmployeeModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showEmployeeModal', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-visible animate-in fade-in zoom-in duration-200">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe] rounded-t-2xl">
                    <div>
                        <h3 class="font-bold text-[#001e40] text-[16px]">
                            {{ $editingEmployeeId ? 'Edit Employee Details' : 'Add New Employee' }}
                        </h3>
                        <p class="text-[12px] text-[#43474f]">
                            Configure HRIS ID, office assignment, and employment status.
                        </p>
                    </div>
                    <button wire:click="$set('showEmployeeModal', false)" class="p-1.5 rounded-lg text-[#43474f] hover:bg-[#eeedf2] transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <form wire:submit.prevent="saveEmployee" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">HRIS ID / ID Number <span class="text-red-500">*</span></label>
                            <input wire:model="empIdNumber" type="text" required placeholder="e.g. 20228900"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('empIdNumber') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input wire:model="empFullname" type="text" required placeholder="e.g. ANSHARI M. MANGONDATO"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('empFullname') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Designation <span class="text-red-500">*</span></label>
                            <input wire:model="empDesignation" type="text" required placeholder="e.g. Planning Officer III"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('empDesignation') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Salary Grade <span class="text-red-500">*</span></label>
                            <input wire:model="empSalaryGrade" type="number" min="1" max="33" required placeholder="e.g. 11"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('empSalaryGrade') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-form-select wire:model="empStatus" label="Employment Status" icon="work" required placeholder="-- Select Status --" :options="['PERMANENT' => 'PERMANENT (Regular)', 'CASUAL' => 'CASUAL', 'JO' => 'JO (Job Order)']" />
                            @error('empStatus') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Sub-Office / Division (Optional)</label>
                            <input wire:model="empSubOffice" type="text" placeholder="e.g. Procurement Unit"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('empSubOffice') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <x-form-select wire:model="empOfficeDivision" label="Office Assignment (Acronym)" icon="corporate_fare" required placeholder="-- Select Office --" :options="$this->offices" searchable placement="bottom" />
                        @error('empOfficeDivision') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Modal Footer --}}
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#c3c6d1]">
                        <button type="button" wire:click="$set('showEmployeeModal', false)"
                                class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#001e40] text-white text-sm font-bold rounded-lg hover:bg-[#003272] transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            {{ $editingEmployeeId ? 'Save Changes' : 'Add Employee' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ───────────────────────────────────────────────────────────
         Bulk Import Modal
    ─────────────────────────────────────────────────────────────── --}}
    @if($showBulkModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showBulkModal', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe]">
                    <div>
                        <h3 class="font-bold text-[#001e40] text-[16px]">Bulk Import Employee Registry</h3>
                        <p class="text-[12px] text-[#43474f]">
                            Copy-paste columns directly from Excel or write tab/comma-separated rows.
                        </p>
                    </div>
                    <button wire:click="$set('showBulkModal', false)" class="p-1.5 rounded-lg text-[#43474f] hover:bg-[#eeedf2] transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 flex flex-col gap-4 overflow-y-auto max-h-[75vh] custom-scrollbar">
                    
                    {{-- Parsing Format Guideline --}}
                    <div class="p-4 bg-indigo-50/50 border border-indigo-200 rounded-xl space-y-2 text-xs text-[#001b3c]">
                        <p class="font-bold uppercase flex items-center gap-1.5 text-indigo-900">
                            <span class="material-symbols-outlined text-[18px]">info</span> Required Columns Format (Tab or Comma Separated):
                        </p>
                        <p class="font-mono bg-white p-2 rounded border border-indigo-100 text-[10px]">
                            ID_Number &emsp; Fullname &emsp; Designation &emsp; Salary_Grade &emsp; Office_Acronym &emsp; Sub_Office (Optional) &emsp; Status (Optional)
                        </p>
                        <p class="leading-relaxed">
                            <strong>Note:</strong> Office acronym must exist in the database (e.g. <code>GSU</code>, <code>ASS</code>, <code>BAS</code>, <code>LO</code>). Status maps to <code>PERMANENT</code>, <code>CASUAL</code>, or <code>JO</code>. Matching on ID_Number will automatically update the existing employee's details.
                        </p>
                    </div>

                    {{-- Textarea input --}}
                    <div class="flex-1 flex flex-col">
                        <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider mb-1.5">Paste Data Here</label>
                        <textarea wire:model="bulkText" placeholder="20228900&#9;ANSHARI M. MANGONDATO&#9;Planning Officer III&#9;11&#9;PRU&#9;&#9;Regular&#10;20214900&#9;MARIAM S. AMPASO&#9;Financial Planning Assistant B&#9;7&#9;CU&#9;&#9;Regular"
                                  class="w-full h-48 px-4 py-3 border border-[#c3c6d1] rounded-xl text-xs font-mono focus:ring-2 focus:ring-[#001e40] outline-none transition-all resize-none leading-normal"></textarea>
                        @error('bulkText') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Bulk Results Report --}}
                    @if(!empty($bulkResults))
                        <div class="p-4 rounded-xl border {{ empty($bulkResults['skipped']) ? 'bg-green-50 border-green-200 text-green-950' : 'bg-amber-50 border-amber-200 text-amber-950' }} text-xs space-y-2">
                            <p class="font-bold flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px]">analytics</span>
                                Import Summary:
                            </p>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Successfully imported/updated: <strong>{{ $bulkResults['inserted'] }}</strong> employee(s).</li>
                                @if(!empty($bulkResults['skipped']))
                                    <li class="text-[#ba1a1a] font-semibold">Skipped rows: <strong>{{ count($bulkResults['skipped']) }}</strong>.</li>
                                @endif
                            </ul>
                            
                            @if(!empty($bulkResults['skipped']))
                                <div class="mt-2 max-h-32 overflow-y-auto bg-white p-3 rounded-lg border border-amber-100 font-mono text-[10px] text-[#ba1a1a] space-y-1 custom-scrollbar">
                                    @foreach($bulkResults['skipped'] as $skipErr)
                                        <p>{{ $skipErr }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Modal Footer --}}
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#c3c6d1] mt-2">
                        <button type="button" wire:click="$set('showBulkModal', false)"
                                class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="button" wire:click="importBulk"
                                class="px-5 py-2 bg-[#001e40] text-white text-sm font-bold rounded-lg hover:bg-[#003272] transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">publish</span>
                            Parse & Import
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ───────────────────────────────────────────────────────────
         Delete Confirmation Modal
    ─────────────────────────────────────────────────────────────── --}}
    @if($confirmingDeleteId)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('confirmingDeleteId', null)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3 text-[#ba1a1a]">
                        <span class="material-symbols-outlined text-[32px]">warning</span>
                        <h4 class="font-bold text-[#001e40] text-lg">Confirm Delete</h4>
                    </div>
                    <p class="text-sm text-[#43474f] leading-relaxed">
                        Are you sure you want to permanently delete this employee record? This action cannot be undone and will fail if the employee is registered as an active signatory.
                    </p>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('confirmingDeleteId', null)"
                                class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="button" wire:click="deleteEmployee"
                                class="px-5 py-2 bg-[#ba1a1a] text-white text-sm font-bold rounded-lg hover:bg-[#93000a] transition-colors flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                            Delete Record
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
