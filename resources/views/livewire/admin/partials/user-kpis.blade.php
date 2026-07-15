{{-- KPI Strip --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-gutter">
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
    <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-indigo-700 text-[26px]">badge</span>
        </div>
        <div>
            <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Linked to HR</p>
            <p class="text-2xl font-bold text-indigo-700">{{ $linkedCount }}</p>
        </div>
    </div>
</div>
