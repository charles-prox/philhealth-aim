<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Mount the component and check for system setup.
     */
    public function mount(): void
    {
        if (App\Models\User::count() === 0) {
            $this->redirect(route('register', absolute: false), navigate: true);
        }
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $user = auth()->user();

        if ($user->two_factor_enabled) {
            $user->generateTwoFactorCode();
            $this->redirect(route('verify-2fa', absolute: false), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-12 bg-white rounded-2xl shadow-2xl border border-[#c3c6d1] overflow-hidden">
    <!-- Branding/Visual Side (Asymmetric Layout) -->
    <div class="hidden md:flex md:col-span-7 bg-[#003366] relative p-12 flex-col justify-between overflow-hidden aspect-square">
        <div class="relative z-10">
            <div class="flex items-center gap-4 mb-8">
                <x-application-logo class="w-12 h-12 fill-current text-white" />
                <div class="flex flex-col">
                    <span class="text-xl font-semibold text-white tracking-tight">PhilHealth AIM</span>
                    <span class="text-[12px] text-white/70 font-bold uppercase tracking-widest">Region X</span>
                </div>
            </div>
            <h1 class="text-3xl font-semibold text-white max-w-md leading-tight mt-12">
                Advanced Inventory Management for Regional Resource Logistics.
            </h1>
            <p class="text-lg text-white/80 mt-4 max-w-sm font-normal">
                Ensuring precise tracking and institutional accountability for office supplies, equipment, and regional resources.
            </p>
        </div>
        <div class="relative z-10 flex flex-wrap gap-4 mt-auto">
            <!-- Badges Removed -->
        </div>
        <!-- Abstract Decorative Element -->
        <div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none">
            <svg class="w-full h-full" viewBox="0 0 400 400">
                <path class="text-white" d="M0,400 L400,0 L400,400 Z" fill="currentColor"></path>
            </svg>
        </div>
    </div>

    <!-- Login Form Side -->
    <div class="md:col-span-5 p-8 lg:p-12 flex flex-col justify-center bg-[#f9f9fe]">
        <div class="md:hidden flex items-center gap-4 mb-8">
            <x-application-logo class="w-8 h-8 fill-current text-[#001e40]" />
            <span class="text-xl font-bold text-[#001e40]">PhilHealth AIM</span>
        </div>
        
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-[#1a1c1f] mb-2">Portal Access</h2>
            <p class="text-sm text-[#43474f]">Enter your regional credentials to manage inventory resources.</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form wire:submit="login" class="flex flex-col gap-6">
            <!-- HRIS ID Input -->
            <x-form-input wire:model="form.username" label="HRIS ID (8-Digit)" icon="badge" placeholder="e.g. 12345678" required autofocus autocomplete="username" inputmode="numeric" maxlength="8" :error="$errors->first('form.username')" />

            <!-- Password Input -->
            <x-form-input wire:model="form.password" label="Password" icon="lock" type="password" placeholder="••••••••" required autocomplete="current-password" :error="$errors->first('form.password')">
                <x-slot:label_right>
                    @if (Route::has('password.request'))
                        <a class="text-[12px] text-[#001e40] font-bold hover:underline transition-all" href="{{ route('password.request') }}" wire:navigate>Forgot Password?</a>
                    @endif
                </x-slot:label_right>
            </x-form-input>

            <!-- Remember Me -->
            <div class="flex items-center gap-2">
                <input wire:model="form.remember" class="w-4 h-4 text-[#001e40] border-[#c3c6d1] rounded focus:ring-[#001e40]" id="remember" type="checkbox" name="remember" />
                <label class="text-sm text-[#43474f] cursor-pointer" for="remember">Stay signed in on this device</label>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-4 mt-2">
                <button wire:loading.attr="disabled" class="w-full bg-[#001e40] text-white py-3.5 px-6 rounded-lg font-semibold text-base hover:bg-[#003366] transition-all active:scale-[0.98] shadow-sm flex items-center justify-center gap-3 group" type="submit">
                    <span wire:loading.remove wire:target="login">Login to Dashboard</span>
                    <span wire:loading.flex wire:target="login" class="items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Verifying...</span>
                    </span>
                    <span wire:loading.remove wire:target="login" class="material-symbols-outlined transition-transform group-hover:translate-x-1">arrow_forward</span>
                </button>
            </div>
        </form>

        </form>
    </div>
</div>
