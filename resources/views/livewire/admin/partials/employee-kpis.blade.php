{{-- KPI Strip --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-gutter">
    <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 bg-[#eeedf2] rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[#001e40] text-[26px]">badge</span>
        </div>
        <div>
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Total Records</p>
            <p class="text-2xl font-bold text-[#001e40]">{{ $totalCount }}</p>
        </div>
    </div>
    <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-green-700 text-[26px]">assignment_turned_in</span>
        </div>
        <div>
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Permanent</p>
            <p class="text-2xl font-bold text-green-700">{{ $permanentCount }}</p>
        </div>
    </div>
    <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-indigo-700 text-[26px]">assignment_ind</span>
        </div>
        <div>
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Casual</p>
            <p class="text-2xl font-bold text-indigo-700">{{ $casualCount }}</p>
        </div>
    </div>
    <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-amber-700 text-[26px]">engineering</span>
        </div>
        <div>
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Job Orders</p>
            <p class="text-2xl font-bold text-amber-700">{{ $joCount }}</p>
        </div>
    </div>
</div>
