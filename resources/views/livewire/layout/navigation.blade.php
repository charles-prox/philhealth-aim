<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $name = auth()->user()->name;
    $initials = collect(explode(' ', $name))
        ->map(fn($segment) => mb_substr($segment, 0, 1))
        ->take(2)
        ->join('');
@endphp

<aside class="h-screen fixed left-0 top-0 bg-white border-r border-[#c3c6d1] hidden md:flex flex-col gap-1 pt-4 z-50 transition-all duration-300"
       :class="sidebarCollapsed ? 'w-20' : 'w-64'">
    
    <!-- Brand Header -->
    <div class="px-4 mb-4">
        <div class="flex items-center gap-3 overflow-hidden">
            <div class="w-10 h-10 bg-[#003366] flex-shrink-0 flex items-center justify-center rounded shadow-sm">
                <span class="material-symbols-outlined text-white" style="font-variation-settings: 'FILL' 1;">shield</span>
            </div>
            <div x-show="!sidebarCollapsed" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-[-10px]" x-transition:enter-end="opacity-100 translate-x-0">
                <h2 class="text-xl font-bold text-[#001e40] leading-tight tracking-tight whitespace-nowrap">PhilHealth AIM</h2>
                <p class="text-[10px] text-[#43474f] font-bold uppercase tracking-widest whitespace-nowrap">Region X · Admin</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-1 overflow-y-auto custom-scrollbar">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard">
            Command Center
        </x-sidebar-link>

        <x-sidebar-link :href="route('procurement')" :active="request()->routeIs('procurement')" icon="shopping_cart">
            Procurement
        </x-sidebar-link>

        <x-sidebar-link :href="route('inventory')" :active="request()->routeIs('inventory')" icon="inventory_2">
            Inventory
        </x-sidebar-link>

        <x-sidebar-link :href="route('accountability')" :active="request()->routeIs('accountability')" icon="assignment_ind">
            Accountability
        </x-sidebar-link>

        <x-sidebar-link :href="route('repairs')" :active="request()->routeIs('repairs')" icon="build">
            Repairs
        </x-sidebar-link>

        <x-sidebar-link :href="route('reports')" :active="request()->routeIs('reports')" icon="assessment">
            Reports
        </x-sidebar-link>

        @role('Admin')
            {{-- COB Management Section --}}
            <div class="pt-4 pb-1 px-4" x-show="!sidebarCollapsed">
                <p class="text-[10px] uppercase tracking-widest text-[#43474f] font-bold">COB Management</p>
            </div>
            <div class="pt-4 pb-1 flex justify-center" x-show="sidebarCollapsed">
                <div class="w-8 h-[1px] bg-[#c3c6d1]"></div>
            </div>

            <x-sidebar-link :href="route('cob.kickoff')" :active="request()->routeIs('cob.*')" icon="account_balance">
                Annual Kick-off
            </x-sidebar-link>

            {{-- Administration Section --}}
            <div class="pt-4 pb-1 px-4" x-show="!sidebarCollapsed">
                <p class="text-[10px] uppercase tracking-widest text-[#43474f] font-bold">Administration</p>
            </div>
            <div class="pt-4 pb-1 flex justify-center" x-show="sidebarCollapsed">
                <div class="w-8 h-[1px] bg-[#c3c6d1]"></div>
            </div>

            <x-sidebar-link :href="route('admin.users')" :active="request()->routeIs('admin.users')" icon="manage_accounts">
                User Management
            </x-sidebar-link>
        @endrole
    </nav>
</aside>
