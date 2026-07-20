<x-app-layout>
    @section('header_title', 'Command Center')

    <div class="p-container-padding bg-background flex flex-col gap-6">

        {{-- Welcome Banner --}}
        <div class="bg-[#001e40] rounded-xl p-6 flex items-center justify-between overflow-hidden relative shadow-lg">
            <div class="relative z-10">
                <p class="text-[#a7c8ff] font-bold text-[12px] uppercase tracking-widest mb-1">PhilHealth AIM · Region X</p>
                <h2 class="text-2xl font-bold text-white">Good morning, {{ auth()->user()->name }} 👋</h2>
                <p class="text-white/60 text-sm mt-1">Here's an operational snapshot of Region X as of today.</p>
            </div>
            <div class="hidden md:flex items-center gap-3 relative z-10">
                <x-primary-button variant="secondary" icon="add" class="!bg-white !text-[#001e40] hover:!bg-[#eeedf2]">
                    New PR
                </x-primary-button>
                <x-primary-button icon="bar_chart" class="!bg-[#a7c8ff] !text-[#001b3c] hover:!opacity-90">
                    Run Report
                </x-primary-button>
            </div>
            {{-- Decorative --}}
            <div class="absolute -right-8 -top-8 w-48 h-48 rounded-full bg-white/5 pointer-events-none"></div>
            <div class="absolute -right-2 -bottom-10 w-36 h-36 rounded-full bg-white/5 pointer-events-none"></div>
        </div>

        {{-- KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            {{-- Active PRs --}}
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm">
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Active PRs</span>
                    <div class="w-9 h-9 bg-[#001e40]/8 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#001e40] text-[20px]" style="font-variation-settings: 'FILL' 1;">description</span>
                    </div>
                </div>
                <p class="text-3xl font-bold text-[#001e40]">24</p>
                <div class="mt-3 flex items-center gap-1 text-[11px] font-bold text-[#ba1a1a]">
                    <span class="material-symbols-outlined text-[14px]">arrow_upward</span>+3 today
                </div>
                <p class="text-[10px] text-[#43474f] mt-1 uppercase tracking-wider font-bold">In-Procurement Queue</p>
            </div>
            {{-- Pending Deliveries --}}
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm">
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Deliveries</span>
                    <div class="w-9 h-9 bg-[#ffdbca]/40 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#723610] text-[20px]" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
                    </div>
                </div>
                <p class="text-3xl font-bold text-[#001e40]">08</p>
                <div class="mt-3 flex items-center gap-1 text-[11px] font-bold text-[#575f67]">
                    <span class="material-symbols-outlined text-[14px]">schedule</span>Expected by Fri
                </div>
                <p class="text-[10px] text-[#43474f] mt-1 uppercase tracking-wider font-bold">Awaiting IAR</p>
            </div>
            {{-- Serialized Stock --}}
            <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm">
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Tracked Assets</span>
                    <div class="w-9 h-9 bg-[#d8e1ea]/60 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#3a5f94] text-[20px]" style="font-variation-settings: 'FILL' 1;">qr_code_2</span>
                    </div>
                </div>
                <p class="text-3xl font-bold text-[#001e40]">1,482</p>
                <div class="mt-3 flex items-center gap-1 text-[11px] font-bold text-green-700">
                    <span class="material-symbols-outlined text-[14px]">verified</span>98% Audited
                </div>
                <p class="text-[10px] text-[#43474f] mt-1 uppercase tracking-wider font-bold">Items in Region X</p>
            </div>
            {{-- COB Balance --}}
            <div class="bg-[#001e40] border border-[#003366] p-gutter rounded-xl shadow-lg">
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[12px] font-bold text-[#a7c8ff] uppercase tracking-wider">COB Balance</span>
                    <span class="material-symbols-outlined text-[#a7c8ff] text-[20px]" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
                </div>
                <p class="text-3xl font-bold text-[#a7c8ff]">₱4.2M</p>
                <div class="mt-3 w-full bg-[#003366] h-2 rounded-full overflow-hidden">
                    <div class="bg-[#a7c8ff] h-full rounded-full" style="width: 75%"></div>
                </div>
                <p class="text-[10px] text-[#a7c8ff]/70 mt-2 uppercase tracking-wider font-bold">75% of Monthly Allocation</p>
            </div>
        </div>

        {{-- Middle Row: Workflow + Alerts --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">

            {{-- Asset Lifecycle Workflow --}}
            <div class="lg:col-span-2 bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h3 class="font-bold text-xl text-[#001e40]">Asset Lifecycle Workflow</h3>
                        <p class="text-[13px] text-[#43474f] mt-0.5">Real-time status of the resource acquisition pipeline</p>
                    </div>
                    <x-primary-button variant="secondary" icon="open_in_new">
                        View Details
                    </x-primary-button>
                </div>
                <div class="relative">
                    {{-- Connector line --}}
                    <div class="absolute top-8 left-[12.5%] right-[12.5%] h-0.5 bg-[#c3c6d1] hidden md:block"></div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        @foreach([
                            ['icon' => 'payments',     'label' => 'Budget',    'sub' => 'Approval & CA',          'badge' => 'Active: 12',  'active' => true],
                            ['icon' => 'receipt_long', 'label' => 'PO',        'sub' => 'Purchase Order',          'badge' => 'Pending: 5',  'active' => false],
                            ['icon' => 'fact_check',   'label' => 'IAR',       'sub' => 'Inspection & Acceptance', 'badge' => 'Processing: 3','active' => false],
                            ['icon' => 'person_pin',   'label' => 'ICS / PAR', 'sub' => 'Accountability',          'badge' => 'Deployed: 842','active' => false],
                        ] as $step)
                        <div class="flex flex-col items-center text-center">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center shadow-md mb-4 ring-4 ring-white relative z-10 {{ $step['active'] ? 'bg-[#001e40] text-[#a7c8ff]' : 'bg-[#eeedf2] text-[#43474f] border-2 border-[#c3c6d1]' }}">
                                <span class="material-symbols-outlined text-[28px]">{{ $step['icon'] }}</span>
                            </div>
                            <h4 class="font-bold text-sm text-[#001e40]">{{ $step['label'] }}</h4>
                            <p class="text-[12px] text-[#43474f] mt-0.5">{{ $step['sub'] }}</p>
                            <span class="mt-3 px-3 py-1 text-[10px] font-bold rounded-full uppercase {{ $step['active'] ? 'bg-[#d5e3ff] text-[#001b3c]' : 'bg-[#eeedf2] text-[#43474f]' }}">
                                {{ $step['badge'] }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Critical Alerts --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-gutter flex flex-col">
                <h3 class="font-bold text-[#001e40] text-lg mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#ba1a1a] text-[22px]" style="font-variation-settings: 'FILL' 1;">warning</span>
                    Critical Alerts
                    <span class="ml-auto px-2 py-0.5 bg-[#ffdad6] text-[#ba1a1a] text-[10px] font-bold rounded-full uppercase">3 Active</span>
                </h3>
                <div class="space-y-3 flex-1">
                    <div class="p-3 border border-[#ffdad6] bg-[#ffdad6]/20 rounded-xl flex gap-3">
                        <span class="material-symbols-outlined text-[#ba1a1a] flex-shrink-0 mt-0.5" style="font-variation-settings: 'FILL' 1;">error</span>
                        <div>
                            <p class="text-sm font-bold text-[#93000a]">Low Stock Warning</p>
                            <p class="text-[12px] text-[#43474f] mt-0.5 leading-relaxed">Standard Form 12-A below critical threshold in Central Depot.</p>
                        </div>
                    </div>
                    <div class="p-3 border border-[#ffdbca] bg-[#ffdbca]/20 rounded-xl flex gap-3">
                        <span class="material-symbols-outlined text-[#723610] flex-shrink-0 mt-0.5">schedule</span>
                        <div>
                            <p class="text-sm font-bold text-[#723610]">Expiring PARs</p>
                            <p class="text-[12px] text-[#43474f] mt-0.5 leading-relaxed">12 items assigned to HR Unit require re-validation this month.</p>
                        </div>
                    </div>
                    <div class="p-3 border border-[#d5e3ff] bg-[#d5e3ff]/20 rounded-xl flex gap-3">
                        <span class="material-symbols-outlined text-[#001e40] flex-shrink-0 mt-0.5" style="font-variation-settings: 'FILL' 1;">info</span>
                        <div>
                            <p class="text-sm font-bold text-[#001e40]">Audit Scheduled</p>
                            <p class="text-[12px] text-[#43474f] mt-0.5 leading-relaxed">Central Office audit sync scheduled for Friday at 14:00.</p>
                        </div>
                    </div>
                </div>
                <x-primary-button variant="secondary" icon="done_all" class="w-full justify-center mt-4">
                    Clear All Notifications
                </x-primary-button>
            </div>
        </div>

        {{-- Bottom Row: Recent Activity + Module Quick Links --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">

            {{-- Recent Procurement Activity --}}
            <div class="lg:col-span-2 bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden">
                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex justify-between items-center">
                    <h3 class="font-bold text-[#001e40] text-lg">Recent Procurement Activity</h3>
                    <a href="{{ route('procurement') }}" wire:navigate class="text-[12px] font-bold text-[#001e40] hover:underline flex items-center gap-1">
                        View All <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Ref No.</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Description</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Vendor</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Amount</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#001e40]">PR-2023-0842</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">Laptops for IT Unit (8 Units)</td>
                                <td class="p-table-cell-padding text-[#43474f]">NexTech Solutions</td>
                                <td class="p-table-cell-padding text-right font-bold text-[#1a1c1f]">₱456,000</td>
                                <td class="p-table-cell-padding text-center"><span class="px-2 py-0.5 bg-[#d8e1ea] text-[#5b646b] text-[10px] font-bold rounded-full uppercase">PO Pending</span></td>
                            </tr>
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#001e40]">PO-2023-0115</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">Common Office Supplies (Q3)</td>
                                <td class="p-table-cell-padding text-[#43474f]">PaperCo Phil.</td>
                                <td class="p-table-cell-padding text-right font-bold text-[#1a1c1f]">₱12,450</td>
                                <td class="p-table-cell-padding text-center"><span class="px-2 py-0.5 bg-[#ffdbca] text-[#341100] text-[10px] font-bold rounded-full uppercase">Delivered</span></td>
                            </tr>
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#001e40]">PR-2023-0839</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">Aircon Unit Maintenance</td>
                                <td class="p-table-cell-padding text-[#43474f]">CoolAir Services</td>
                                <td class="p-table-cell-padding text-right font-bold text-[#1a1c1f]">₱8,500</td>
                                <td class="p-table-cell-padding text-center"><span class="px-2 py-0.5 bg-[#ffdad6] text-[#93000a] text-[10px] font-bold rounded-full uppercase">Urgent</span></td>
                            </tr>
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#001e40]">PO-2023-0109</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">Steel Filing Cabinets (4-drawer)</td>
                                <td class="p-table-cell-padding text-[#43474f]">OfficePlus Inc.</td>
                                <td class="p-table-cell-padding text-right font-bold text-[#1a1c1f]">₱24,800</td>
                                <td class="p-table-cell-padding text-center"><span class="px-2 py-0.5 bg-[#d5e3ff] text-[#001b3c] text-[10px] font-bold rounded-full uppercase">Approved</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Module Quick Links --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-gutter flex flex-col gap-3">
                <h3 class="font-bold text-[#001e40] text-lg mb-1">Quick Navigate</h3>
                @foreach([
                    ['icon' => 'shopping_cart',  'label' => 'Procurement',    'sub' => '42 Active Requests',    'route' => 'procurement',    'color' => 'bg-[#d5e3ff] text-[#001b3c]'],
                    ['icon' => 'inventory_2',    'label' => 'Inventory',      'sub' => 'RSMI Pending: 2',        'route' => 'inventory',      'color' => 'bg-[#d8e1ea] text-[#5b646b]'],
                    ['icon' => 'assignment_ind', 'label' => 'Accountability', 'sub' => '1,284 Assets Tracked',  'route' => 'accountability', 'color' => 'bg-[#eeedf2] text-[#43474f]'],
                    ['icon' => 'build',          'label' => 'Repairs',        'sub' => '7 Urgent Requests',     'route' => 'repairs',        'color' => 'bg-[#ffdad6] text-[#93000a]'],
                    ['icon' => 'assessment',     'label' => 'Reports',        'sub' => '3 Overdue Reports',     'route' => 'reports',        'color' => 'bg-[#ffdbca] text-[#723610]'],
                ] as $module)
                <a href="{{ route($module['route']) }}" wire:navigate
                   class="flex items-center gap-4 p-3 rounded-xl border border-transparent hover:border-[#c3c6d1] hover:bg-[#f9f9fe] transition-all group">
                    <div class="w-10 h-10 {{ $module['color'] }} rounded-xl flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px]">{{ $module['icon'] }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-[#001e40]">{{ $module['label'] }}</p>
                        <p class="text-[11px] text-[#43474f] font-medium">{{ $module['sub'] }}</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-[#c3c6d1] group-hover:text-[#001e40] transition-colors">arrow_forward_ios</span>
                </a>
                @endforeach
            </div>
        </div>

    </div>
</x-app-layout>
