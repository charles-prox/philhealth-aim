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

    public function saveEmployee(\App\Services\AdminService $service): void
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

        $service->saveEmployee([
            'id_number'         => $this->empIdNumber,
            'fullname'          => $this->empFullname,
            'designation'       => $this->empDesignation,
            'salary_grade'      => $this->empSalaryGrade,
            'office_division'   => $this->empOfficeDivision,
            'sub_office'        => $this->empSubOffice,
            'employment_status' => $this->empStatus,
        ], $this->editingEmployeeId);

        session()->flash('status', $this->editingEmployeeId ? "Employee details updated successfully." : "Employee record created successfully.");
        $this->showEmployeeModal = false;
    }

    public function confirmDelete(int $empId): void
    {
        $this->confirmingDeleteId = $empId;
    }

    public function deleteEmployee(\App\Services\AdminService $service): void
    {
        if (!$this->confirmingDeleteId) return;

        try {
            $service->deleteEmployee($this->confirmingDeleteId);
            session()->flash('status', "Employee record deleted successfully.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->confirmingDeleteId = null;
    }

    public function openBulkModal(): void
    {
        $this->resetErrorBag();
        $this->bulkText = '';
        $this->bulkResults = [];
        $this->showBulkModal = true;
    }

    public function importBulk(\App\Services\AdminService $service): void
    {
        $this->validate([
            'bulkText' => 'required|string'
        ]);

        $results = $service->importBulkEmployees($this->bulkText);

        $this->bulkResults = $results;

        session()->flash('status', "Bulk import complete. Imported/updated {$results['inserted']} employee record(s).");
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

<div class="p-container-padding bg-background flex flex-col gap-6">

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

    @include('livewire.admin.partials.employee-kpis')

    @include('livewire.admin.partials.employee-table')

    @include('livewire.admin.partials.employee-modals')

</div>
