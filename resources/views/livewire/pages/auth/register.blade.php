<?php

use App\Models\Office;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $employee_id = '';
    public string $unit = '';
    public array $offices = [];

    /**
     * Mount the component and check if registration is allowed.
     */
    public function mount(): void
    {
        if (User::count() > 0) {
            $this->redirect(route('login', absolute: false), navigate: true);
        }

        // Load offices from DB as a flat sorted list of acronyms
        $this->offices = Office::orderBy('acronym')
            ->pluck('acronym', 'acronym')
            ->all();
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'size:8', 'regex:/^[0-9]+$/', 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'unit' => ['required', 'string', 'exists:offices,acronym'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // Resolve the office acronym to its primary key
        $office = Office::where('acronym', $validated['unit'])->first();
        unset($validated['unit']);
        if ($office) {
            $validated['office_id'] = $office->id;
        }

        $isFirstUser = User::count() === 0;
        
        $user = User::create($validated);

        if ($isFirstUser) {
            $user->assignRole('Admin');
        }

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-[1000px] grid grid-cols-1 md:grid-cols-12 bg-white rounded-2xl shadow-2xl border border-[#c3c6d1] overflow-hidden">
    <!-- Left Panel: Visual/Context -->
    <div class="md:col-span-5 bg-[#001e40] relative overflow-hidden hidden md:flex flex-col p-10 justify-between">
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <img class="w-full h-full object-cover" src="{{ asset('images/register-bg.png') }}" alt="PhilHealth Background">
        </div>
        <div class="relative z-10">
            <span class="material-symbols-outlined text-[#799dd6] text-4xl mb-4">admin_panel_settings</span>
            <h2 class="text-2xl font-bold text-white mb-2">Admin Setup</h2>
            <p class="text-sm text-[#799dd6] leading-relaxed">
                Configure the primary system administrator account for PhilHealth Region X resources.
            </p>
        </div>
        <div class="relative z-10">
            <div class="flex flex-col gap-4">
                <div class="flex items-start gap-3">
                    <div class="mt-1 w-2 h-2 rounded-full bg-[#799dd6]"></div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#799dd6]">Full System Oversight</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="mt-1 w-2 h-2 rounded-full bg-[#799dd6]"></div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#799dd6]">User Access Management</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="mt-1 w-2 h-2 rounded-full bg-[#799dd6]"></div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#799dd6]">Institutional Data Integrity</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel: The Form -->
    <div class="md:col-span-7 p-8 md:p-12">
        <div class="mb-8">
            <h3 class="text-2xl font-bold text-[#1a1c1f]">Admin Registration</h3>
            <p class="text-sm text-[#43474f]">Set up your administrative credentials to initialize the system.</p>
        </div>

        <form wire:submit="register" class="flex flex-col gap-6">
            <!-- Full Name -->
            <x-form-input wire:model="name" label="Full Name" icon="person" placeholder="e.g. Juan D. Dela Cruz" required autofocus autocomplete="name" :error="$errors->first('name')" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Username (HRIS ID) -->
                <x-form-input wire:model="username" label="HRIS ID (8-Digit)" icon="badge" placeholder="e.g. 12345678" required autocomplete="username" inputmode="numeric" maxlength="8" :error="$errors->first('username')" />
                
                <!-- Unit/Section -->
                <x-form-select wire:model="unit" label="Unit / Section" icon="corporate_fare" required :searchable="true" :error="$errors->first('unit')" :options="$offices" />
            </div>

            <!-- Email -->
            <x-form-input wire:model="email" label="Professional Email" icon="mail" type="email" placeholder="admin@philhealth.gov.ph" required autocomplete="username" :error="$errors->first('email')" />

            <!-- Password -->
            <x-form-input wire:model="password" label="Create Password" icon="lock" type="password" placeholder="••••••••" required autocomplete="new-password" :error="$errors->first('password')" />

            <!-- Confirm Password -->
            <x-form-input wire:model="password_confirmation" label="Confirm Password" icon="lock_reset" type="password" placeholder="••••••••" required autocomplete="new-password" :error="$errors->first('password_confirmation')" />

            <button wire:loading.attr="disabled" class="mt-4 w-full bg-[#001e40] text-white font-bold py-4 rounded-xl hover:bg-[#003366] transition-all flex items-center justify-center gap-2 shadow-lg active:scale-[0.98]" type="submit">
                <span wire:loading.remove wire:target="register">Finalize Admin Account</span>
                <span wire:loading.flex wire:target="register" class="items-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Initializing...</span>
                </span>
                <span wire:loading.remove wire:target="register" class="material-symbols-outlined text-base">how_to_reg</span>
            </button>
        </form>
    </div>
</div>
