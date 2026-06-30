{{--
    Signatory Row Partial
    Variables expected:
        $row  — SignatoryRegistry model instance (with primaryEmployee, oicPrimary, oicSecondary loaded)
--}}
@php
    $holderColors = [
        'PRIMARY' => ['ring' => 'ring-[#001e40]',   'dot' => 'bg-[#001e40]',   'badge' => 'bg-[#001e40]/10 text-[#001e40]',   'label' => 'Primary'],
        'OIC_1'   => ['ring' => 'ring-amber-500',    'dot' => 'bg-amber-500',   'badge' => 'bg-amber-50 text-amber-700 border border-amber-200', 'label' => 'OIC 1'],
        'OIC_2'   => ['ring' => 'ring-orange-500',   'dot' => 'bg-orange-500',  'badge' => 'bg-orange-50 text-orange-700 border border-orange-200', 'label' => 'OIC 2'],
    ];
    $hc = $holderColors[$row->active_holder] ?? $holderColors['PRIMARY'];
@endphp
<tr class="hover:bg-[#f9f9fe] transition-colors group">

    {{-- Position --}}
    <td class="p-table-cell-padding">
        <div class="space-y-0.5">
            <p class="font-bold text-[#001e40] text-[13px]">{{ $row->position_title }}</p>
            <span class="font-mono text-[9px] font-bold uppercase tracking-widest text-[#43474f]/50 bg-[#eeedf2] px-1.5 py-0.5 rounded">{{ $row->position_code }}</span>
        </div>
    </td>

    {{-- Primary Holder --}}
    <td class="p-table-cell-padding">
        @if($row->primaryEmployee)
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-[#001e40] text-white flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                    {{ strtoupper(substr($row->primaryEmployee->fullname, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-[#001e40] text-[12px] truncate">{{ $row->primaryEmployee->fullname }}</p>
                    <p class="text-[10px] text-[#43474f]/60 truncate">{{ $row->primaryEmployee->designation }}</p>
                </div>
            </div>
        @else
            <span class="text-[11px] text-[#ba1a1a] font-bold flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">person_off</span> Unassigned
            </span>
        @endif
    </td>

    {{-- OIC 1 --}}
    <td class="p-table-cell-padding">
        @if($row->oicPrimary)
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                    {{ strtoupper(substr($row->oicPrimary->fullname, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-[#001e40] text-[12px] truncate">{{ $row->oicPrimary->fullname }}</p>
                    <p class="text-[10px] text-[#43474f]/60 truncate">{{ $row->oicPrimary->designation }}</p>
                </div>
            </div>
        @else
            <span class="text-[11px] text-[#43474f]/40 italic">— Not set</span>
        @endif
    </td>

    {{-- OIC 2 --}}
    <td class="p-table-cell-padding">
        @if($row->oicSecondary)
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-orange-100 text-orange-800 flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                    {{ strtoupper(substr($row->oicSecondary->fullname, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-[#001e40] text-[12px] truncate">{{ $row->oicSecondary->fullname }}</p>
                    <p class="text-[10px] text-[#43474f]/60 truncate">{{ $row->oicSecondary->designation }}</p>
                </div>
            </div>
        @else
            <span class="text-[11px] text-[#43474f]/40 italic">— Not set</span>
        @endif
    </td>

    {{-- Active Holder Segmented Toggle --}}
    <td class="p-table-cell-padding">
        <div class="flex justify-center">
            <div class="flex rounded-lg border border-[#c3c6d1] overflow-hidden shadow-sm bg-[#f9f9fe]" role="group" aria-label="Active holder for {{ $row->position_title }}">
                <button wire:click="setActiveHolder({{ $row->id }}, 'PRIMARY')"
                        title="Set Primary as active holder"
                        class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider transition-all flex items-center gap-1 border-r border-[#c3c6d1]
                               {{ $row->active_holder === 'PRIMARY' ? 'bg-[#001e40] text-white' : 'text-[#43474f] hover:bg-[#eeedf2]' }}">
                    <span class="material-symbols-outlined text-[13px]">person</span>
                    <span class="hidden sm:inline">Primary</span>
                </button>
                <button wire:click="setActiveHolder({{ $row->id }}, 'OIC_1')"
                        title="{{ $row->oicPrimary ? 'Set OIC 1 as active holder' : 'No OIC 1 assigned — configure first' }}"
                        class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider transition-all flex items-center gap-1 border-r border-[#c3c6d1]
                               {{ $row->active_holder === 'OIC_1' ? 'bg-amber-500 text-white' : 'text-[#43474f] hover:bg-[#eeedf2]' }}
                               {{ !$row->oicPrimary ? 'opacity-40 cursor-not-allowed' : '' }}">
                    <span class="material-symbols-outlined text-[13px]">swap_horiz</span>
                    <span class="hidden sm:inline">OIC 1</span>
                </button>
                <button wire:click="setActiveHolder({{ $row->id }}, 'OIC_2')"
                        title="{{ $row->oicSecondary ? 'Set OIC 2 as active holder' : 'No OIC 2 assigned — configure first' }}"
                        class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider transition-all flex items-center gap-1
                               {{ $row->active_holder === 'OIC_2' ? 'bg-orange-500 text-white' : 'text-[#43474f] hover:bg-[#eeedf2]' }}
                               {{ !$row->oicSecondary ? 'opacity-40 cursor-not-allowed' : '' }}">
                    <span class="material-symbols-outlined text-[13px]">swap_calls</span>
                    <span class="hidden sm:inline">OIC 2</span>
                </button>
            </div>
        </div>
    </td>

    {{-- Configure Action --}}
    <td class="p-table-cell-padding text-right">
        <button wire:click="openEdit({{ $row->id }})"
                title="Configure this signatory slot"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#c3c6d1] hover:border-[#001e40] hover:bg-[#f4f3f8] text-[#43474f] hover:text-[#001e40] text-[11px] font-bold rounded-lg transition-all shadow-sm active:scale-95">
            <span class="material-symbols-outlined text-[15px]">edit_note</span>
            Configure
        </button>
    </td>
</tr>
