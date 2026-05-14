<x-app-layout>
    @section('header_title', 'Procurement')

    @push('header_actions')
        <x-primary-button icon="add">New PR</x-primary-button>
    @endpush

    {{-- Page variables (replace with real data when connected) --}}
    @php
        $prs = collect([
            ['id' => 'PR-2024-00124', 'date' => 'Oct 12, 2024', 'supplier' => 'Advantage Medical Systems', 'status' => 'PENDING',   'delivered' => 0,  'total' => 50,  'status_color' => 'bg-[#ffdbca] text-[#341100]'],
            ['id' => 'PR-2024-00120', 'date' => 'Oct 10, 2024', 'supplier' => 'Global Tech Solutions Inc.', 'status' => 'RFQ_SENT', 'delivered' => 45, 'total' => 100, 'status_color' => 'bg-[#d8e1ea] text-[#5b646b]'],
            ['id' => 'PO-2024-00088', 'date' => 'Oct 08, 2024', 'supplier' => 'PaperCo Philippines',       'status' => 'DELIVERED', 'delivered' => 200,'total' => 200, 'status_color' => 'bg-[#d5e3ff] text-[#001b3c]'],
        ]);
    @endphp

    <div class="p-container-padding bg-background space-y-6">

        {{-- KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            @php $kpis = [
                ['label' => 'Active Requests',    'value' => $prs->count() > 0 ? 42   : null, 'sub' => '+5% from last week',    'icon' => 'description',     'icon_bg' => 'bg-[#001e40]/8',   'icon_color' => 'text-[#001e40]', 'trend' => 'up',   'trend_color' => 'text-green-700'],
                ['label' => 'Total PR Value',     'value' => $prs->count() > 0 ? '₱1.24M' : null, 'sub' => 'Fiscal Year 2024', 'icon' => 'account_balance_wallet', 'icon_bg' => 'bg-[#d5e3ff]/60', 'icon_color' => 'text-[#1f477b]', 'trend' => null,   'trend_color' => ''],
                ['label' => 'Pending Approval',   'value' => $prs->count() > 0 ? 12   : null, 'sub' => 'Immediate attention',   'icon' => 'pending_actions',  'icon_bg' => 'bg-[#ffdad6]/60', 'icon_color' => 'text-[#ba1a1a]', 'trend' => 'alert','trend_color' => 'text-[#ba1a1a]'],
                ['label' => 'Avg Turnaround',     'value' => $prs->count() > 0 ? '5.2d': null, 'sub' => 'Within KPI target',    'icon' => 'timer',            'icon_bg' => 'bg-green-50',      'icon_color' => 'text-green-700', 'trend' => 'check', 'trend_color' => 'text-green-700'],
            ] @endphp

            @foreach($kpis as $kpi)
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm">
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">{{ $kpi['label'] }}</span>
                    <div class="w-9 h-9 {{ $kpi['icon_bg'] }} rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined {{ $kpi['icon_color'] }} text-[20px]">{{ $kpi['icon'] }}</span>
                    </div>
                </div>
                @if($kpi['value'] !== null)
                    <p class="text-3xl font-bold text-[#001e40]">{{ $kpi['value'] }}</p>
                    <p class="text-[11px] {{ $kpi['trend_color'] ?: 'text-[#43474f]' }} mt-2 font-bold uppercase tracking-wider flex items-center gap-1">
                        @if($kpi['trend'] === 'up')    <span class="material-symbols-outlined text-[14px]">trending_up</span>
                        @elseif($kpi['trend'] === 'alert') <span class="material-symbols-outlined text-[14px]">warning</span>
                        @elseif($kpi['trend'] === 'check') <span class="material-symbols-outlined text-[14px]">check_circle</span>
                        @endif
                        {{ $kpi['sub'] }}
                    </p>
                @else
                    <p class="text-3xl font-bold text-[#c3c6d1]">—</p>
                    <p class="text-[11px] text-[#c3c6d1] mt-2 font-bold uppercase tracking-wider">No data yet</p>
                @endif
            </div>
            @endforeach
        </div>

        {{-- PR Table --}}
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden">
            <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                <h3 class="font-bold text-[#001e40] text-lg">Purchase Request Tracker</h3>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                        <input type="text" placeholder="Search PR or supplier..." class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all w-56 placeholder-[#43474f]/40"/>
                    </div>
                    <x-primary-button variant="secondary" icon="filter_list" class="!px-3" />
                    <x-primary-button variant="secondary" icon="download" class="!px-3" />
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">PR Number</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Requested</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Supplier Name</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider w-56">Delivery Progress</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                        @forelse($prs as $pr)
                        <tr class="hover:bg-[#f4f3f8] transition-colors">
                            <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $pr['id'] }}</td>
                            <td class="p-table-cell-padding text-[#1a1c1f]">{{ $pr['date'] }}</td>
                            <td class="p-table-cell-padding text-[#1a1c1f]">{{ $pr['supplier'] }}</td>
                            <td class="p-table-cell-padding">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $pr['status_color'] }}">{{ $pr['status'] }}</span>
                            </td>
                            <td class="p-table-cell-padding">
                                @php $pct = $pr['total'] > 0 ? round(($pr['delivered']/$pr['total'])*100) : 0; @endphp
                                <div class="space-y-1">
                                    <div class="flex justify-between text-[10px] font-bold text-[#43474f]">
                                        <span>{{ $pr['delivered'] }} / {{ $pr['total'] }} Units</span>
                                        <span>{{ $pct }}%</span>
                                    </div>
                                    <div class="w-full h-2 bg-[#eeedf2] rounded-full overflow-hidden">
                                        <div class="h-full bg-[#001e40] rounded-full transition-all duration-700" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-table-cell-padding text-right">
                                <div class="flex justify-end gap-1">
                                    <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                                    <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all"><span class="material-symbols-outlined text-[20px]">more_vert</span></button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                    <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">receipt_long</span>
                                    <p class="font-bold text-[#001e40] text-lg">No Purchase Requests Found</p>
                                    <p class="text-[13px] text-[#43474f] max-w-xs">There are no procurement requests yet. Create the first one to start tracking your purchasing pipeline.</p>
                                    <x-primary-button icon="add" class="mt-2">Create First PR</x-primary-button>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($prs->count() > 0)
            <div class="p-gutter border-t border-[#c3c6d1] flex items-center justify-between bg-[#f9f9fe]">
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing 1 to {{ $prs->count() }} of 42 PRs</p>
                <div class="flex gap-2">
                    <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8] transition-all disabled:opacity-30" disabled><span class="material-symbols-outlined text-[20px]">chevron_left</span></button>
                    <button class="w-9 h-9 flex items-center justify-center bg-[#001e40] text-white rounded-lg font-bold text-sm shadow-sm">1</button>
                    <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8] transition-all font-bold text-sm">2</button>
                    <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8] transition-all"><span class="material-symbols-outlined text-[20px]">chevron_right</span></button>
                </div>
            </div>
            @endif
        </div>

        {{-- Insight Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
            <div class="bg-[#d5e3ff]/30 p-8 border border-[#001e40]/10 rounded-2xl relative overflow-hidden group shadow-sm">
                <div class="relative z-10">
                    <h4 class="text-xl font-bold text-[#001e40] mb-2">Delivery Bottlenecks Detected</h4>
                    <p class="text-sm text-[#43474f] max-w-md leading-relaxed">3 awarded purchase requests from "Global Tech Solutions Inc." are exceeding the expected delivery timeline. Consideration for alternative RFQs recommended.</p>
                    <x-primary-button class="mt-5">Resolve Delays</x-primary-button>
                </div>
                <span class="material-symbols-outlined absolute -right-6 -bottom-6 text-[140px] text-[#001e40]/5 group-hover:scale-110 transition-transform duration-700" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
            </div>
            <div class="bg-white p-8 border border-[#c3c6d1] rounded-2xl shadow-sm flex gap-6 items-start">
                <div class="w-24 h-24 rounded-2xl bg-[#eeedf2] flex items-center justify-center flex-shrink-0 shadow-inner">
                    <span class="material-symbols-outlined text-[48px] text-[#43474f]">inventory</span>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="text-xl font-bold text-[#1a1c1f]">Regional Stock Utilization</h4>
                        <span class="px-3 py-1 bg-[#ffdad6] text-[#93000a] text-[10px] font-bold rounded-full uppercase">LOW STOCK</span>
                    </div>
                    <p class="text-sm text-[#43474f] mb-5 leading-relaxed">Critical medical supplies in Warehouse Sector B are below the 15% threshold. Initiate procurement cycle for FY24 Q4.</p>
                    <div class="w-full h-2 bg-[#eeedf2] rounded-full overflow-hidden">
                        <div class="h-full bg-[#ba1a1a] rounded-full" style="width: 15%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
