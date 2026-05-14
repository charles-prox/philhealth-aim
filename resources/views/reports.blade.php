<x-app-layout>
    @section('header_title', 'Reports & Analytics')

    <div class="p-container-padding bg-background space-y-6">

        <!-- KPI Summary Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-gutter">
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Reports Generated</span>
                    <span class="material-symbols-outlined text-[#001e40]">description</span>
                </div>
                <div class="text-2xl font-bold text-[#001e40]">184</div>
                <div class="text-[11px] text-[#43474f] flex items-center gap-1 font-bold">
                    <span class="material-symbols-outlined text-[14px] text-green-600">trending_up</span>
                    +8 this month
                </div>
            </div>
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Scheduled Reports</span>
                    <span class="material-symbols-outlined text-[#001e40]">schedule</span>
                </div>
                <div class="text-2xl font-bold text-[#001e40]">12</div>
                <div class="text-[11px] text-[#43474f] font-bold uppercase">Next: Monthly RSMI · May 31</div>
            </div>
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Pending Review</span>
                    <span class="material-symbols-outlined text-[#592300]">pending_actions</span>
                </div>
                <div class="text-2xl font-bold text-[#592300]">5</div>
                <div class="text-[11px] text-[#43474f] font-bold uppercase">Awaiting supervisor sign-off</div>
            </div>
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between h-32 border-l-4 border-l-[#ba1a1a]">
                <div class="flex justify-between items-start">
                    <span class="text-[12px] font-bold text-[#ba1a1a] uppercase tracking-wider">Overdue Reports</span>
                    <span class="material-symbols-outlined text-[#ba1a1a]">warning</span>
                </div>
                <div class="text-2xl font-bold text-[#ba1a1a]">3</div>
                <div class="text-[11px] text-[#43474f] font-bold uppercase">Requires immediate action</div>
            </div>
        </div>

        <!-- Two-Column Layout: Quick Generate + Recent Reports -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">

            <!-- Quick Report Generator -->
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-gutter flex flex-col gap-5">
                <div>
                    <h3 class="font-h2 text-h2 text-[#001e40] flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#001e40] bg-[#eeedf2] p-1.5 rounded-lg">bolt</span>
                        Quick Generate
                    </h3>
                    <p class="text-[12px] text-[#43474f] mt-1">Select a report type and date range to export instantly.</p>
                </div>

                <div class="space-y-4">
                    <x-form-select label="Report Type" icon="description" placeholder="Monthly RSMI Report"
                        :options="[
                            'rsmi'      => 'Monthly RSMI Report',
                            'par'       => 'PAR Summary (Accountability)',
                            'jo'        => 'Job Order Summary (Repairs)',
                            'pr'        => 'Procurement Status Report',
                            'disposal'  => 'Asset Disposal Report',
                            'inventory' => 'Stock Valuation Report',
                        ]" />

                    <div class="flex flex-col gap-2">
                        <label class="text-[12px] text-[#43474f] font-bold uppercase tracking-wide">Reporting Period</label>
                        <div class="flex items-center gap-2">
                            <input type="date" value="2026-05-01" class="flex-1 py-2.5 bg-white border border-[#c3c6d1] rounded-lg focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] transition-all text-sm px-4" />
                            <span class="text-[#43474f] font-bold text-xs uppercase">to</span>
                            <input type="date" value="2026-05-31" class="flex-1 py-2.5 bg-white border border-[#c3c6d1] rounded-lg focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] transition-all text-sm px-4" />
                        </div>
                    </div>

                    <x-form-select label="Format" icon="file_download" placeholder="PDF Document"
                        :options="[
                            'pdf'   => 'PDF Document',
                            'xlsx'  => 'Excel Spreadsheet (.xlsx)',
                            'csv'   => 'CSV Data Export',
                        ]" />
                </div>

                <x-primary-button icon="download" class="w-full justify-center">
                    Generate & Download
                </x-primary-button>
            </div>

            <!-- Recent Reports Table -->
            <div class="lg:col-span-2 bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden flex flex-col">
                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex justify-between items-center">
                    <h3 class="font-h2 text-h2 text-[#001e40]">Recent Reports</h3>
                    <div class="flex gap-2">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                            <input type="text" placeholder="Search reports..." class="pl-9 pr-4 py-2 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] outline-none transition-all w-48 placeholder-[#43474f]/40"/>
                        </div>
                        <x-primary-button variant="secondary" icon="filter_list" class="!px-3" />
                    </div>
                </div>
                <div class="overflow-x-auto custom-scrollbar flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                                <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Report Name</th>
                                <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Module</th>
                                <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Generated By</th>
                                <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Date</th>
                                <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                                <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#001e40]">Monthly RSMI — April 2026</span>
                                        <span class="text-[11px] text-[#43474f]">Office Supplies · OS-2026-Q2</span>
                                    </div>
                                </td>
                                <td class="p-table-cell-padding"><span class="px-2 py-0.5 bg-[#d5e3ff] text-[#001b3c] text-[10px] font-bold rounded-full uppercase">Inventory</span></td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">J. Dela Cruz</td>
                                <td class="p-table-cell-padding text-[#43474f]">May 02, 2026</td>
                                <td class="p-table-cell-padding">
                                    <div class="flex items-center gap-2 text-green-700 font-bold">
                                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                        Approved
                                    </div>
                                </td>
                                <td class="p-table-cell-padding text-right">
                                    <div class="flex justify-end gap-1">
                                        <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all" title="Download PDF"><span class="material-symbols-outlined text-[20px]">download</span></button>
                                        <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all" title="View"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#001e40]">PAR Summary — Q1 2026</span>
                                        <span class="text-[11px] text-[#43474f]">All Sections · Regional Audit</span>
                                    </div>
                                </td>
                                <td class="p-table-cell-padding"><span class="px-2 py-0.5 bg-[#d8e1ea] text-[#5b646b] text-[10px] font-bold rounded-full uppercase">Accountability</span></td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">M. Santos</td>
                                <td class="p-table-cell-padding text-[#43474f]">Apr 30, 2026</td>
                                <td class="p-table-cell-padding">
                                    <div class="flex items-center gap-2 text-[#592300] font-bold">
                                        <span class="material-symbols-outlined text-[16px]">pending</span>
                                        Pending
                                    </div>
                                </td>
                                <td class="p-table-cell-padding text-right">
                                    <div class="flex justify-end gap-1">
                                        <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all" title="Download PDF"><span class="material-symbols-outlined text-[20px]">download</span></button>
                                        <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all" title="View"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#001e40]">Job Order Summary — April 2026</span>
                                        <span class="text-[11px] text-[#43474f]">IT Equipment · Region X</span>
                                    </div>
                                </td>
                                <td class="p-table-cell-padding"><span class="px-2 py-0.5 bg-[#ffdbca] text-[#723610] text-[10px] font-bold rounded-full uppercase">Repairs</span></td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">R. Enriquez</td>
                                <td class="p-table-cell-padding text-[#43474f]">May 01, 2026</td>
                                <td class="p-table-cell-padding">
                                    <div class="flex items-center gap-2 text-green-700 font-bold">
                                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                        Approved
                                    </div>
                                </td>
                                <td class="p-table-cell-padding text-right">
                                    <div class="flex justify-end gap-1">
                                        <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all" title="Download PDF"><span class="material-symbols-outlined text-[20px]">download</span></button>
                                        <button class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all" title="View"><span class="material-symbols-outlined text-[20px]">visibility</span></button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#001e40]">Procurement Status — May 2026</span>
                                        <span class="text-[11px] text-[#ba1a1a] font-medium">OVERDUE · Submit by May 10</span>
                                    </div>
                                </td>
                                <td class="p-table-cell-padding"><span class="px-2 py-0.5 bg-[#001e40]/10 text-[#001e40] text-[10px] font-bold rounded-full uppercase">Procurement</span></td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">—</td>
                                <td class="p-table-cell-padding text-[#ba1a1a] font-bold">Not submitted</td>
                                <td class="p-table-cell-padding">
                                    <div class="flex items-center gap-2 text-[#ba1a1a] font-bold">
                                        <span class="material-symbols-outlined text-[16px]">warning</span>
                                        Overdue
                                    </div>
                                </td>
                                <td class="p-table-cell-padding text-right">
                                    <x-primary-button icon="edit" class="!py-1.5 !text-xs">
                                        Draft
                                    </x-primary-button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="p-gutter border-t border-[#c3c6d1] flex items-center justify-between bg-[#f9f9fe]">
                    <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing 1 to 4 of 184 reports</p>
                    <div class="flex gap-2">
                        <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8] transition-all disabled:opacity-30" disabled><span class="material-symbols-outlined text-[20px]">chevron_left</span></button>
                        <button class="w-9 h-9 flex items-center justify-center bg-[#001e40] text-white rounded-lg font-bold text-sm shadow-sm">1</button>
                        <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8] transition-all font-bold text-sm">2</button>
                        <button class="w-9 h-9 flex items-center justify-center border border-[#c3c6d1] rounded-lg hover:bg-[#f4f3f8] transition-all"><span class="material-symbols-outlined text-[20px]">chevron_right</span></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scheduled Reports -->
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-gutter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-h2 text-h2 text-[#001e40] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#001e40] bg-[#eeedf2] p-1.5 rounded-lg">calendar_month</span>
                    Scheduled Reports
                </h3>
                <x-primary-button variant="secondary" icon="add">
                    Add Schedule
                </x-primary-button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Scheduled Card 1 -->
                <div class="p-4 border border-[#c3c6d1] rounded-xl hover:border-[#001e40] hover:shadow-md transition-all group">
                    <div class="flex justify-between items-start mb-3">
                        <span class="px-2 py-0.5 bg-[#d5e3ff] text-[#001b3c] text-[10px] font-bold rounded-full uppercase">Monthly</span>
                        <button class="p-1 text-[#43474f] hover:text-[#ba1a1a] transition-colors"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                    </div>
                    <h4 class="font-bold text-[#001e40] text-sm">Monthly RSMI Report</h4>
                    <p class="text-[11px] text-[#43474f] mt-1">Inventory · All Items</p>
                    <div class="mt-4 flex items-center gap-2 text-[11px] font-bold text-[#43474f] uppercase tracking-wider">
                        <span class="material-symbols-outlined text-[16px] text-[#001e40]">schedule</span>
                        Every 31st · Auto PDF
                    </div>
                </div>
                <!-- Scheduled Card 2 -->
                <div class="p-4 border border-[#c3c6d1] rounded-xl hover:border-[#001e40] hover:shadow-md transition-all group">
                    <div class="flex justify-between items-start mb-3">
                        <span class="px-2 py-0.5 bg-[#d8e1ea] text-[#5b646b] text-[10px] font-bold rounded-full uppercase">Quarterly</span>
                        <button class="p-1 text-[#43474f] hover:text-[#ba1a1a] transition-colors"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                    </div>
                    <h4 class="font-bold text-[#001e40] text-sm">PAR Accountability Summary</h4>
                    <p class="text-[11px] text-[#43474f] mt-1">Accountability · All Sections</p>
                    <div class="mt-4 flex items-center gap-2 text-[11px] font-bold text-[#43474f] uppercase tracking-wider">
                        <span class="material-symbols-outlined text-[16px] text-[#001e40]">schedule</span>
                        End of Quarter · Excel
                    </div>
                </div>
                <!-- Scheduled Card 3 -->
                <div class="p-4 border border-[#c3c6d1] rounded-xl hover:border-[#001e40] hover:shadow-md transition-all group">
                    <div class="flex justify-between items-start mb-3">
                        <span class="px-2 py-0.5 bg-[#ffdbca] text-[#723610] text-[10px] font-bold rounded-full uppercase">Weekly</span>
                        <button class="p-1 text-[#43474f] hover:text-[#ba1a1a] transition-colors"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                    </div>
                    <h4 class="font-bold text-[#001e40] text-sm">Job Order Status Summary</h4>
                    <p class="text-[11px] text-[#43474f] mt-1">Repairs · All Technicians</p>
                    <div class="mt-4 flex items-center gap-2 text-[11px] font-bold text-[#43474f] uppercase tracking-wider">
                        <span class="material-symbols-outlined text-[16px] text-[#001e40]">schedule</span>
                        Every Monday · CSV
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
