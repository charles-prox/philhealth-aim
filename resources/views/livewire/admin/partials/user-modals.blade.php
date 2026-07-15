{{-- Unified User Modal (Create & Edit) --}}
@if($showUserModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showUserModal', false)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">

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
                        <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">
                            HRIS ID (8-Digit)
                            @if($selectedEmpId && !empty($userUsername))
                                <span class="ml-1 inline-flex items-center gap-0.5 text-[10px] font-bold text-[#1a6b3a] bg-[#d4f0e0] border border-[#a3d9b8] rounded-full px-2 py-0.5 normal-case tracking-normal">
                                    <span class="material-symbols-outlined text-[12px]">auto_fix_high</span>
                                    Auto-filled
                                </span>
                            @endif
                        </label>
                        <input wire:model="userUsername" type="text" required maxlength="8" placeholder="e.g. 12345678"
                               @if($selectedEmpId && !empty($userUsername)) readonly @endif
                               class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all
                                      {{ ($selectedEmpId && !empty($userUsername)) ? 'border-[#a3d9b8] bg-[#f0fbf5] text-[#1a6b3a] font-semibold cursor-not-allowed' : 'border-[#c3c6d1]' }}"/>
                        @error('userUsername') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Hierarchical Office Selector --}}
                <div>
                    <x-form-select wire:model="userOfficeId" label="Office / Department Assignment" icon="corporate_fare" required placeholder="-- Select Office / Department --" :options="$this->hierarchicalOffices" searchable />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Professional Email</label>
                        <input wire:model="userEmail" type="email" required placeholder="user@philhealth.gov.ph"
                               class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                        @error('userEmail') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-form-select wire:model="userRole" label="System Role" icon="manage_accounts" required placeholder="-- Select System Role --" :options="$this->systemRoles" :error="$errors->first('userRole')" />
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
