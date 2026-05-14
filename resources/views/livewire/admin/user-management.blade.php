<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public string $search = '';

    /**
     * Mount the component and check for admin.
     */
    public function mount(): void
    {
        if (!auth()->user()->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Toggle 2FA for a user.
     */
    public function toggle2FA(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->two_factor_enabled = !$user->two_factor_enabled;
        $user->save();
        
        $status = $user->two_factor_enabled ? 'enabled' : 'disabled';
        session()->flash('status', "Two-factor authentication successfully {$status} for {$user->name}.");
    }

    /**
     * Get users for the management list.
     */
    public function with(): array
    {
        return [
            'users' => User::where('id', '!=', auth()->id())
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('username', 'like', "%{$this->search}%"))
                ->get(),
            'totalUsers' => User::count(),
            'activeWith2FA' => User::where('two_factor_enabled', true)->count(),
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
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
    </div>

    {{-- Users Table Card --}}
    <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden flex flex-col">

        {{-- Table Header --}}
        <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap justify-between items-center gap-4">
            <h3 class="font-h2 text-h2 text-[#001e40]">System Users</h3>
            <div class="flex items-center gap-3">
                {{-- Search --}}
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                    <input wire:model.live="search" type="text" placeholder="Search by name or HRIS ID..." 
                           class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] outline-none transition-all w-64 placeholder-[#43474f]/40"/>
                </div>
                <x-primary-button icon="person_add">
                    New User
                </x-primary-button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">User</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">HRIS ID</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Role</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">2FA Security</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Last Login</th>
                        <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                    @forelse ($users as $user)
                        @php
                            $initials = collect(explode(' ', $user->name))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->implode('');
                            $role = $user->getRoleNames()->first() ?? 'No Role';
                            $roleColors = [
                                'Admin'          => 'bg-[#001e40] text-white',
                                'Supply Officer' => 'bg-[#d5e3ff] text-[#001b3c]',
                                'Property Officer' => 'bg-[#d8e1ea] text-[#5b646b]',
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
                            {{-- HRIS ID --}}
                            <td class="p-table-cell-padding">
                                <span class="font-mono font-bold text-[#1a1c1f] tracking-wider">{{ $user->username }}</span>
                            </td>
                            {{-- Role --}}
                            <td class="p-table-cell-padding">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $roleClass }}">
                                    {{ $role }}
                                </span>
                            </td>
                            {{-- 2FA Status --}}
                            <td class="p-table-cell-padding">
                                @if($user->two_factor_enabled)
                                    <div class="flex items-center gap-2 text-green-700 font-bold">
                                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">verified_user</span>
                                        <span class="text-[12px] uppercase tracking-wider">Enabled</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-[#ba1a1a] font-bold">
                                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                                        <span class="text-[12px] uppercase tracking-wider">Disabled</span>
                                    </div>
                                @endif
                            </td>
                            {{-- Last Login --}}
                            <td class="p-table-cell-padding text-[#43474f]">
                                {{ $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->diffForHumans() : 'Never' }}
                            </td>
                            {{-- Actions --}}
                            <td class="p-table-cell-padding text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="toggle2FA({{ $user->id }})"
                                            title="{{ $user->two_factor_enabled ? 'Disable 2FA' : 'Enable 2FA' }}"
                                            class="p-1.5 rounded-lg transition-all hover:bg-[#eeedf2] {{ $user->two_factor_enabled ? 'text-[#ba1a1a] hover:text-[#93000a]' : 'text-green-600 hover:text-green-800' }}">
                                        <span class="material-symbols-outlined text-[20px]">{{ $user->two_factor_enabled ? 'lock_open' : 'lock' }}</span>
                                    </button>
                                    <button title="Edit User" class="p-1.5 rounded-lg text-[#43474f] hover:bg-[#eeedf2] hover:text-[#001e40] transition-all">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button title="More Options" class="p-1.5 rounded-lg text-[#43474f] hover:bg-[#eeedf2] hover:text-[#001e40] transition-all">
                                        <span class="material-symbols-outlined text-[20px]">more_vert</span>
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

</div>
