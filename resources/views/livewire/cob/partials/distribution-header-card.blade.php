{{-- Beautiful Header Summary Card --}}
<div class="mb-5 bg-white border border-[#c3c6d1] rounded-2xl p-5 shadow-sm flex items-center justify-between">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-[#d5e3ff] text-[#001b3c] flex items-center justify-center">
            <span class="material-symbols-outlined text-[28px] text-[#001b3c]">account_balance</span>
        </div>
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-base font-extrabold text-[#001e40] tracking-tight">Corporate Operating Budget</h1>
                <span class="px-2.5 py-0.5 bg-[#d5e3ff] text-[#001b3c] text-[10px] font-extrabold rounded-full uppercase tracking-wider">
                    Active: {{ $activeVersion->version_name }}
                </span>
                <span class="px-2.5 py-0.5 bg-[#f1f3f9] text-[#43474f] text-[10px] font-bold rounded-full uppercase tracking-wider">
                    FY{{ $activeVersion->budgetYear->fiscal_year ?? '—' }}
                </span>
            </div>
            <p class="text-[12px] text-[#43474f] mt-1">
                Map and distribute approved budget line allocations to designated office divisions, regular accountable officers, and floor users.
            </p>
        </div>
    </div>
    <div class="flex items-center gap-4">
        {{-- KPI 1: Total Budget --}}
        <div class="bg-[#f1f3f9]/50 border border-[#eeedf2] px-4 py-2 rounded-xl flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#d5e3ff] text-[#001b3c] flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">payments</span>
            </div>
            <div>
                <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider leading-none">Total Budget</p>
                <p class="text-sm font-extrabold text-[#001e40] mt-1 leading-none">₱{{ number_format($this->totalCobBudget, 2) }}</p>
            </div>
        </div>

        {{-- KPI 2: Total Allocated --}}
        <div class="bg-[#f1f3f9]/50 border border-[#eeedf2] px-4 py-2 rounded-xl flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#d5e3ff] text-[#001b3c] flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">inventory_2</span>
            </div>
            <div>
                <p class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider leading-none">Distributed</p>
                <p class="text-sm font-extrabold text-[#001e40] mt-1 leading-none">{{ number_format($this->totalAllocatedUnits) }} Units</p>
            </div>
        </div>
    </div>
</div>
