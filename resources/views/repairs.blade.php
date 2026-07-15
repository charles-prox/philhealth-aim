<x-app-layout>
    @section('header_title', 'Repairs & Maintenance')

    @push('header_actions')
        <x-primary-button icon="add">New Repair Request</x-primary-button>
    @endpush

    @php
        $jobs = collect([
            ['jo' => 'JO-2023-1042', 'asset' => 'IT-LPT-00821', 'desc' => 'Display flickering and vertical lines after system update.', 'urgency' => 'High',   'urgency_color' => 'bg-[#ffdad6] text-[#ba1a1a]', 'status' => 'In-Progress',  'status_type' => 'progress'],
            ['jo' => 'JO-2023-1045', 'asset' => 'OFF-CHR-0021', 'desc' => 'Broken hydraulic mechanism, seat no longer height adjustable.', 'urgency' => 'Low', 'urgency_color' => 'bg-[#ffdbca] text-[#723610]', 'status' => 'Pending',      'status_type' => 'pending'],
            ['jo' => 'JO-2023-0988', 'asset' => 'IT-SRV-0002',  'desc' => 'Critical power supply failure during regional outage.',       'urgency' => 'High',   'urgency_color' => 'bg-[#ffdad6] text-[#ba1a1a]', 'status' => 'Unrepairable', 'status_type' => 'error'],
            ['jo' => 'JO-2023-1011', 'asset' => 'IT-PRN-0012',  'desc' => 'Continuous paper jam in Tray 2. Rollers may need replacement.','urgency' => 'Medium','urgency_color' => 'bg-[#d8e1ea] text-[#5b646b]',  'status' => 'Completed',    'status_type' => 'done'],
        ]);
        $hasData = $jobs->count() > 0;
    @endphp

    <div class="p-container-padding bg-background flex flex-col gap-6">

        {{-- KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            @php $kpis = [
                ['label' => 'Total Active',     'value' => $hasData ? 24 : null, 'icon' => 'engineering',   'icon_bg' => 'bg-[#001e40]/8',  'icon_color' => 'text-[#001e40]', 'vcolor' => 'text-[#001e40]', 'sub' => 'Open job orders',    'accent' => ''],
                ['label' => 'Urgent Requests',  'value' => $hasData ? 7  : null, 'icon' => 'priority_high', 'icon_bg' => 'bg-[#ffdad6]/60', 'icon_color' => 'text-[#ba1a1a]', 'vcolor' => 'text-[#ba1a1a]', 'sub' => 'Requires attention', 'accent' => 'border-l-4 border-l-[#ba1a1a]'],
                ['label' => 'In Progress',      'value' => $hasData ? 12 : null, 'icon' => 'settings_suggest','icon_bg' => 'bg-[#ffdbca]/50','icon_color' => 'text-[#d8885c]','vcolor' => 'text-[#d8885c]', 'sub' => 'Under repair',       'accent' => ''],
                ['label' => 'Unrepairable',     'value' => $hasData ? 3  : null, 'icon' => 'report',        'icon_bg' => 'bg-[#d8e1ea]/60', 'icon_color' => 'text-[#575f67]', 'vcolor' => 'text-[#575f67]', 'sub' => 'For disposal queue', 'accent' => ''],
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
        <div class="flex flex-wrap items-end justify-between gap-4 bg-white p-4 rounded-xl border border-[#c3c6d1] shadow-sm">
            <div class="flex flex-wrap items-center gap-6 flex-1">
                <div class="w-64">
                    <x-form-input label="Job Order Number" placeholder="e.g. JO-2023-001" icon="tag" />
                </div>
                <div class="w-64">
                    <x-form-select label="Assigned Technician" icon="person" placeholder="All Technicians"
                        :options="['all' => 'All Technicians','santos' => 'Engr. Santos','ramos' => 'Tech. Ramos','cruz' => 'Admin Cruz']" />
                </div>
                <x-primary-button variant="secondary" icon="filter_list" class="mb-0.5">Clear Filters</x-primary-button>
            </div>
        </div>

        {{-- Job Orders Table --}}
        <div class="bg-white rounded-xl border border-[#c3c6d1] overflow-hidden shadow-sm">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left table-auto border-collapse">
                    <thead>
                        <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">JO Number</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Asset ID</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Issue Description</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Urgency</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                        @forelse($jobs as $job)
                        <tr class="hover:bg-[#f4f3f8] transition-colors">
                            <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $job['jo'] }}</td>
                            <td class="p-table-cell-padding font-medium text-[#1a1c1f]">{{ $job['asset'] }}</td>
                            <td class="p-table-cell-padding max-w-xs truncate text-[#43474f]">{{ $job['desc'] }}</td>
                            <td class="p-table-cell-padding">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $job['urgency_color'] }}">{{ $job['urgency'] }}</span>
                            </td>
                            <td class="p-table-cell-padding">
                                @if($job['status_type'] === 'progress')
                                    <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-[#d8885c] animate-pulse"></div><span class="font-medium text-[#1a1c1f]">In-Progress</span></div>
                                @elseif($job['status_type'] === 'pending')
                                    <div class="flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-[#737780]"></div><span class="font-medium text-[#43474f]">Pending</span></div>
                                @elseif($job['status_type'] === 'error')
                                    <div class="flex items-center gap-2 text-[#ba1a1a] font-bold"><span class="material-symbols-outlined text-[18px]">dangerous</span><span>Unrepairable</span></div>
                                @elseif($job['status_type'] === 'done')
                                    <div class="flex items-center gap-2 text-green-700 font-bold"><span class="material-symbols-outlined text-[18px]" style="font-variation-settings:'FILL' 1;">check_circle</span><span>Completed</span></div>
                                @endif
                            </td>
                            <td class="p-table-cell-padding text-right">
                                <div class="flex justify-end gap-1">
                                    <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                                    <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all"><span class="material-symbols-outlined text-[20px]">edit</span></button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">build_circle</span>
                                    <p class="font-bold text-[#001e40] text-lg">No Repair Requests Found</p>
                                    <p class="text-[13px] text-[#43474f] max-w-xs">No job orders have been filed yet. Submit a new repair request to begin tracking asset maintenance.</p>
                                    <x-primary-button icon="add" class="mt-2">File First Repair Request</x-primary-button>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($hasData)
            <div class="bg-[#f9f9fe] px-gutter py-3 flex items-center justify-between border-t border-[#c3c6d1]">
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing 1 to {{ $jobs->count() }} of 24 entries</p>
                <div class="flex items-center gap-2">
                    <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg disabled:opacity-30" disabled><span class="material-symbols-outlined text-[20px]">chevron_left</span></button>
                    <button class="w-9 h-9 bg-[#001e40] text-white rounded-lg font-bold text-sm shadow-sm">1</button>
                    <button class="w-9 h-9 border border-[#c3c6d1] rounded-lg font-bold text-sm hover:bg-[#f4f3f8]">2</button>
                    <button class="w-9 h-9 border border-[#c3c6d1] rounded-lg font-bold text-sm hover:bg-[#f4f3f8]">3</button>
                    <button class="w-9 h-9 border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8]"><span class="material-symbols-outlined text-[20px]">chevron_right</span></button>
                </div>
            </div>
            @endif
        </div>

        {{-- Maintenance Insights --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter">
            <div class="bg-white p-gutter rounded-xl border border-[#c3c6d1] shadow-sm">
                <h4 class="font-bold text-[#001e40] text-lg mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined">pie_chart</span>Repair Categories
                </h4>
                @if($hasData)
                <div class="space-y-5">
                    @foreach([['IT Equipment','65%','bg-[#001e40]','text-[#001e40]'],['Office Furniture','25%','bg-[#d8885c]','text-[#d8885c]'],['Utility / HVAC','10%','bg-[#575f67]','text-[#575f67]']] as [$cat,$pct,$bar,$text])
                    <div class="space-y-2">
                        <div class="flex justify-between text-[12px] font-bold text-[#43474f] uppercase tracking-wider">
                            <span>{{ $cat }}</span><span class="{{ $text }}">{{ $pct }}</span>
                        </div>
                        <div class="w-full bg-[#eeedf2] rounded-full h-2.5 overflow-hidden shadow-inner">
                            <div class="{{ $bar }} h-full rounded-full transition-all duration-1000" style="width: {{ $pct }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="py-12 text-center flex flex-col items-center gap-3">
                    <span class="material-symbols-outlined text-[48px] text-[#c3c6d1]">pie_chart</span>
                    <p class="font-bold text-[#001e40]">No Category Data</p>
                    <p class="text-[13px] text-[#43474f]">Repair category breakdown will appear once job orders are filed.</p>
                </div>
                @endif
            </div>
            <div class="relative rounded-xl overflow-hidden border border-[#c3c6d1] h-full shadow-sm">
                <img class="absolute inset-0 w-full h-full object-cover" src="{{ asset('images/repairs-bg.png') }}" alt="Technical workshop"/>
                <div class="absolute inset-0 bg-gradient-to-t from-[#001e40]/90 via-[#001e40]/30 to-transparent flex flex-col justify-end p-5">
                    <p class="text-white font-bold text-base mb-0.5">System Health Overview</p>
                    <p class="text-white/80 text-[12px] leading-relaxed">
                        @if($hasData)
                            Inventory maintenance is currently 92% operational for Region X facilities.
                        @else
                            No maintenance data has been logged yet for this region.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
