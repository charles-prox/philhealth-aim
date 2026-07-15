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
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Office / Department</th>
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
                            'Regional Vice President' => 'bg-[#ffdcc0] text-[#301400]',
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
                                    <span class="text-[11px] text-[#43474f]">{{ $user->employee?->designation ?? ($user->email ?? '—') }}</span>
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
                        {{-- Office Link --}}
                        <td class="p-table-cell-padding">
                            @if($user->office)
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-[#43474f]">corporate_fare</span>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#001e40] text-[12px]">{{ $user->office->acronym ?? $user->office->name }}</span>
                                        <span class="text-[10px] text-[#43474f] truncate max-w-[150px]">{{ $user->office->name }}</span>
                                    </div>
                                </div>
                            @else
                                <span class="flex items-center gap-1 text-[#ba1a1a] font-bold text-[11px] uppercase tracking-wide">
                                    <span class="material-symbols-outlined text-[15px]">corporate_fare</span> No Office
                                </span>
                            @endif
                        </td>
                        {{-- 2FA Status --}}
                        <td class="p-table-cell-padding">
                            <button type="button" wire:click="toggle2FA({{ $user->id }})" class="focus:outline-none">
                                @if($user->two_factor_enabled)
                                    <div class="flex items-center gap-2 text-green-700 font-bold hover:opacity-80 transition-opacity">
                                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">verified_user</span>
                                        <span class="text-[12px] uppercase tracking-wider">On</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-[#ba1a1a] font-bold hover:opacity-80 transition-opacity">
                                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                                        <span class="text-[12px] uppercase tracking-wider">Off</span>
                                    </div>
                                @endif
                            </button>
                        </td>
                        {{-- Actions --}}
                        <td class="p-table-cell-padding text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button wire:click="openEditModal({{ $user->id }})" class="p-1.5 rounded-lg text-[#001e40] hover:bg-[#eeedf2] transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-[#43474f] italic">
                            No system users matched the filters.
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
