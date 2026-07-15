{{-- ================================================================
     RIGHT PANEL — Allocation Pane (slide-in)
================================================================ --}}
<div class="flex-shrink-0 flex flex-col bg-white rounded-2xl shadow-sm overflow-hidden transition-all duration-300 {{ $showPane ? 'w-[550px] border border-[#c3c6d1] opacity-100 translate-x-0' : 'w-0 border-none opacity-0 pointer-events-none translate-x-4' }}">

    @if($showPane && $selectedCobItem)
        {{-- Active Allocation Ledger Header --}}
        <div class="px-5 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe] space-y-4">
            <div class="flex justify-between items-start gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#43474f]">Active Allocation Ledger</p>
                    <h3 class="font-bold text-[#001e40] text-base leading-snug mt-1 line-clamp-2">
                        {{ $selectedCobItem->full_particulars ?? $selectedCobItem->exp_desc ?? '—' }}
                    </h3>
                    <p class="text-[11px] text-[#43474f] mt-1 font-mono">{{ $selectedCobItem->ppa_code }}</p>
                </div>
                <button wire:click="closePane" class="flex-shrink-0 p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] transition-all">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Real-Time Allocation Header / Capacity Bar --}}
            @php
                $recomQty  = (int) $selectedCobItem->recom_qty;
                $allocated = $selectedItemTotalAllocated;
                $remaining = $selectedItemRemainingToAllocate;
                $pct       = $recomQty > 0 ? min(100, round(($allocated / $recomQty) * 100)) : 0;
            @endphp
            <div class="p-3 bg-[#f4f3f8] border border-[#eeedf2] rounded-xl space-y-3">
                <div class="grid grid-cols-3 gap-2 text-center divide-x divide-[#c3c6d1]/40">
                    <div>
                        <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider">Total Quantity</p>
                        <p class="text-sm font-extrabold text-[#001e40] mt-0.5">{{ number_format($recomQty) }} <span class="text-[9px] font-normal text-[#43474f]">{{ $selectedCobItem->unit }}</span></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider">Allocated</p>
                        <p class="text-sm font-extrabold text-blue-700 mt-0.5">{{ number_format($allocated) }} <span class="text-[9px] font-normal text-[#43474f]">{{ $selectedCobItem->unit }}</span></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider">Remaining</p>
                        <p class="text-sm font-extrabold {{ $remaining > 0 ? 'text-[#001e40]' : 'text-green-700' }} mt-0.5">
                            {{ number_format($remaining) }} <span class="text-[9px] font-normal text-[#43474f]">{{ $selectedCobItem->unit }}</span>
                        </p>
                    </div>
                </div>
                
                {{-- Visual Progress Bar --}}
                <div class="space-y-1">
                    <div class="flex justify-between text-[9px] font-bold text-[#43474f] uppercase tracking-wider px-0.5">
                        <span>Allocation Progress</span>
                        <span class="{{ $pct >= 100 ? 'text-green-700' : 'text-[#001e40]' }} font-extrabold">{{ $pct }}%</span>
                    </div>
                    <div class="w-full h-2.5 bg-white border border-[#eeedf2] rounded-full overflow-hidden p-[1px]">
                        <div class="h-full rounded-full transition-all duration-500 {{ $pct >= 100 ? 'bg-green-500' : 'bg-[#001e40]' }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Single-Line Insertion Form --}}
        <div class="px-5 py-3 border-b border-[#c3c6d1] bg-[#f9f9fe] space-y-2">
            @error('newAllocation.office_id')
                <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
            @enderror
            @error('newAllocation.employee_id')
                <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
            @enderror
            @error('newAllocation.sub_employee_id')
                <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
            @enderror
            @error('newAllocation.quantity')
                <p class="text-[11px] text-[#ba1a1a] bg-[#ffdad6]/50 border border-[#ffdad6] rounded-lg px-3 py-1.5 font-bold">{{ $message }}</p>
            @enderror

            @if($remaining > 0)
                <div class="flex flex-col gap-3 p-3 rounded-xl border border-[#eeedf2] bg-[#f1f3f9]/50 shadow-inner" x-data="{
                    focusOffice() {
                        let btn = $el.querySelector('.office-select-trigger');
                        if (btn) btn.focus();
                    }
                 }" @allocation-added.window="focusOffice()">
                    
                    {{-- Row 1: Office & Accountable Officer (Regular) --}}
                    <div class="flex items-end gap-2.5">
                        {{-- Dropdown: Office --}}
                        <div class="flex-1 min-w-0">
                            <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Office</label>
                            <x-form-select 
                                label="" 
                                class="office-select-trigger"
                                wire:model.live="newAllocation.office_id"
                                :options="$this->offices"
                                placeholder="Select Office…"
                                searchable />
                        </div>

                        {{-- Dropdown: Accountable Officer (Regular) --}}
                        <div class="flex-1 min-w-0">
                            <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Accountable Officer (Regular)</label>
                            <x-form-select 
                                label="" 
                                wire:model.live="newAllocation.employee_id"
                                :options="$this->regularEmployees"
                                placeholder="Select Accountable..."
                                :disabled="!$newAllocation['office_id']"
                                searchable />
                        </div>
                    </div>

                    {{-- Row 2: Sub-User, Qty, & Add Button --}}
                    <div class="flex items-end gap-2.5">
                        {{-- Dropdown: Sub-End-User (Casual/JO) --}}
                        <div class="flex-1 min-w-0">
                            <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Sub-User (JO/Casual - Optional)</label>
                            <x-form-select 
                                label="" 
                                wire:model="newAllocation.sub_employee_id"
                                :options="$this->subEmployees"
                                placeholder="Optional (Casual/JO)"
                                :disabled="!$newAllocation['employee_id']"
                                searchable />
                        </div>

                        {{-- Qty Input --}}
                        <div class="w-16 flex-shrink-0">
                            <label class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider block mb-1">Qty</label>
                            <input type="number" 
                                   wire:model="newAllocation.quantity" 
                                   min="1" 
                                   max="{{ $remaining }}"
                                   placeholder="Qty"
                                   x-on:keydown.enter="$wire.addAllocation()"
                                   class="w-full px-3 py-2 bg-white border border-[#c3c6d1] rounded-lg text-sm font-bold focus:ring-2 focus:ring-[#001e40] outline-none text-[#1a1c1f] text-center h-[38px]"/>
                        </div>

                        {{-- Action Button --}}
                        <div class="flex-shrink-0">
                            <button wire:click="addAllocation"
                                    class="px-4 py-2 bg-[#001e40] text-white font-bold text-sm rounded-lg hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center justify-center gap-1 h-[38px] whitespace-nowrap">
                                <span class="material-symbols-outlined text-[18px]">add</span>Add
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-2 flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-green-700 text-[18px]">check_circle</span>
                    <p class="text-[11px] font-bold text-green-800 uppercase tracking-wider">All item quantities have been fully distributed.</p>
                </div>
            @endif
        </div>

        {{-- Immediate View Grid / Ledger List --}}
        <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-[#eeedf2]">
            @forelse($selectedCobItem->distributions()->whereNull('deleted_at')->with(['office','employee','subEmployee'])->get() as $dist)
                <div class="px-5 py-3 flex items-center gap-3 {{ $dist->is_locked ? 'bg-[#f9f9fe]' : 'hover:bg-[#fcfcff]' }} transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-bold text-[#1a1c1f] text-sm">{{ $dist->office->name ?? '—' }}</p>
                            @if($dist->is_locked)
                                <span class="px-2 py-0.5 bg-[#d5e3ff] text-[#001b3c] text-[9px] font-bold rounded-full uppercase tracking-wider flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[11px]">lock</span>Locked
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-col gap-1 mt-1">
                            @if($dist->employee)
                                <div class="flex items-center gap-1.5 text-[11px] text-[#43474f]">
                                    <span class="material-symbols-outlined text-[14px] text-[#001e40]/75">assignment_ind</span>
                                    <span class="font-semibold text-[#001b3c]">{{ $dist->employee->fullname }}</span>
                                    <span class="text-[9px] bg-[#d5e3ff] text-[#001b3c] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">Regular</span>
                                </div>
                            @else
                                <p class="text-[11px] text-[#43474f]/50 italic">General Office Stock (Pool)</p>
                            @endif

                            @if($dist->subEmployee)
                                <div class="flex items-center gap-1.5 text-[11px] text-[#43474f]">
                                    <span class="material-symbols-outlined text-[14px] text-[#43474f]/70 font-light">subdirectory_arrow_right</span>
                                    <span>User: <span class="font-medium text-[#1a1c1f]">{{ $dist->subEmployee->fullname }}</span></span>
                                    <span class="text-[9px] bg-[#f1f3f9] text-[#43474f] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">{{ $dist->subEmployee->employment_status }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="font-extrabold text-[#001e40] text-sm">{{ number_format($dist->allocated_quantity) }}</p>
                        <p class="text-[9px] text-[#43474f] font-bold uppercase tracking-wider">{{ $selectedCobItem->unit }}</p>
                    </div>
                    @if(!$dist->is_locked)
                        <button wire:click="removeAllocation('{{ $dist->id }}')"
                                wire:confirm="Remove this allocation?"
                                class="p-1.5 hover:bg-[#ffdad6] rounded-lg text-[#43474f] hover:text-[#ba1a1a] transition-all flex-shrink-0">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    @else
                        <div class="w-8 flex-shrink-0"></div>
                    @endif
                </div>
            @empty
                <div class="py-20 text-center text-[#43474f]">
                    <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">inventory_2</span>
                    <p class="text-[12px] font-bold text-[#001e40] mt-3">Ledger is Empty</p>
                    <p class="text-[11px] text-[#43474f] max-w-[240px] mx-auto mt-1">Select an office above to record the initial allocation.</p>
                </div>
            @endforelse
        </div>

    @else
        {{-- Pane placeholder (nothing selected yet) --}}
        <div class="flex-1 flex flex-col items-center justify-center gap-3 text-[#43474f] px-8 text-center bg-[#f9f9fe]">
            <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">arrow_back</span>
            <p class="font-bold text-[#001e40]">Active Allocation Ledger</p>
            <p class="text-[13px] max-w-[280px]">Select any budget item on the left to review its dynamic capacity, view existing distributions, or rapidly record a new allocation.</p>
        </div>
    @endif
</div>
