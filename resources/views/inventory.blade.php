<x-app-layout>
    @section('header_title', 'Warehouse Ledger')

    @php
        $entries = collect([
            ['date' => 'Oct 01, 2023', 'ref' => 'Balance Forwarded', 'sub' => null,                      'in' => null, 'out' => null, 'balance' => 500, 'recipient' => null,           'type' => 'forward'],
            ['date' => 'Oct 05, 2023', 'ref' => 'PO #23-0442',       'sub' => 'Supplier: MegaTech',       'in' => 200,  'out' => null, 'balance' => 700, 'recipient' => 'Warehouse A',  'type' => 'in'],
            ['date' => 'Oct 08, 2023', 'ref' => 'RIS #RX-2023-901',  'sub' => 'Req: Membership Div',      'in' => null, 'out' => 150,  'balance' => 550, 'recipient' => 'Membership',   'type' => 'out'],
        ]);
        $hasData = $entries->count() > 0;
    @endphp

    <div class="p-container-padding bg-background space-y-6">

        {{-- KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            @php $kpis = [
                ['label' => 'Current Balance', 'value' => $hasData ? '452 Units' : null, 'sub' => '+12% from last month', 'icon' => 'inventory_2',   'icon_bg' => 'bg-[#001e40]/8',   'icon_color' => 'text-[#001e40]', 'badge' => null,                      'badge_color' => ''],
                ['label' => 'Total In (Oct)',   'value' => $hasData ? '1,200'     : null, 'sub' => null,                  'icon' => 'arrow_downward', 'icon_bg' => 'bg-green-50',       'icon_color' => 'text-green-700', 'badge' => 'In Stock',                'badge_color' => 'bg-green-50 text-green-700 border-green-100'],
                ['label' => 'Total Out (Oct)',  'value' => $hasData ? '748'       : null, 'sub' => null,                  'icon' => 'arrow_upward',   'icon_bg' => 'bg-[#d5e3ff]/60',  'icon_color' => 'text-[#001e40]', 'badge' => 'RSMI Pending: 2',         'badge_color' => 'bg-blue-50 text-blue-700 border-blue-100'],
                ['label' => 'Reorder Point',   'value' => $hasData ? '100'       : null, 'sub' => 'Status: Optimal',     'icon' => 'warning_amber',  'icon_bg' => 'bg-[#ffdad6]/60',  'icon_color' => 'text-[#ba1a1a]', 'badge' => null,                      'badge_color' => ''],
            ] @endphp

            @foreach($kpis as $i => $kpi)
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm {{ $i === 3 ? 'border-l-4 border-l-[#ba1a1a]' : '' }} flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider {{ $i === 3 ? 'text-[#ba1a1a]' : '' }}">{{ $kpi['label'] }}</span>
                    <div class="w-8 h-8 {{ $kpi['icon_bg'] }} rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined {{ $kpi['icon_color'] }} text-[18px]">{{ $kpi['icon'] }}</span>
                    </div>
                </div>
                @if($kpi['value'] !== null)
                    <p class="text-2xl font-bold text-[#001e40]">{{ $kpi['value'] }}</p>
                    @if($kpi['badge'])
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $kpi['badge_color'] }} w-fit uppercase">{{ $kpi['badge'] }}</span>
                    @elseif($kpi['sub'])
                        <p class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-[#001e40]">trending_up</span>{{ $kpi['sub'] }}
                        </p>
                    @endif
                @else
                    <p class="text-2xl font-bold text-[#c3c6d1]">—</p>
                    <p class="text-[11px] text-[#c3c6d1] font-bold uppercase tracking-wider">No data yet</p>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="grid grid-cols-12 gap-form-gap items-end bg-white p-gutter rounded-xl border border-[#c3c6d1] shadow-sm">
            <div class="col-span-12 md:col-span-2">
                <x-form-select label="Item Category" icon="category" placeholder="All Categories"
                    :options="['office' => 'Office Supplies','medical' => 'Medical Supplies','it' => 'IT Equipment','maintenance' => 'Maintenance']" />
            </div>
            <div class="col-span-12 md:col-span-2">
                <x-form-select label="Stock No." icon="inventory_2" placeholder="All Items"
                    :options="['001' => 'OS-2023-001','002' => 'OS-2023-002','003' => 'OS-2023-003']" />
            </div>
            <div class="col-span-12 md:col-span-4">
                <div class="flex flex-col gap-2">
                    <label class="text-[12px] text-[#43474f] font-bold uppercase tracking-wide">Date Range (For RSMI)</label>
                    <div class="flex items-center gap-2">
                        <input type="date" value="2023-10-01" class="flex-1 py-2.5 bg-white border border-[#c3c6d1] rounded-lg focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] transition-all text-sm px-4"/>
                        <span class="text-[#43474f] font-bold text-xs uppercase">to</span>
                        <input type="date" value="2023-10-31" class="flex-1 py-2.5 bg-white border border-[#c3c6d1] rounded-lg focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] transition-all text-sm px-4"/>
                    </div>
                </div>
            </div>
            <div class="col-span-12 md:col-span-4 flex justify-end gap-3">
                <x-primary-button variant="secondary" icon="search">Filter</x-primary-button>
                <x-primary-button icon="description">Generate RSMI</x-primary-button>
                <x-primary-button variant="secondary" icon="print" class="!px-3" />
            </div>
        </div>

        {{-- Ledger Table --}}
        <div class="bg-white rounded-xl border border-[#c3c6d1] flex flex-col overflow-hidden shadow-sm">
            <div class="p-gutter border-b border-[#c3c6d1] flex justify-between items-center bg-[#f9f9fe]">
                <h4 class="font-bold text-[#001e40] text-lg">Digital Stock Card
                    <span class="font-normal text-[#43474f] text-sm ml-2 italic">{{ $hasData ? 'Item: OS-2023-001' : 'No item selected' }}</span>
                </h4>
                <div class="flex gap-3">
                    <x-primary-button variant="secondary" icon="download">Export RSMI</x-primary-button>
                    <x-primary-button icon="add">New Entry</x-primary-button>
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse table-fixed">
                    <thead>
                        <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider w-28">Date</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Reference / Particulars</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center w-24 bg-[#d5e3ff]/30">In</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center w-24 bg-[#ffdad6]/30">Out</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center w-24 bg-green-50/30">Balance</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider w-36">Recipient</th>
                            <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center w-16">Act.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                        @forelse($entries as $row)
                        <tr class="hover:bg-[#f4f3f8] transition-colors">
                            <td class="p-table-cell-padding text-[#1a1c1f]">{{ $row['date'] }}</td>
                            <td class="p-table-cell-padding">
                                <div class="flex flex-col">
                                    <span class="font-semibold text-[#001e40]">{{ $row['ref'] }}</span>
                                    @if($row['sub'])<span class="text-[11px] {{ $row['type'] === 'out' ? 'text-[#ba1a1a]' : 'text-[#43474f]' }}">{{ $row['sub'] }}</span>@endif
                                </div>
                            </td>
                            <td class="p-table-cell-padding text-center {{ $row['type'] === 'in' ? 'font-bold text-[#001e40] bg-[#d5e3ff]/10' : '' }}">{{ $row['in'] ?? '—' }}</td>
                            <td class="p-table-cell-padding text-center {{ $row['type'] === 'out' ? 'font-bold text-[#ba1a1a] bg-[#ffdad6]/10' : '' }}">{{ $row['out'] ?? '—' }}</td>
                            <td class="p-table-cell-padding text-center font-bold text-[#001e40]">{{ $row['balance'] }}</td>
                            <td class="p-table-cell-padding text-[#43474f] truncate">{{ $row['recipient'] ?? '—' }}</td>
                            <td class="p-table-cell-padding text-center">
                                <button class="p-1.5 hover:bg-white rounded-lg text-[#43474f] hover:text-[#001e40] transition-all"><span class="material-symbols-outlined text-[18px]">{{ $row['type'] === 'in' ? 'edit' : 'visibility' }}</span></button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">inventory_2</span>
                                    <p class="font-bold text-[#001e40] text-lg">No Ledger Entries</p>
                                    <p class="text-[13px] text-[#43474f] max-w-xs">Select an item and date range above, or log the first stock entry to begin tracking.</p>
                                    <x-primary-button icon="add" class="mt-2">Log First Entry</x-primary-button>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($hasData)
                    <tfoot class="bg-[#eeedf2] border-t border-[#c3c6d1]">
                        <tr>
                            <td class="p-table-cell-padding font-bold text-[#001e40]" colspan="2">OCTOBER TOTALS</td>
                            <td class="p-table-cell-padding text-center font-bold text-[#001e40]">202</td>
                            <td class="p-table-cell-padding text-center font-bold text-[#ba1a1a]">250</td>
                            <td class="p-table-cell-padding text-center font-bold bg-green-100/50 text-green-800">452</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
            @if($hasData)
            <div class="p-gutter border-t border-[#c3c6d1] flex items-center justify-between bg-white">
                <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing 1 to {{ $entries->count() }} of {{ $entries->count() }} entries</p>
                <div class="flex gap-2">
                    <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg disabled:opacity-30" disabled><span class="material-symbols-outlined text-[20px]">chevron_left</span></button>
                    <button class="w-9 h-9 flex items-center justify-center bg-[#001e40] text-white rounded-lg font-bold text-sm">1</button>
                    <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg"><span class="material-symbols-outlined text-[20px]">chevron_right</span></button>
                </div>
            </div>
            @endif
        </div>

        {{-- Bottom Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter">
            <div class="bg-white p-gutter rounded-xl border border-[#c3c6d1] shadow-sm">
                <h5 class="font-bold text-[#001e40] text-lg mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined bg-[#eeedf2] p-1.5 rounded-lg">analytics</span>
                    Inventory Valuation
                </h5>
                @if($hasData)
                <div class="space-y-4 text-sm">
                    @foreach([['Unit Cost (Weighted Avg)', '₱ 14.50', 'text-[#1a1c1f]'],['Ending Inventory Value','₱ 6,554.00','text-[#001e40]'],['Consumables Issued (Oct)','₱ 3,625.00','text-[#ba1a1a]']] as [$l,$v,$c])
                    <div class="flex justify-between items-center py-2 border-b border-[#eeedf2] border-dotted">
                        <span class="text-[#43474f]">{{ $l }}</span>
                        <span class="font-bold {{ $c }}">{{ $v }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="py-10 text-center flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-[40px] text-[#c3c6d1]">bar_chart</span>
                    <p class="text-[13px] text-[#43474f]">No valuation data available yet.</p>
                </div>
                @endif
            </div>
            <div class="bg-white border border-[#c3c6d1] rounded-xl overflow-hidden shadow-sm min-h-[180px]">
                <div class="relative h-full min-h-[180px]">
                    <img src="{{ asset('images/inventory-bg.png') }}" alt="Warehouse" class="absolute inset-0 w-full h-full object-cover"/>
                    <div class="absolute inset-0 bg-gradient-to-r from-[#001e40]/90 via-[#001e40]/60 to-transparent flex items-center p-8">
                        <div class="max-w-[70%]">
                            <h6 class="text-xl font-bold text-white mb-2">Monthly RSMI Readiness</h6>
                            <p class="text-sm text-white/90 leading-relaxed">All issuances for October have been cross-referenced with RIS numbers. Ready for regional approval.</p>
                            <button class="mt-5 bg-white text-[#001e40] px-5 py-2.5 rounded-lg font-bold text-sm flex items-center gap-2 shadow-xl hover:bg-[#eeedf2] active:scale-95 transition-all">
                                <span class="material-symbols-outlined text-[20px]">verified</span>
                                Finalize Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="fixed bottom-8 right-8 w-14 h-14 bg-[#001e40] text-white rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-50 group">
        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">add</span>
        <span class="absolute right-16 bg-[#1a1c1f] text-white px-3 py-1.5 rounded-lg text-xs font-bold opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-xl">Log Stock Entry</span>
    </button>
</x-app-layout>
