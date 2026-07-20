<x-app-layout>
    @section('header_title', 'Accountability')

    @push('header_actions')
        <x-primary-button icon="assignment_add">Issue New PAR/ICS</x-primary-button>
    @endpush

    @php
        $assets = collect([
            ['id' => 'PH-IT-2023-0421', 'name' => 'MacBook Pro 14" M2',    'sub' => 'SN: C02DFX300MD6',      'custodian' => 'Enriquez, Maria Clara',       'section' => 'IT Management Section', 'date' => 'Oct 12, 2023', 'status' => 'Accounted',   'status_color' => 'bg-[#d5e3ff] text-[#001b3c]'],
            ['id' => 'PH-GEN-2021-0115','name' => 'Office Desk - L Shaped', 'sub' => 'Wood Finish, 180cm',    'custodian' => 'Dela Cruz, Juan',             'section' => 'Legal Services',        'date' => 'Jan 15, 2021', 'status' => 'For Disposal','status_color' => 'bg-[#ffdbca] text-[#341100]'],
            ['id' => 'PH-IT-2023-0501', 'name' => 'iPad Air 5th Gen',       'sub' => 'SN: R90X-K6M-77',       'custodian' => 'Santos, Roberto',             'section' => 'Field Operations',      'date' => 'Nov 03, 2023', 'status' => 'Missing',     'status_color' => 'bg-[#ffdad6] text-[#93000a]'],
            ['id' => 'PH-IT-2022-0988', 'name' => 'HP LaserJet Enterprise', 'sub' => 'Shared Network Printer', 'custodian' => 'Office of the Regional VP',   'section' => 'Executive Office',      'date' => 'Sep 22, 2022', 'status' => 'Accounted',   'status_color' => 'bg-[#d5e3ff] text-[#001b3c]'],
            ['id' => 'PH-GEN-2023-0012','name' => 'Steel Filing Cabinet',   'sub' => '4-Drawer, Fireproof',   'custodian' => 'Mendoza, Elena',              'section' => 'Human Resources',       'date' => 'Feb 14, 2023', 'status' => 'Accounted',   'status_color' => 'bg-[#d5e3ff] text-[#001b3c]'],
        ]);
        $hasData = $assets->count() > 0;
    @endphp

    <div class="p-container-padding bg-background flex flex-col gap-6">

        {{-- KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            @php $kpis = [
                ['label' => 'Total Assets Tracked', 'value' => $hasData ? '1,284' : null, 'sub' => '+12% from last month', 'icon' => 'inventory_2',    'icon_bg' => 'bg-[#001e40]/8',  'icon_color' => 'text-[#001e40]', 'vcolor' => 'text-[#001e40]', 'accent' => ''],
                ['label' => 'Active Custodians',    'value' => $hasData ? '412'   : null, 'sub' => '18 regional sections', 'icon' => 'groups',         'icon_bg' => 'bg-[#d8e1ea]/60', 'icon_color' => 'text-[#3a5f94]', 'vcolor' => 'text-[#001e40]', 'accent' => ''],
                ['label' => 'Pending Audit',        'value' => $hasData ? '28'    : null, 'sub' => 'Verification needed',  'icon' => 'verified_user',  'icon_bg' => 'bg-[#ffdbca]/50', 'icon_color' => 'text-[#592300]', 'vcolor' => 'text-[#592300]', 'accent' => ''],
                ['label' => 'For Disposal',         'value' => $hasData ? '14'    : null, 'sub' => 'Awaiting committee',   'icon' => 'delete_sweep',   'icon_bg' => 'bg-[#ffdad6]/50', 'icon_color' => 'text-[#ba1a1a]', 'vcolor' => 'text-[#ba1a1a]', 'accent' => 'border-l-4 border-l-[#ba1a1a]'],
            ] @endphp

            @foreach($kpis as $kpi)
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm {{ $kpi['accent'] }} flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">{{ $kpi['label'] }}</span>
                    <div class="w-9 h-9 {{ $kpi['icon_bg'] }} rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined {{ $kpi['icon_color'] }} text-[20px]">{{ $kpi['icon'] }}</span>
                    </div>
                </div>
                @if($kpi['value'] !== null)
                    <p class="text-3xl font-bold {{ $kpi['vcolor'] }}">{{ $kpi['value'] }}</p>
                    <p class="text-[11px] text-[#43474f] font-bold uppercase tracking-wider">{{ $kpi['sub'] }}</p>
                @else
                    <p class="text-3xl font-bold text-[#c3c6d1]">—</p>
                    <p class="text-[11px] text-[#c3c6d1] font-bold uppercase tracking-wider">No data yet</p>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Action Bar --}}
        <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl border border-[#c3c6d1] gap-4 shadow-sm">
            <div class="relative w-full md:w-96">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                <input type="text" placeholder="Search by Asset ID, Custodian, or Section..." class="w-full pl-10 pr-4 py-2.5 bg-[#f9f9fe] rounded-lg border border-[#c3c6d1] focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] text-sm outline-none transition-all placeholder-[#43474f]/40"/>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <x-primary-button variant="secondary" icon="sync">Bulk Audit Sync</x-primary-button>
                <x-primary-button variant="secondary" icon="description">Generate Report</x-primary-button>
                <x-primary-button variant="secondary" icon="filter_list" class="!px-3" />
            </div>
        </div>

        {{-- Assets Table --}}
        <div class="bg-white rounded-xl border border-[#c3c6d1] overflow-hidden shadow-sm">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                            <th class="px-gutter py-3 text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Asset ID</th>
                            <th class="px-gutter py-3 text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Item Description</th>
                            <th class="px-gutter py-3 text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Custodian</th>
                            <th class="px-gutter py-3 text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Section</th>
                            <th class="px-gutter py-3 text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Issued</th>
                            <th class="px-gutter py-3 text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                            <th class="px-gutter py-3 text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                        @forelse($assets as $asset)
                        <tr class="hover:bg-[#f4f3f8] transition-colors">
                            <td class="px-gutter py-4 font-bold text-[#1a1c1f]">{{ $asset['id'] }}</td>
                            <td class="px-gutter py-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-[#001e40]">{{ $asset['name'] }}</span>
                                    <span class="text-[11px] text-[#43474f]">{{ $asset['sub'] }}</span>
                                </div>
                            </td>
                            <td class="px-gutter py-4 text-[#1a1c1f]">{{ $asset['custodian'] }}</td>
                            <td class="px-gutter py-4 text-[#1a1c1f]">{{ $asset['section'] }}</td>
                            <td class="px-gutter py-4 text-[#43474f]">{{ $asset['date'] }}</td>
                            <td class="px-gutter py-4">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $asset['status_color'] }}">{{ $asset['status'] }}</span>
                            </td>
                            <td class="px-gutter py-4 text-right">
                                <div class="flex justify-end gap-1">
                                    <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all"><span class="material-symbols-outlined text-[20px]">edit</span></button>
                                    <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all"><span class="material-symbols-outlined text-[20px]">more_vert</span></button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">assignment_ind</span>
                                    <p class="font-bold text-[#001e40] text-lg">No Assets Recorded</p>
                                    <p class="text-[13px] text-[#43474f] max-w-xs">No accountable items have been issued yet. Use the button above to issue a PAR or ICS.</p>
                                    <x-primary-button icon="assignment_add" class="mt-2">Issue First PAR/ICS</x-primary-button>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($hasData)
            <div class="px-gutter py-4 bg-[#f9f9fe] flex justify-between items-center border-t border-[#c3c6d1]">
                <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing 1–{{ $assets->count() }} of 1,284 assets</span>
                <div class="flex gap-2">
                    <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg disabled:opacity-30" disabled><span class="material-symbols-outlined text-[20px]">chevron_left</span></button>
                    <button class="w-9 h-9 bg-[#001e40] text-white rounded-lg font-bold text-sm shadow-sm">1</button>
                    <button class="w-9 h-9 border border-[#c3c6d1] rounded-lg font-bold text-sm hover:bg-[#f4f3f8]">2</button>
                    <button class="w-9 h-9 border border-[#c3c6d1] rounded-lg font-bold text-sm hover:bg-[#f4f3f8]">3</button>
                    <span class="w-9 h-9 flex items-center justify-center text-[#43474f]">…</span>
                    <button class="w-9 h-9 border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8]"><span class="material-symbols-outlined text-[20px]">chevron_right</span></button>
                </div>
            </div>
            @endif
        </div>

        {{-- Contextual Insight Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
            {{-- Recent Transfers --}}
            <div class="lg:col-span-2 bg-white p-gutter rounded-xl border border-[#c3c6d1] shadow-sm">
                <h3 class="font-bold text-[#001e40] text-lg mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#001e40]">history</span>Recent Transfers
                </h3>
                @if($hasData)
                <div class="space-y-3">
                    @foreach([
                        ['icon' => 'sync_alt',         'bg' => 'bg-[#d8e1ea]', 'ic' => 'text-[#5b646b]', 'title' => 'Lenovo ThinkPad X1 Transfer',    'sub' => 'From Finance to Audit Section • Approved by J. Admin', 'time' => '2 hours ago'],
                        ['icon' => 'assignment_return', 'bg' => 'bg-[#eeedf2]', 'ic' => 'text-[#43474f]', 'title' => 'Return to Warehouse: Office Chair','sub' => 'Returned by S. Lopez • End of Term',                   'time' => 'Yesterday'],
                    ] as $t)
                    <div class="flex items-center gap-4 p-4 hover:bg-[#f9f9fe] transition-colors rounded-xl border border-transparent hover:border-[#c3c6d1]">
                        <div class="w-12 h-12 rounded-xl {{ $t['bg'] }} flex items-center justify-center {{ $t['ic'] }}">
                            <span class="material-symbols-outlined text-[24px]">{{ $t['icon'] }}</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-[#1a1c1f]">{{ $t['title'] }}</p>
                            <p class="text-[11px] text-[#43474f]">{{ $t['sub'] }}</p>
                        </div>
                        <span class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider whitespace-nowrap">{{ $t['time'] }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="py-12 text-center flex flex-col items-center gap-3">
                    <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">sync_alt</span>
                    <p class="font-bold text-[#001e40]">No Transfers Yet</p>
                    <p class="text-[13px] text-[#43474f]">Asset transfer history will appear here.</p>
                </div>
                @endif
            </div>

            {{-- Audit Progress Card --}}
            <div class="bg-[#003366] text-white p-gutter rounded-xl flex flex-col justify-between shadow-lg relative overflow-hidden group">
                <div class="relative z-10">
                    <h3 class="text-xl font-bold text-white mb-1">Q4 Audit Sync</h3>
                    <p class="text-[11px] text-white/70 font-bold uppercase tracking-wider">Regional Inventory Status</p>
                </div>
                <div class="my-8 relative z-10">
                    @if($hasData)
                    <div class="flex justify-between items-end mb-3">
                        <span class="text-3xl font-bold text-white">68%</span>
                        <span class="text-[11px] font-bold text-white/80 uppercase">873 / 1,284 items</span>
                    </div>
                    <div class="w-full bg-white/10 h-3 rounded-full overflow-hidden shadow-inner p-0.5">
                        <div class="bg-white h-full rounded-full" style="width: 68%"></div>
                    </div>
                    @else
                    <div class="flex flex-col items-center gap-2 py-4">
                        <span class="material-symbols-outlined text-[36px] text-white/30">hourglass_empty</span>
                        <p class="text-[12px] text-white/50 font-bold uppercase tracking-wider">No audit data yet</p>
                    </div>
                    @endif
                </div>
                <button class="w-full bg-white text-[#001e40] py-3 rounded-lg font-bold text-sm hover:bg-[#eeedf2] active:scale-[0.98] transition-all relative z-10 shadow-xl">
                    Resume Audit Session
                </button>
                <span class="material-symbols-outlined absolute -right-4 -top-4 text-[100px] text-white/5 group-hover:scale-110 transition-transform duration-700">verified_user</span>
            </div>
        </div>
    </div>

    <div class="fixed bottom-8 right-8 group">
        <button class="bg-[#001e40] text-white h-14 w-14 rounded-full shadow-2xl flex items-center justify-center active:scale-95 transition-all hover:w-52 hover:rounded-xl overflow-hidden relative">
            <span class="material-symbols-outlined absolute left-4">assignment_add</span>
            <span class="opacity-0 group-hover:opacity-100 whitespace-nowrap ml-10 font-bold text-sm tracking-tight transition-all duration-300">Issue New PAR/ICS</span>
        </button>
    </div>
</x-app-layout>
