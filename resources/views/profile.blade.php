<x-app-layout>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#001e40] tracking-tight">{{ __('User Profile') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('Manage your account settings and security preferences') }}</p>
    </div>

    <div class="flex flex-col gap-6">
        <div class="card p-6 sm:p-8">
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>

        <div class="card p-6 sm:p-8">
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </div>

        <div class="card p-6 sm:p-8">
            <div class="max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
