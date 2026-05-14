<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $code = '';

    /**
     * Handle the 2FA verification.
     */
    public function verify(): void
    {
        $this->validate([
            'code' => ['required', 'numeric', 'digits:6'],
        ]);

        $user = Auth::user();

        if ($user->two_factor_code === $this->code && $user->two_factor_expires_at->isFuture()) {
            $user->resetTwoFactorCode();
            
            Session::put('2fa_verified', true);

            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
        } else {
            $this->addError('code', __('The provided two-factor authentication code was invalid or has expired.'));
        }
    }

    /**
     * Resend the 2FA code.
     */
    public function resend(): void
    {
        $user = Auth::user();
        $user->generateTwoFactorCode();
        // Send email here in real scenario
        $this->dispatch('code-resent');
    }
    
    public function logout(): void
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
    <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
        <div class="mb-6 text-sm text-[#43474f] leading-relaxed">
            {{ __('Please enter the 6-digit authentication code sent to your email to continue.') }}
        </div>

        <form wire:submit="verify">
            <!-- 2FA Code -->
            <div>
                <x-input-label for="code" :value="__('Authentication Code')" class="label-caps mb-2" />
                <x-text-input wire:model="code" id="code" class="block mt-1 w-full text-center text-3xl tracking-[0.5em] font-black text-[#001e40] border-[#c3c6d1] focus:ring-[#001e40] focus:border-[#001e40]" type="text" maxlength="6" required autofocus />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div class="flex flex-col space-y-4 mt-8">
                <x-primary-button class="w-full justify-center py-3">
                    {{ __('Verify Identity') }}
                </x-primary-button>
                
                <div class="flex items-center justify-between">
                    <button type="button" wire:click="resend" class="text-xs font-bold uppercase tracking-widest text-[#001e40] hover:underline">
                        {{ __('Resend Code') }}
                    </button>

                    <button type="button" wire:click="logout" class="text-xs font-bold uppercase tracking-widest text-[#737780] hover:text-[#1a1c1f]">
                        {{ __('Sign Out') }}
                    </button>
                </div>
            </div>
        </form>
        
        <div x-data="{ show: false }" x-on:code-resent.window="show = true; setTimeout(() => show = false, 3000)" x-show="show" x-transition class="mt-6 p-3 bg-[#f4f3f8] text-[#001e40] text-[11px] rounded border border-[#001e40] text-center font-bold uppercase tracking-widest">
            {{ __('Security code has been re-dispatched') }}
        </div>
    </div>
</div>
