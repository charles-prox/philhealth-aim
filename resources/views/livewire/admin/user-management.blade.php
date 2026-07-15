<?php

use App\Models\User;
use App\Models\Employee;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component
{
    public string $search = '';

    // Unified Create/Edit User Modal State
    public bool $showUserModal = false;
    public ?int $editingUserId = null; // null = creating, int = editing
    public string $userName = '';
    public string $userUsername = '';
    public string $userEmail = '';
    public string $userPassword = '';
    public string $userPassword_confirmation = '';
    public string $userRole = '';
    
    // Linking employee state
    public string $employeeSearch = '';
    public ?int $selectedEmpId = null;
    
    // Lineage Office state
    public ?int $userOfficeId = null;

    public function mount(): void
    {
        if (!auth()->user()->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function toggle2FA(int $userId, \App\Services\AdminService $service): void
    {
        $enabled = $service->toggle2FA($userId);
        $user = User::findOrFail($userId);
        $status = $enabled ? 'enabled' : 'disabled';
        session()->flash('status', "Two-factor authentication successfully {$status} for {$user->name}.");
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->editingUserId = null;
        $this->userName = '';
        $this->userUsername = '';
        $this->userEmail = '';
        $this->userPassword = '';
        $this->userPassword_confirmation = '';
        $this->userRole = 'Procurement Officer';
        $this->selectedEmpId = null;
        $this->employeeSearch = '';
        $this->userOfficeId = null;
        $this->showUserModal = true;
    }

    public function openEditModal(int $userId): void
    {
        $this->resetErrorBag();
        $user = User::with('employee')->findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->userName = $user->name;
        $this->userUsername = $user->username;
        $this->userEmail = $user->email;
        $this->userPassword = '';
        $this->userPassword_confirmation = '';
        $this->userRole = $user->getRoleNames()->first() ?? 'Procurement Officer';
        $this->selectedEmpId = $user->employee_id;
        $this->employeeSearch = $user->employee?->fullname ?? '';
        $this->userOfficeId = $user->office_id;
        $this->showUserModal = true;
    }

    public function saveUser(\App\Services\AdminService $service): void
    {
        $rules = [
            'userName' => ['required', 'string', 'max:255'],
            'userUsername' => [
                'required',
                'string',
                'size:8',
                'regex:/^[0-9]+$/',
                $this->editingUserId ? 'unique:users,username,' . $this->editingUserId : 'unique:users,username'
            ],
            'userEmail' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                $this->editingUserId ? 'unique:users,email,' . $this->editingUserId : 'unique:users,email'
            ],
            'userRole' => ['required', 'string', 'exists:roles,name'],
            'userOfficeId' => ['required', 'integer', 'exists:offices,id'],
        ];

        if (!$this->editingUserId) {
            $rules['userPassword'] = ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()];
        } else {
            $rules['userPassword'] = ['nullable', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()];
        }

        $this->validate($rules);

        try {
            $user = $service->saveUser([
                'name' => $this->userName,
                'username' => $this->userUsername,
                'email' => $this->userEmail,
                'password' => $this->userPassword,
                'employee_id' => $this->selectedEmpId,
                'office_id' => $this->userOfficeId,
                'role' => $this->userRole,
            ], $this->editingUserId);

            session()->flash('status', $this->editingUserId ? "User '{$user->name}' updated successfully." : "User '{$user->name}' created successfully.");
            $this->showUserModal = false;
        } catch (\Exception $e) {
            $this->addError('selectedEmpId', $e->getMessage());
        }
    }

    public function selectEmployee(int $empId): void
    {
        $this->selectedEmpId = $empId;
        $employee = Employee::find($empId);
        if ($employee) {
            $office = \App\Models\Office::where('acronym', $employee->office_division)->first();
            if ($office) {
                $this->userOfficeId = $office->id;
            }

            if (!$this->editingUserId) {
                // Only auto-fill if we are creating a new user
                $this->userName = $employee->fullname;

                // Auto-fill HRIS ID from the employee's stored id_number
                if (!empty($employee->id_number)) {
                    $this->userUsername = $employee->id_number;
                }
                
                // Suggest professional email if currently blank
                if (empty($this->userEmail)) {
                    $firstWord = explode(' ', $employee->fullname)[0] ?? '';
                    $cleanedName = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstWord));
                    if ($cleanedName) {
                        $this->userEmail = $cleanedName . '@philhealth.gov.ph';
                    }
                }
            }
        }
    }

    #[Computed]
    public function matchingEmployees(): \Illuminate\Support\Collection
    {
        if (strlen($this->employeeSearch) < 2) {
            return collect();
        }
        return Employee::where(DB::raw('LOWER(fullname)'), 'like', '%' . strtolower($this->employeeSearch) . '%')
            ->orderBy('fullname')
            ->limit(10)
            ->get(['id', 'fullname', 'designation', 'office_division']);
    }

    #[Computed]
    public function systemRoles(): \Illuminate\Support\Collection
    {
        return \Spatie\Permission\Models\Role::orderBy('name')->pluck('name', 'name');
    }

    #[Computed]
    public function hierarchicalOffices(): array
    {
        return \App\Models\Office::orderBy('name')
            ->get()
            ->mapWithKeys(function ($office) {
                $label = $office->name . ($office->acronym ? " ({$office->acronym})" : "");
                return [$office->id => $label];
            })
            ->toArray();
    }

    public function with(): array
    {
        return [
            'users'         => User::with(['employee', 'office'])
                ->when($this->search, fn($q) => $q
                    ->where(fn($sub) => $sub
                        ->where(DB::raw('LOWER(name)'), 'like', '%' . strtolower($this->search) . '%')
                        ->orWhere('username', 'like', "%{$this->search}%")
                    )
                )
                ->get(),
            'totalUsers'    => User::count(),
            'activeWith2FA' => User::where('two_factor_enabled', true)->count(),
            'linkedCount'   => User::whereNotNull('employee_id')->count(),
        ];
    }
}; ?>

<div class="p-container-padding bg-background ">

    {{-- Flash Status --}}
    @if (session('status'))
        <div class="flex items-center gap-3 p-4 bg-[#d5e3ff] border border-[#001e40]/20 text-[#001e40] rounded-xl text-sm font-bold shadow-sm">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    @include('livewire.admin.partials.user-kpis')

    @include('livewire.admin.partials.user-table')

    @include('livewire.admin.partials.user-modals')

</div>
