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

    public function mount(): void
    {
        if (!auth()->user()->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function toggle2FA(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->two_factor_enabled = !$user->two_factor_enabled;
        $user->save();

        $status = $user->two_factor_enabled ? 'enabled' : 'disabled';
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
        $this->showUserModal = true;
    }

    public function saveUser(): void
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
        ];

        if (!$this->editingUserId) {
            $rules['userPassword'] = ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()];
        } else {
            $rules['userPassword'] = ['nullable', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()];
        }

        $this->validate($rules);

        if ($this->selectedEmpId) {
            // Guard: prevent same employee being linked to two users
            $conflict = User::where('employee_id', $this->selectedEmpId)
                ->when($this->editingUserId, fn($q) => $q->where('id', '!=', $this->editingUserId))
                ->first();
            if ($conflict) {
                $this->addError('selectedEmpId', "That employee is already linked to user '{$conflict->name}'.");
                return;
            }
        }

        DB::transaction(function () {
            if ($this->editingUserId) {
                $user = User::findOrFail($this->editingUserId);
                $user->name = $this->userName;
                $user->username = $this->userUsername;
                $user->email = $this->userEmail;
                if (!empty($this->userPassword)) {
                    $user->password = Hash::make($this->userPassword);
                }
                $user->employee_id = $this->selectedEmpId;
                $user->save();

                $user->syncRoles($this->userRole);
                session()->flash('status', "User '{$user->name}' updated successfully.");
            } else {
                $user = User::create([
                    'name' => $this->userName,
                    'username' => $this->userUsername,
                    'email' => $this->userEmail,
                    'password' => Hash::make($this->userPassword),
                    'employee_id' => $this->selectedEmpId,
                ]);

                $user->assignRole($this->userRole);
                session()->flash('status', "User '{$user->name}' created successfully.");
            }
        });

        $this->showUserModal = false;
    }

    public function selectEmployee(int $empId): void
    {
        $this->selectedEmpId = $empId;
        $employee = Employee::find($empId);
        if ($employee && !$this->editingUserId) {
            // Only auto-fill if we are creating a new user
            $this->userName = $employee->fullname;
            
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
        return \Spatie\Permission\Models\Role::orderBy('name')->pluck('name');
    }

    public function with(): array
    {
        return [
            'users'         => User::with('employee')
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

<div class="p-container-padding bg-background space-y-6">

    {{-- Flash Status --}}
    @if (session('status'))
        <div class="flex items-center gap-3 p-4 bg-[#d5e3ff] border border-[#001e40]/20 text-[#001e40] rounded-xl text-sm font-bold shadow-sm">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            {{ session('status') }}
        </div>
    @endif

    {{-- KPI Strip --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-gutter">
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-[#eeedf2] rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-[#001e40] text-[26px]">group</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Total Users</p>
                <p class="text-2xl font-bold text-[#001e40]">{{ $totalUsers }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-green-700 text-[26px]">verified_user</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">2FA Active</p>
                <p class="text-2xl font-bold text-green-700">{{ $activeWith2FA }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-[#ffdad6] rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-[#ba1a1a] text-[26px]">lock_open</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#ba1a1a] uppercase tracking-wider">Without 2FA</p>
                <p class="text-2xl font-bold text-[#ba1a1a]">{{ $totalUsers - $activeWith2FA }}</p>
            </div>
        </div>
        <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-indigo-700 text-[26px]">badge</span>
            </div>
            <div>
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Linked to HR</p>
                <p class="text-2xl font-bold text-indigo-700">{{ $linkedCount }}</p>
            </div>
        </div>
    </div>

    {{-- Users Table Card --}}
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden flex flex-col relative">

        <div wire:loading wire:target="search" class="absolute inset-x-0 bottom-0 top-[57px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
            <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
            </div>
        </div>

        {{-- Table Header --}}
        <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap justify-between items-center gap-4">
            <h3 class="font-h2 text-h2 text-[#001e40]">System Users</h3>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name or username..."
                           class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] outline-none transition-all w-64 placeholder-[#43474f]/40"/>
                </div>
                <button wire:click="openCreateModal" class="flex items-center gap-2 bg-[#001e40] hover:bg-[#003272] text-white px-4 py-2.5 rounded-lg text-sm font-bold shadow-sm transition-all">
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                    New User
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">User</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Username</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Role</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">HR Employee</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">2FA</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                    @forelse ($users as $user)
                        @php
                            $initials = collect(explode(' ', $user->name))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->implode('');
                            $role = $user->getRoleNames()->first() ?? 'No Role';
                            $roleColors = [
                                'Admin'               => 'bg-[#001e40] text-white',
                                'Procurement Officer' => 'bg-[#d5e3ff] text-[#001b3c]',
                                'Canvasser'           => 'bg-[#e2f1e4] text-[#1c3822]',
                                'Inventory Manager'   => 'bg-[#f4e4cf] text-[#4d2d00]',
                                'Budget Officer'      => 'bg-[#e8def8] text-[#21005d]',
                                'Admin Head'          => 'bg-[#ffd8e4] text-[#31111d]',
                                'MSD Head'            => 'bg-[#ffdad6] text-[#410002]',
                                'Auditor'             => 'bg-[#d8e1ea] text-[#2c3135]',
                                'Document custodian'  => 'bg-[#e8f0fe] text-[#1a73e8]',
                                'Office Head'         => 'bg-[#f3e8ff] text-[#6b21a8]',
                            ];
                            $roleClass = $roleColors[$role] ?? 'bg-[#eeedf2] text-[#43474f]';
                        @endphp
                        <tr class="hover:bg-[#f4f3f8] transition-colors group">
                            {{-- User --}}
                            <td class="p-table-cell-padding">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-[#001e40] text-white flex items-center justify-center font-bold text-[12px] flex-shrink-0 shadow-sm">
                                        {{ $initials }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#001e40]">{{ $user->name }}</span>
                                        <span class="text-[11px] text-[#43474f]">{{ $user->email ?? '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            {{-- Username --}}
                            <td class="p-table-cell-padding">
                                <span class="font-mono font-bold text-[#1a1c1f] tracking-wider">{{ $user->username }}</span>
                            </td>
                            {{-- Role --}}
                            <td class="p-table-cell-padding">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $roleClass }}">
                                    {{ $role }}
                                </span>
                            </td>
                            {{-- HR Employee Link --}}
                            <td class="p-table-cell-padding">
                                @if($user->employee)
                                    <div class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-indigo-600" style="font-variation-settings: 'FILL' 1;">badge</span>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-[#001e40] text-[12px]">{{ $user->employee->fullname }}</span>
                                            <span class="text-[10px] text-[#43474f]">{{ $user->employee->designation ?? '—' }}</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="flex items-center gap-1 text-[#ba1a1a] font-bold text-[11px] uppercase tracking-wide">
                                        <span class="material-symbols-outlined text-[15px]">link_off</span> Not Linked
                                    </span>
                                @endif
                            </td>
                            {{-- 2FA Status --}}
                            <td class="p-table-cell-padding">
                                @if($user->two_factor_enabled)
                                    <div class="flex items-center gap-2 text-green-700 font-bold">
                                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">verified_user</span>
                                        <span class="text-[12px] uppercase tracking-wider">On</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-[#ba1a1a] font-bold">
                                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                                        <span class="text-[12px] uppercase tracking-wider">Off</span>
                                    </div>
                                @endif
                            </td>
                            {{-- Actions --}}
                            <td class="p-table-cell-padding text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="openEditModal({{ $user->id }})"
                                            title="Edit User details & role"
                                            class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800 transition-all">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button wire:click="toggle2FA({{ $user->id }})"
                                            title="{{ $user->two_factor_enabled ? 'Disable 2FA' : 'Enable 2FA' }}"
                                            class="p-1.5 rounded-lg transition-all hover:bg-[#eeedf2] {{ $user->two_factor_enabled ? 'text-[#ba1a1a] hover:text-[#93000a]' : 'text-green-600 hover:text-green-800' }}">
                                        <span class="material-symbols-outlined text-[20px]">{{ $user->two_factor_enabled ? 'lock_open' : 'lock' }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-gutter py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                    <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">person_search</span>
                                    <p class="font-bold text-[#001e40]">No users found</p>
                                    <p class="text-[13px]">Try a different search term.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="p-gutter border-t border-[#c3c6d1] bg-[#f9f9fe] flex justify-between items-center">
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                {{ $users->count() }} {{ Str::plural('user', $users->count()) }} listed
            </p>
            <div class="flex items-center gap-2 text-[12px] font-bold text-[#43474f]">
                <span class="material-symbols-outlined text-[16px] text-[#001e40]">admin_panel_settings</span>
                Administration Panel · Region X
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────────────
         Unified User Modal (Create & Edit)
    ─────────────────────────────────────────────────────────────── --}}
    @if($showUserModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showUserModal', false)">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe]">
                    <div>
                        <h3 class="font-bold text-[#001e40] text-[16px]">
                            {{ $editingUserId ? 'Edit User: ' . $userName : 'Create New User' }}
                        </h3>
                        <p class="text-[12px] text-[#43474f]">
                            {{ $editingUserId ? 'Update account, user role, and HR link' : 'Register a new user account' }}
                        </p>
                    </div>
                    <button wire:click="$set('showUserModal', false)" class="p-1.5 rounded-lg text-[#43474f] hover:bg-[#eeedf2] transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <form wire:submit.prevent="saveUser" class="flex-1 overflow-y-auto max-h-[80vh] p-6 space-y-4 custom-scrollbar">
                    
                    {{-- HR Employee Linkage --}}
                    <div class="p-4 bg-indigo-50/50 border border-indigo-200 rounded-xl space-y-3">
                        <div class="flex items-center gap-2 text-indigo-900 font-bold text-[13px] border-b border-indigo-200 pb-2">
                            <span class="material-symbols-outlined text-[20px] text-indigo-700" style="font-variation-settings: 'FILL' 1;">badge</span>
                            <span>HRIS Employee Profile Link</span>
                        </div>

                        {{-- Current Choice --}}
                        @if($selectedEmpId)
                            @php $linkedEmp = \App\Models\Employee::find($selectedEmpId); @endphp
                            <div class="flex items-center gap-3 p-3 bg-white border border-indigo-200 rounded-lg">
                                <span class="material-symbols-outlined text-[24px] text-indigo-600" style="font-variation-settings: 'FILL' 1;">badge</span>
                                <div class="flex flex-col">
                                    <span class="font-bold text-[#001e40] text-[13px]">{{ $linkedEmp?->fullname }}</span>
                                    <span class="text-[11px] text-[#43474f]">{{ $linkedEmp?->designation }} · {{ $linkedEmp?->office_division }}</span>
                                </div>
                                <button type="button" wire:click="$set('selectedEmpId', null)"
                                        class="ml-auto text-xs font-bold text-[#ba1a1a] hover:underline flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">link_off</span> Remove
                                </button>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-[#ba1a1a] font-bold text-[11px] uppercase tracking-wide">
                                <span class="material-symbols-outlined text-[16px]">link_off</span> No employee record linked.
                            </div>
                        @endif

                        {{-- Search Employee --}}
                        @if(!$selectedEmpId)
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider mb-1">Search HR Database</label>
                                    <div class="relative">
                                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                                        <input wire:model.live.debounce.300ms="employeeSearch"
                                               type="text"
                                               placeholder="Type 2+ letters to filter..."
                                               class="w-full pl-9 pr-4 py-2 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                                    </div>
                                </div>

                                @if(strlen($employeeSearch) >= 2)
                                    <div class="border border-[#c3c6d1] bg-white rounded-lg overflow-hidden max-h-48 overflow-y-auto custom-scrollbar">
                                        @forelse($this->matchingEmployees as $emp)
                                            <button type="button" wire:click="selectEmployee({{ $emp->id }})"
                                                    class="w-full text-left px-3 py-2 flex items-center gap-2 transition-colors hover:bg-[#f4f3f8] border-b border-[#c3c6d1] last:border-0">
                                                <span class="material-symbols-outlined text-[18px] text-[#c3c6d1]">badge</span>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-[#001e40] text-[12px]">{{ $emp->fullname }}</span>
                                                    <span class="text-[10px] text-[#43474f]">{{ $emp->designation }} · {{ $emp->office_division }}</span>
                                                </div>
                                            </button>
                                        @empty
                                            <div class="p-4 text-center text-[12px] text-[#43474f]">No matches.</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error('selectedEmpId') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Basic Profile Info --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Full Name</label>
                            <input wire:model="userName" type="text" required placeholder="e.g. Juan D. Dela Cruz"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('userName') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">HRIS ID (8-Digit)</label>
                            <input wire:model="userUsername" type="text" required maxlength="8" placeholder="e.g. 12345678"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('userUsername') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Professional Email</label>
                            <input wire:model="userEmail" type="email" required placeholder="user@philhealth.gov.ph"
                                   class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            @error('userEmail') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">System Role</label>
                            <select wire:model="userRole" required
                                    class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all">
                                @foreach($this->systemRoles as $roleOption)
                                    <option value="{{ $roleOption }}">{{ $roleOption }}</option>
                                @endforeach
                            </select>
                            @error('userRole') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Passwords --}}
                    <div class="p-4 bg-[#f4f3f8] border border-[#c3c6d1] rounded-xl space-y-4">
                        <div class="flex items-center gap-2 text-[#001e40] font-bold text-[13px] border-b border-[#c3c6d1] pb-2">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                            <span>Security Password</span>
                            @if($editingUserId)
                                <span class="text-[11px] font-normal text-[#43474f] ml-auto">(Leave blank to keep current password)</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider mb-1">Password</label>
                                <input wire:model="userPassword" type="password" placeholder="••••••••"
                                       class="w-full px-3 py-2 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                                @error('userPassword') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider mb-1">Confirm Password</label>
                                <input wire:model="userPassword_confirmation" type="password" placeholder="••••••••"
                                       class="w-full px-3 py-2 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#c3c6d1]">
                        <button type="button" wire:click="$set('showUserModal', false)"
                                class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#001e40] text-white text-sm font-bold rounded-lg hover:bg-[#003272] transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            {{ $editingUserId ? 'Save Changes' : 'Create Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
