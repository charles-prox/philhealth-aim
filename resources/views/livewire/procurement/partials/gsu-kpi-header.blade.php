        {{-- KPI Bento Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-gutter">
            @php $kpis = [
                ['label' => 'Active Requests',    'value' => $totalActive > 0 ? $totalActive : null, 'sub' => 'Ongoing tracking',    'icon' => 'description',     'icon_bg' => 'bg-[#001e40]/8',   'icon_color' => 'text-[#001e40]', 'trend' => 'up',   'trend_color' => 'text-green-700'],
                ['label' => 'Total PO Value',     'value' => $totalValue > 0 ? '₱'.number_format($totalValue/1000000, 2).'M' : null, 'sub' => 'Awarded & Released', 'icon' => 'account_balance_wallet', 'icon_bg' => 'bg-[#d5e3ff]/60', 'icon_color' => 'text-[#1f477b]', 'trend' => null,   'trend_color' => ''],
                ['label' => 'Pending Action',   'value' => $totalPending > 0 ? $totalPending : null, 'sub' => 'Draft / Unawarded',   'icon' => 'pending_actions',  'icon_bg' => 'bg-[#ffdad6]/60', 'icon_color' => 'text-[#ba1a1a]', 'trend' => 'alert','trend_color' => 'text-[#ba1a1a]'],
                ['label' => 'Avg Turnaround',     'value' => $avgTurnaround > 0 ? $avgTurnaround.'d': null, 'sub' => 'Delivery time',    'icon' => 'timer',            'icon_bg' => 'bg-green-50',      'icon_color' => 'text-green-700', 'trend' => 'check', 'trend_color' => 'text-green-700'],
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

        {{-- Tab Switching --}}
        <div class="flex border-b border-[#c3c6d1] gap-6 mb-6">
            <button wire:click="$set('activeTab', 'registry')" class="pb-3 text-sm font-bold border-b-2 transition-all flex items-center gap-2 {{ $activeTab === 'registry' ? 'border-[#001e40] text-[#001e40]' : 'border-transparent text-[#43474f] hover:text-[#001e40]' }}">
                <span class="material-symbols-outlined text-[18px]">format_list_bulleted</span>
                Procurement Registry
            </button>
            <button wire:click="$set('activeTab', 'triage')" class="pb-3 text-sm font-bold border-b-2 transition-all flex items-center gap-2 relative {{ $activeTab === 'triage' ? 'border-[#001e40] text-[#001e40]' : 'border-transparent text-[#43474f] hover:text-[#001e40]' }}">
                <span class="material-symbols-outlined text-[18px]">move_to_inbox</span>
                GSU Inbox
                @if($triageCount > 0)
                    <span class="px-2 py-0.5 text-[10px] font-black bg-[#ba1a1a] text-white rounded-full animate-pulse">{{ $triageCount }}</span>
                @endif
            </button>
        </div>

        @if($activeTab === 'registry')
